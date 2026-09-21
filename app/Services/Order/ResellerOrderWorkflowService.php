<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderCommentContextType;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Enums\OrderPaymentMethod;
use Feeder\Core\Enums\OrderPaymentReviewStatus;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Models\CustomerBan;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderComment;
use Feeder\Core\Models\OrderPaymentSubmission;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Feeder\Core\Services\Order\OrderCommentService;
use Feeder\Core\Services\Order\OrderDiscountService;
use Feeder\Core\Services\Order\OrderStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
        private readonly ResellerOrderAssignmentService $orderAssignmentService,
        private readonly OrderCommentService $commentService,
        private readonly OrderDiscountService $discountService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly CustomerBanService $customerBanService,
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
        $this->assertCcaMayOperateOnOrder($actor, $order);

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
        $this->assertActorIsNotCallCenterAgent($actor, 'assign');

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
        $this->assertActorIsNotCallCenterAgent($actor, 'pool');

        return $this->ccaAssignmentService->moveToPool(
            $order,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    public function unassignCca(User $actor, Order $order): Order
    {
        $this->assertActorIsNotCallCenterAgent($actor, 'unassign');

        return $this->ccaAssignmentService->unassign(
            $order,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    public function claimFromPool(User $actor, Order $order): Order
    {
        if (! $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            throw ValidationException::withMessages([
                'order' => ['Only eligible call center agents can claim orders from the Order Pool.'],
            ]);
        }

        return $this->ccaAssignmentService->claimFromPool(
            $order,
            $actor,
            (int) $actor->company_id,
        );
    }

    /**
     * Owner bulk assign — unassigned/pool only; refuses silent CCA reassignment.
     *
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkAssignCca(User $actor, array $orderIds, int $ccaId, ?string $note = null): Collection
    {
        $this->assertActorIsNotCallCenterAgent($actor, 'assign');

        return $this->orderAssignmentService->assignOrders(
            $actor,
            $orderIds,
            $ccaId,
            $note,
        );
    }

    /**
     * Randomly assign a quantity of eligible unassigned company orders.
     *
     * @return array{
     *     orders: Collection<int, Order>,
     *     requested: int,
     *     assigned_count: int,
     *     available_count: int
     * }
     */
    public function randomAssignCca(
        User $actor,
        int $ccaId,
        int $quantity,
        ?string $note = null,
    ): array {
        $this->assertActorIsNotCallCenterAgent($actor, 'assign');

        return $this->orderAssignmentService->assignRandomQuantity(
            $actor,
            $ccaId,
            $quantity,
            $note,
        );
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
    public function randomAssignCcaAllocations(
        User $actor,
        array $allocations,
        ?string $note = null,
    ): array {
        $this->assertActorIsNotCallCenterAgent($actor, 'assign');

        return $this->orderAssignmentService->assignRandomQuantitiesByCca(
            $actor,
            $allocations,
            $note,
        );
    }

    /**
     * Owner bulk pool — currently unassigned orders only.
     *
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkMoveToPool(User $actor, array $orderIds): Collection
    {
        $this->assertActorIsNotCallCenterAgent($actor, 'pool');

        return $this->orderAssignmentService->sendToPool($actor, $orderIds);
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, Order>
     */
    public function bulkUnassign(User $actor, array $orderIds): Collection
    {
        $this->assertActorIsNotCallCenterAgent($actor, 'unassign');

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
        $this->assertCcaMayOperateOnOrder($actor, $order);

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

    public function banCustomer(User $actor, Order $order, string $reason): CustomerBan
    {
        $this->assertCcaMayOperateOnOrder($actor, $order);

        $order->loadMissing('customer');
        $customer = $order->customer;

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer' => ['This order has no linked customer to ban.'],
            ]);
        }

        return $this->customerBanService->ban(
            $customer,
            (int) $actor->id,
            (int) $actor->company_id,
            $reason,
        );
    }

    /**
     * Store a bank-transfer payment proof for later admin review.
     */
    public function submitBankTransfer(
        User $actor,
        Order $order,
        UploadedFile $slip,
        string $referenceNumber,
        float|string $amount,
        string $description,
    ): OrderPaymentSubmission {
        $this->assertCcaMayOperateOnOrder($actor, $order);

        if ($order->isCancelled()) {
            throw ValidationException::withMessages([
                'order' => ['Cancelled orders cannot accept bank transfer submissions.'],
            ]);
        }

        $path = $slip->store(
            'order-payment-slips/'.$order->uuid,
            'local'
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'payment_slip' => ['Unable to store the payment slip. Try again.'],
            ]);
        }

        return OrderPaymentSubmission::query()->create([
            'order_id' => (int) $order->id,
            'method' => OrderPaymentMethod::BANK_TRANSFER,
            'review_status' => OrderPaymentReviewStatus::PENDING_REVIEW,
            'reference_number' => trim($referenceNumber),
            'amount' => $amount,
            'description' => trim($description),
            'slip_path' => $path,
            'slip_original_name' => $slip->getClientOriginalName(),
            'submitted_by_user_id' => (int) $actor->id,
            'submitted_by_company_id' => (int) $actor->company_id,
            'submitted_at' => now(),
        ]);
    }

    public function updateDiscount(User $actor, Order $order, float|string $discountAmount): Order
    {
        $this->assertActorIsNotCallCenterAgent($actor, 'discount');

        return $this->discountService->apply(
            $order,
            $discountAmount,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    /**
     * Call Center Agents may not manage assignment or discount, even if a permission
     * override exists. Owners keep those capabilities.
     */
    private function assertActorIsNotCallCenterAgent(User $actor, string $action): void
    {
        if (! $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            return;
        }

        $message = match ($action) {
            'discount' => 'Call center agents cannot edit order discounts.',
            'assign' => 'Call center agents cannot assign orders to other agents.',
            'pool' => 'Call center agents cannot send orders to the Order Pool.',
            'unassign' => 'Call center agents cannot unassign orders.',
            default => 'Call center agents cannot perform this assignment action.',
        };

        throw ValidationException::withMessages([
            'order' => [$message],
        ]);
    }

    /**
     * CCA operational mutations are limited to orders currently assigned to them.
     * Owners remain free to operate on any company order without becoming the CCA.
     */
    public function assertCcaMayOperateOnOrder(User $actor, Order $order): void
    {
        if (! $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            return;
        }

        if ((int) $order->cca_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'order' => ['You can only update orders currently assigned to you.'],
        ]);
    }

    public function canEditOrderDetails(User $actor, Order $order): bool
    {
        if (! $actor->hasPermission('orders.update')) {
            return false;
        }

        if ($order->isCancelled()) {
            return false;
        }

        if ($order->confirmed_at !== null || $order->status === OrderStatus::CONFIRMED) {
            return false;
        }

        if ($this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            return (int) $order->cca_id === (int) $actor->id;
        }

        return true;
    }
}
