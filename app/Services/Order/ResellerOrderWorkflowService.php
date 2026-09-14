<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderCommentContextType;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderComment;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Feeder\Core\Services\Order\OrderCommentService;
use Feeder\Core\Services\Order\OrderDiscountService;
use Feeder\Core\Services\Order\OrderStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reseller Portal adapter for single-order operational workflow.
 *
 * Company scoping is enforced here before invoking Core domain services.
 * Controllers must still apply permission middleware.
 */
class ResellerOrderWorkflowService
{
    public function __construct(
        private readonly ResellerOrderListService $orderListService,
        private readonly OrderStatusService $statusService,
        private readonly OrderCcaAssignmentService $ccaAssignmentService,
        private readonly OrderCommentService $commentService,
        private readonly OrderDiscountService $discountService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
    ) {}

    public function findForCompany(User $actor, string $orderUuid): ?Order
    {
        return $this->orderListService->findForCompany($actor, $orderUuid);
    }

    public function findOrFailForCompany(User $actor, string $orderUuid): Order
    {
        $order = $this->findForCompany($actor, $orderUuid);

        if ($order === null) {
            abort(404);
        }

        return $order;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return array_map(
            static fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            OrderStatus::cases()
        );
    }

    /**
     * Target statuses for reactivation (everything except CANCELLED).
     *
     * @return list<array{value: string, label: string}>
     */
    public function reactivationStatusOptions(): array
    {
        return array_values(array_filter(
            $this->statusOptions(),
            static fn (array $option) => $option['value'] !== OrderStatus::CANCELLED->value
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function commentContextOptions(): array
    {
        return array_map(
            static fn (OrderCommentContextType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            // SYSTEM comments are reserved for automated writes.
            array_filter(
                OrderCommentContextType::cases(),
                static fn (OrderCommentContextType $type) => $type !== OrderCommentContextType::SYSTEM
            )
        );
    }

    /**
     * Eligible CCAs for the order's reseller company.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function eligibleCcas(User $actor, Order $order): Collection
    {
        return User::query()
            ->where('company_id', (int) $order->reseller_company_id)
            ->whereHas('role', fn (Builder $query) => $query->where(
                'slug',
                CallCenterAgentEligibilityService::ROLE_SLUG
            ))
            ->with(['profile', 'role.portal'])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $cca) => $this->ccaEligibilityService->isEligible(
                $cca,
                (int) $order->reseller_company_id
            ))
            ->map(function (User $cca) {
                $name = trim(($cca->profile?->first_name ?? '').' '.($cca->profile?->last_name ?? ''));

                return [
                    'id' => (int) $cca->id,
                    'name' => $name !== '' ? $name : ($cca->phone ?? 'CCA #'.$cca->id),
                ];
            })
            ->values();
    }

    public function canReactivate(Order $order): bool
    {
        return $this->statusService->canReactivate($order);
    }

    public function updateStatus(
        User $actor,
        Order $order,
        OrderStatus|string $status,
        ?string $reason = null,
    ): Order {
        return $this->statusService->transition(
            $order,
            $status,
            (int) $actor->id,
            (int) $actor->company_id,
            $reason,
            (int) $actor->company_id,
        );
    }

    public function assignCca(User $actor, Order $order, int $ccaId, ?string $note = null): Order
    {
        return $this->ccaAssignmentService->assign(
            $order,
            $ccaId,
            (int) $actor->id,
            $note,
            (int) $actor->company_id,
            OrderCcaAssignmentOrigin::DIRECT,
        );
    }

    public function moveToPool(User $actor, Order $order): Order
    {
        return $this->ccaAssignmentService->moveToPool(
            $order,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    public function unassignCca(User $actor, Order $order): Order
    {
        return $this->ccaAssignmentService->unassign(
            $order,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    public function claimFromPool(User $actor, Order $order): Order
    {
        return $this->ccaAssignmentService->claimFromPool(
            $order,
            $actor,
            (int) $actor->company_id,
        );
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkAssignCca(User $actor, array $orderIds, int $ccaId, ?string $note = null): Collection
    {
        return $this->ccaAssignmentService->bulkAssign(
            $orderIds,
            $ccaId,
            (int) $actor->id,
            (int) $actor->company_id,
            $note,
        );
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkMoveToPool(User $actor, array $orderIds): Collection
    {
        return $this->ccaAssignmentService->bulkMoveToPool(
            $orderIds,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkUnassign(User $actor, array $orderIds): Collection
    {
        return $this->ccaAssignmentService->bulkUnassign(
            $orderIds,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    public function addComment(
        User $actor,
        Order $order,
        string $body,
        OrderCommentContextType|string $contextType,
    ): OrderComment {
        return $this->commentService->add(
            $order,
            $body,
            $contextType,
            (int) $actor->id,
            (int) $actor->company_id,
            null,
            (int) $actor->company_id,
        );
    }

    public function updateDiscount(User $actor, Order $order, float|string $discountAmount): Order
    {
        return $this->discountService->apply(
            $order,
            $discountAmount,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }
}
