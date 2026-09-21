<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderAssignmentState;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-side CCA assignment for unassigned company orders (Import Phase 1.2B).
 *
 * Delegates mutations to OrderCcaAssignmentService. Enforces company scope and
 * refuses silent reassignment of orders that already have a CCA.
 */
class ResellerOrderAssignmentService
{
    public function __construct(
        private readonly OrderCcaAssignmentService $ccaAssignmentService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly ResellerOrderCatalogService $orderCatalogService,
    ) {}

    /**
     * Assign one or more currently unassigned (no CCA) company orders to a CCA.
     *
     * @param  list<int|string>  $orderIds
     * @return Collection<int, Order>
     */
    public function assignOrders(User $actor, array $orderIds, int $ccaId, ?string $note = null): Collection
    {
        $companyId = $this->assertActorCompany($actor);
        $this->ccaEligibilityService->assertEligible($ccaId, $companyId);

        return DB::transaction(function () use ($actor, $orderIds, $ccaId, $note, $companyId) {
            $orders = $this->lockSelectedOrders($orderIds, $companyId);
            $this->assertAllHaveNoCca($orders);

            foreach ($orders as $order) {
                $this->ccaAssignmentService->assign(
                    $order,
                    $ccaId,
                    (int) $actor->id,
                    $note,
                    $companyId,
                    OrderCcaAssignmentOrigin::DIRECT,
                );
            }

            return $orders
                ->map(fn (Order $order) => $order->fresh(['cca', 'ccaAssignments']))
                ->values();
        });
    }

    /**
     * Randomly assign up to $quantity eligible unassigned company orders to a CCA.
     *
     * @return array{
     *     orders: Collection<int, Order>,
     *     requested: int,
     *     assigned_count: int,
     *     available_count: int
     * }
     */
    public function assignRandomQuantity(
        User $actor,
        int $ccaId,
        int $quantity,
        ?string $note = null,
    ): array {
        $companyId = $this->assertActorCompany($actor);
        $this->ccaEligibilityService->assertEligible($ccaId, $companyId);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $ccaId, $quantity, $note, $companyId) {
            $availableCount = $this->eligibleUnassignedQuery($companyId)->count();

            $candidateIds = $this->eligibleUnassignedQuery($companyId)
                ->inRandomOrder()
                ->limit($quantity)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($candidateIds === []) {
                return [
                    'orders' => collect(),
                    'requested' => $quantity,
                    'assigned_count' => 0,
                    'available_count' => $availableCount,
                ];
            }

            $orders = Order::query()
                ->whereIn('id', $candidateIds)
                ->where('reseller_company_id', $companyId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Concurrent assignment may have claimed some rows between select and lock.
            $stillEligible = $orders->filter(
                fn (Order $order) => $order->assignmentState() === OrderAssignmentState::UNASSIGNED
            )->values();

            foreach ($stillEligible as $order) {
                $this->ccaAssignmentService->assign(
                    $order,
                    $ccaId,
                    (int) $actor->id,
                    $note,
                    $companyId,
                    OrderCcaAssignmentOrigin::DIRECT,
                );
            }

            $assigned = $stillEligible
                ->map(fn (Order $order) => $order->fresh(['cca', 'ccaAssignments']))
                ->values();

            return [
                'orders' => $assigned,
                'requested' => $quantity,
                'assigned_count' => $assigned->count(),
                'available_count' => $availableCount,
            ];
        });
    }

    /**
     * Randomly assign quantities of eligible unassigned company orders across multiple CCAs.
     *
     * @param  list<array{cca_id: int|string, quantity: int|string}>  $allocations
     * @return array{
     *     orders: Collection<int, Order>,
     *     requested: int,
     *     assigned_count: int,
     *     available_count: int,
     *     allocations: list<array{cca_id: int, requested: int, assigned_count: int}>
     * }
     */
    public function assignRandomQuantitiesByCca(
        User $actor,
        array $allocations,
        ?string $note = null,
    ): array {
        $companyId = $this->assertActorCompany($actor);

        $normalized = [];
        $seenCcaIds = [];

        foreach ($allocations as $index => $allocation) {
            if (! is_array($allocation)) {
                throw ValidationException::withMessages([
                    'allocations.'.$index => ['Each allocation must include a CCA and quantity.'],
                ]);
            }

            $ccaId = (int) ($allocation['cca_id'] ?? 0);
            $quantity = (int) ($allocation['quantity'] ?? 0);

            if ($ccaId < 1) {
                throw ValidationException::withMessages([
                    'allocations.'.$index.'.cca_id' => ['Select a valid call center agent.'],
                ]);
            }

            if ($quantity < 1) {
                continue;
            }

            if ($quantity > 500) {
                throw ValidationException::withMessages([
                    'allocations.'.$index.'.quantity' => ['Quantity may not be greater than 500.'],
                ]);
            }

            if (isset($seenCcaIds[$ccaId])) {
                throw ValidationException::withMessages([
                    'allocations.'.$index.'.cca_id' => ['Each call center agent may appear only once.'],
                ]);
            }

            $this->ccaEligibilityService->assertEligible($ccaId, $companyId);
            $seenCcaIds[$ccaId] = true;
            $normalized[] = [
                'cca_id' => $ccaId,
                'quantity' => $quantity,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'allocations' => ['Enter a quantity of at least 1 for one or more call center agents.'],
            ]);
        }

        $requestedTotal = array_sum(array_column($normalized, 'quantity'));

        return DB::transaction(function () use ($actor, $normalized, $note, $companyId, $requestedTotal) {
            $availableCount = $this->eligibleUnassignedQuery($companyId)->count();

            if ($requestedTotal > $availableCount) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'Total quantity ('.$requestedTotal.') exceeds remaining unassigned orders ('.$availableCount.').',
                    ],
                ]);
            }

            $allAssigned = collect();
            $allocationResults = [];

            foreach ($normalized as $allocation) {
                $ccaId = (int) $allocation['cca_id'];
                $quantity = (int) $allocation['quantity'];

                $candidateIds = $this->eligibleUnassignedQuery($companyId)
                    ->inRandomOrder()
                    ->limit($quantity)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($candidateIds === []) {
                    $allocationResults[] = [
                        'cca_id' => $ccaId,
                        'requested' => $quantity,
                        'assigned_count' => 0,
                    ];
                    continue;
                }

                $orders = Order::query()
                    ->whereIn('id', $candidateIds)
                    ->where('reseller_company_id', $companyId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $stillEligible = $orders->filter(
                    fn (Order $order) => $order->assignmentState() === OrderAssignmentState::UNASSIGNED
                )->values();

                foreach ($stillEligible as $order) {
                    $this->ccaAssignmentService->assign(
                        $order,
                        $ccaId,
                        (int) $actor->id,
                        $note,
                        $companyId,
                        OrderCcaAssignmentOrigin::DIRECT,
                    );
                }

                $assigned = $stillEligible
                    ->map(fn (Order $order) => $order->fresh(['cca', 'ccaAssignments']))
                    ->values();

                $allAssigned = $allAssigned->concat($assigned);
                $allocationResults[] = [
                    'cca_id' => $ccaId,
                    'requested' => $quantity,
                    'assigned_count' => $assigned->count(),
                ];
            }

            return [
                'orders' => $allAssigned->values(),
                'requested' => $requestedTotal,
                'assigned_count' => $allAssigned->count(),
                'available_count' => $availableCount,
                'allocations' => $allocationResults,
            ];
        });
    }

    /**
     * Move selected currently unassigned company orders into the Order Pool.
     *
     * @param  list<int|string>  $orderIds
     * @return Collection<int, Order>
     */
    public function sendToPool(User $actor, array $orderIds): Collection
    {
        $companyId = $this->assertActorCompany($actor);

        return DB::transaction(function () use ($actor, $orderIds, $companyId) {
            $orders = $this->lockSelectedOrders($orderIds, $companyId);
            $this->assertAllUnassignedNotInPool($orders);

            foreach ($orders as $order) {
                $this->ccaAssignmentService->moveToPool(
                    $order,
                    (int) $actor->id,
                    $companyId,
                );
            }

            return $orders
                ->map(fn (Order $order) => $order->fresh(['cca', 'ccaAssignments']))
                ->values();
        });
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function eligibleCcas(User $actor): Collection
    {
        $this->assertActorCompany($actor);

        return $this->orderCatalogService->eligibleCcasForCompany($actor);
    }

    public function countEligibleUnassigned(User $actor): int
    {
        $companyId = $this->assertActorCompany($actor);

        return $this->eligibleUnassignedQuery($companyId)->count();
    }

    private function assertActorCompany(User $actor): int
    {
        $companyId = (int) ($actor->company_id ?? 0);

        if ($companyId < 1) {
            throw ValidationException::withMessages([
                'company' => ['Your account is not associated with a reseller company.'],
            ]);
        }

        return $companyId;
    }

    /**
     * @param  list<int|string>  $orderIds
     * @return Collection<int, Order>
     */
    private function lockSelectedOrders(array $orderIds, int $companyId): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $orderIds)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'order_ids' => ['Select at least one order.'],
            ]);
        }

        $orders = Order::query()
            ->whereIn('id', $ids)
            ->where('reseller_company_id', $companyId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($orders->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'order_ids' => ['One or more selected orders are invalid for this company.'],
            ]);
        }

        return $orders;
    }

    /**
     * Eligible for CCA assignment: no current CCA (Unassigned or Pool).
     *
     * @param  Collection<int, Order>  $orders
     */
    private function assertAllHaveNoCca(Collection $orders): void
    {
        $alreadyAssigned = $orders
            ->filter(fn (Order $order) => $order->cca_id !== null)
            ->values();

        if ($alreadyAssigned->isEmpty()) {
            return;
        }

        $labels = $alreadyAssigned
            ->map(fn (Order $order) => (string) ($order->order_number ?: '#'.$order->id))
            ->implode(', ');

        throw ValidationException::withMessages([
            'order_ids' => [
                'One or more selected orders are already assigned to a CCA and cannot be reassigned here: '.$labels.'.',
            ],
        ]);
    }

    /**
     * Eligible for pool: Unassigned only (not assigned, not already in pool).
     *
     * @param  Collection<int, Order>  $orders
     */
    private function assertAllUnassignedNotInPool(Collection $orders): void
    {
        $invalid = $orders
            ->filter(fn (Order $order) => $order->assignmentState() !== OrderAssignmentState::UNASSIGNED)
            ->values();

        if ($invalid->isEmpty()) {
            return;
        }

        $labels = $invalid
            ->map(function (Order $order) {
                $state = $order->assignmentState()->label();

                return (string) ($order->order_number ?: '#'.$order->id).' ('.$state.')';
            })
            ->implode(', ');

        throw ValidationException::withMessages([
            'order_ids' => [
                'Only currently unassigned orders can be moved to the Order Pool. Invalid selections: '.$labels.'.',
            ],
        ]);
    }

    private function eligibleUnassignedQuery(int $companyId): Builder
    {
        return Order::query()
            ->where('reseller_company_id', $companyId)
            ->assignmentState(OrderAssignmentState::UNASSIGNED);
    }
}
