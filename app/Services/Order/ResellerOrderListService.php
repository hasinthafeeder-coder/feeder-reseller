<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderAssignmentState;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Enums\OrderPaymentMethod;
use Feeder\Core\Enums\OrderPaymentReviewStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Feeder\Core\Services\ResellerSupplierAssignmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResellerOrderListService
{
    public const FILTER_PENDING_APPROVAL = 'PENDING_APPROVAL';

    private const PER_PAGE = 25;

    public function __construct(
        private readonly ResellerSupplierAssignmentService $supplierAssignmentService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly OrderCcaAssignmentService $ccaAssignmentService,
    ) {}

    public function paginate(User $actor, Request $request): LengthAwarePaginator
    {
        return $this->filteredQuery($actor, $request)
            ->with([
                'supplier.company',
                'cca.profile',
                'market.currency',
                'items',
                'creator.role',
                'shipment.courier',
                'paymentSubmissions' => fn ($query) => $query->latest('id'),
                'ccaAssignments' => fn ($query) => $query->whereNull('unassigned_at')->latest('id'),
            ])
            ->latest('orders.id')
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Company-scoped assignment-state counts (independent of the current page).
     *
     * @return array{all: int, assigned: int, unassigned: int, pool: int, my: int}
     */
    public function assignmentCounts(User $actor): array
    {
        $base = $this->baseQuery($actor);

        return [
            'all' => (clone $base)->count(),
            'assigned' => (clone $base)->assignmentState(OrderAssignmentState::ASSIGNED)->count(),
            'unassigned' => (clone $base)->assignmentState(OrderAssignmentState::UNASSIGNED)->count(),
            'pool' => (clone $base)->assignmentState(OrderAssignmentState::POOL)->count(),
            'my' => (clone $base)
                ->where('cca_id', (int) $actor->id)
                ->count(),
        ];
    }

    /**
     * Status counts for the current list filters (tab / search / CCA / supplier / product / date),
     * excluding the selected status chip so every chip keeps a meaningful total.
     *
     * Keys match status filter option values (enum values) plus "all".
     *
     * @return array<string, int>
     */
    public function statusCounts(User $actor, Request $request): array
    {
        $rows = $this->filteredQuery($actor, $request, ignoreStatus: true)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = ['all' => 0];

        foreach (OrderStatus::cases() as $status) {
            $count = (int) ($rows[$status->value] ?? 0);
            $counts[$status->value] = $count;
            $counts['all'] += $count;
        }

        $counts[self::FILTER_PENDING_APPROVAL] = (clone $this->filteredQuery($actor, $request, ignoreStatus: true))
            ->whereHas('paymentSubmissions', function (Builder $query): void {
                $query->where('method', OrderPaymentMethod::BANK_TRANSFER)
                    ->where('review_status', OrderPaymentReviewStatus::PENDING_REVIEW);
            })
            ->count();

        return $counts;
    }

    /**
     * @return array{
     *     search: string,
     *     order_number: string,
     *     customer_name: string,
     *     customer_phone: string,
     *     status: string,
     *     supplier_id: string,
     *     product_id: string,
     *     date_from: string,
     *     date_to: string,
     *     date_preset: string,
     *     cca_id: string,
     *     source: string,
     *     assignment: string,
     *     tab: string
     * }
     */
    public function activeFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'order_number' => trim((string) $request->input('order_number', '')),
            'customer_name' => trim((string) $request->input('customer_name', '')),
            'customer_phone' => trim((string) $request->input('customer_phone', '')),
            'status' => trim((string) $request->input('status', '')),
            'supplier_id' => trim((string) $request->input('supplier_id', '')),
            'product_id' => trim((string) $request->input('product_id', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
            'date_preset' => trim((string) $request->input('date_preset', '')),
            'cca_id' => trim((string) $request->input('cca_id', '')),
            'source' => trim((string) $request->input('source', '')),
            'assignment' => trim((string) $request->input('assignment', '')),
            'tab' => trim((string) $request->input('tab', '')),
        ];
    }

    /**
     * Serialize paginator + meta for the finalized All Orders UI bootstrap.
     *
     * @return array<string, mixed>
     */
    public function bootstrap(User $actor, Request $request): array
    {
        $actor->loadMissing(['profile', 'company', 'role']);
        $isCca = $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id);
        $workspace = trim((string) $request->input('workspace', ''));
        $isCallCenterWorkspace = $workspace === 'call-center';

        // Call Center defaults: CCA lands on Assigned filtered to self; owners see All.
        if ($isCallCenterWorkspace && ! $request->filled('tab') && ! $request->filled('assignment')) {
            $request->merge([
                'tab' => $isCca ? 'assigned' : 'all',
            ]);
        }

        if ($isCallCenterWorkspace && $isCca && ! $request->exists('cca_id')) {
            $request->merge([
                'cca_id' => (string) $actor->id,
            ]);
        }

        $orders = $this->paginate($actor, $request);
        $filters = $this->activeFilters($request);
        $counts = $this->assignmentCounts($actor);
        $statusCounts = $this->statusCounts($actor, $request);
        $blocking = $isCca
            ? $this->ccaAssignmentService->blockingActivePoolClaim($actor, (int) $actor->company_id)
            : null;

        $name = trim(($actor->profile?->first_name ?? '').' '.($actor->profile?->last_name ?? ''));
        $canAssign = ! $isCca && $actor->hasPermission('orders.cca.assign');

        $indexParams = $isCallCenterWorkspace ? ['workspace' => 'call-center'] : [];

        return [
            'workspace' => $isCallCenterWorkspace ? 'call-center' : 'ongoing',
            'actor' => [
                'id' => (int) $actor->id,
                'role' => $isCca ? 'cca' : 'reseller',
                'name' => $name !== '' ? $name : ($actor->phone ?? 'User #'.$actor->id),
                'company' => $actor->company?->name ?? 'Company',
                'can_assign' => $canAssign,
                'can_claim' => $isCca && $actor->hasPermission('orders.update'),
                'can_create' => $actor->hasPermission('orders.create'),
                'can_update_status' => $actor->hasPermission('orders.status.update'),
                'can_update_discount' => ! $isCca && $actor->hasPermission('orders.discount.update'),
            ],
            'filters' => $filters,
            'selected_product' => $this->selectedProductFilter($actor, $filters['product_id']),
            'counts' => $counts,
            'status_counts' => $statusCounts,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'orders' => $orders->getCollection()
                ->map(fn (Order $order) => $this->serializeOrder($order, $actor, $isCallCenterWorkspace))
                ->values()
                ->all(),
            'ccas' => $this->ccaFilterOptions($actor)->all(),
            'suppliers' => $this->supplierFilterOptions($actor)->all(),
            'statuses' => $this->statusFilterOptions(),
            'sources' => $this->sourceFilterOptions(),
            'pool_lock' => $blocking === null ? null : [
                'order_id' => (int) $blocking->order_id,
                'order_uuid' => $blocking->order?->uuid,
                'order_number' => $blocking->order?->order_number,
            ],
            'routes' => [
                'index' => route('orders.index', $indexParams),
                'show' => url('/orders'),
                'create' => route('orders.create'),
                'bulk_assign' => route('orders.bulk.assign'),
                'bulk_assign_random' => route('orders.bulk.assign-random'),
                'bulk_pool' => route('orders.bulk.pool'),
                'bulk_unassign' => route('orders.bulk.unassign'),
                'claim' => url('/orders'),
                'filter_products' => route('orders.filter-products'),
            ],
        ];
    }

    /**
     * Search products the reseller (or CCA via company owner) is allowed to work with.
     * Reuses assigned-supplier + allowed-market visibility rules from the product catalog.
     *
     * @return Collection<int, array{id: int, name: string, supplier_name: string}>
     */
    public function searchFilterProducts(User $actor, ?string $search = null, int $limit = 25): Collection
    {
        $query = $this->accessibleProductsQuery($actor)->orderBy('name');

        $search = trim((string) $search);

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->with('supplier.company')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'supplier_name' => $product->supplier?->company?->name ?? 'Supplier #'.$product->supplier_id,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOrder(Order $order, User $actor, bool $forCallCenterWorkspace = false): array
    {
        $order->loadMissing([
            'supplier.company',
            'cca.profile',
            'market.currency',
            'items',
            'creator.role',
            'shipment.courier',
            'address',
            'ccaAssignments' => fn ($query) => $query->whereNull('unassigned_at')->latest('id'),
        ]);

        $activeAssignment = $order->ccaAssignments->first();
        $assignment = $order->assignmentState()->value;
        $fromPool = $activeAssignment?->origin?->value === OrderCcaAssignmentOrigin::POOL_CLAIM->value;
        $poolStatusChanged = false;

        if ($fromPool && $activeAssignment !== null) {
            $poolStatusChanged = $order->statusHistories()
                ->whereNotNull('from_status')
                ->where('created_at', '>=', $activeAssignment->assigned_at)
                ->exists();
        }

        $ccaName = null;
        if ($order->cca !== null) {
            $ccaName = trim(($order->cca->profile?->first_name ?? '').' '.($order->cca->profile?->last_name ?? ''));
            if ($ccaName === '') {
                $ccaName = $order->cca->phone ?? 'CCA #'.$order->cca_id;
            }
        }

        $createdByRole = null;
        if ($order->creator?->role?->slug === CallCenterAgentEligibilityService::ROLE_SLUG) {
            $createdByRole = 'cca';
        } elseif ($order->creator !== null) {
            $createdByRole = 'reseller';
        }

        $firstItem = $order->items->first();
        $itemName = $firstItem?->product_name_snapshot
            ?? $firstItem?->variant_name_snapshot
            ?? null;
        if ($itemName && $order->items->count() > 1) {
            $itemName .= ' +'.($order->items->count() - 1).' more';
        }

        $address = collect([
            $order->address?->line1,
            $order->address?->line2,
            $order->address?->city_name,
            $order->address?->district_name,
        ])->filter()->implode(', ');
        if ($address === '' && $order->address?->full_address_text) {
            $address = (string) $order->address->full_address_text;
        }

        $statusValue = strtolower($order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status);

        $pendingApproval = $order->relationLoaded('paymentSubmissions')
            ? $order->paymentSubmissions->contains(function ($submission): bool {
                $method = $submission->method instanceof OrderPaymentMethod
                    ? $submission->method
                    : OrderPaymentMethod::tryFrom((string) $submission->method);
                $review = $submission->review_status instanceof OrderPaymentReviewStatus
                    ? $submission->review_status
                    : OrderPaymentReviewStatus::tryFrom((string) $submission->review_status);

                return $method === OrderPaymentMethod::BANK_TRANSFER
                    && $review === OrderPaymentReviewStatus::PENDING_REVIEW;
            })
            : $order->hasPendingPaymentApproval();

        $statusLabel = $order->status instanceof OrderStatus
            ? $order->status->label()
            : (string) $order->status;

        if ($pendingApproval) {
            $statusLabel = 'Pending Approval';
            $statusValue = 'pending-approval';
        }

        return [
            'id' => (int) $order->id,
            'uuid' => (string) $order->uuid,
            'orderNumber' => (string) $order->order_number,
            'customerName' => (string) $order->customer_name_snapshot,
            'customerPhone' => (string) $order->primary_phone_snapshot,
            'secondaryPhone' => (string) ($order->secondary_phone_snapshot ?? ''),
            'address' => $address,
            'itemCount' => (int) $order->items->sum('quantity'),
            'itemCode' => (string) ($firstItem?->barcode_snapshot ?? ''),
            'itemName' => (string) ($itemName ?? ''),
            'amount' => (float) $order->customer_payable_amount,
            'currency' => (string) ($order->currency_code_snapshot ?: $order->market?->currency?->iso_code ?: ''),
            'source' => strtolower($order->source instanceof OrderSource
                ? $order->source->value
                : (string) $order->source),
            'createdByRole' => $createdByRole,
            'assignment' => $assignment,
            'ccaId' => $order->cca_id !== null ? (int) $order->cca_id : null,
            'ccaName' => $ccaName,
            'supplierId' => (int) $order->supplier_id,
            'supplierName' => $order->supplier?->company?->name ?? 'Supplier #'.$order->supplier_id,
            'status' => $statusValue,
            'statusLabel' => $statusLabel,
            'pendingApproval' => $pendingApproval,
            'operationalStatus' => strtolower($order->status instanceof OrderStatus
                ? $order->status->value
                : (string) $order->status),
            'fromPool' => $fromPool,
            'poolStatusChanged' => $poolStatusChanged,
            'createdAt' => optional($order->created_at)?->toIso8601String(),
            'courierName' => $order->shipment?->courier?->name,
            'trackingId' => $order->shipment?->tracking_number,
            'showUrl' => route('orders.show', $order),
            'claimUrl' => route('orders.cca.claim', $order),
            'isMine' => $order->cca_id !== null && (int) $order->cca_id === (int) $actor->id,
            'workspace' => $forCallCenterWorkspace ? 'call-center' : 'ongoing',
            'isGlobal' => false,
            // CCA may select/edit only orders assigned to them; owners may bulk-assign any company row.
            'isEditable' => $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)
                ? ($order->cca_id !== null && (int) $order->cca_id === (int) $actor->id)
                : true,
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function supplierFilterOptions(User $actor): Collection
    {
        $commercial = $this->resolveCommercialReseller($actor);

        return $this->supplierAssignmentService
            ->listAssignedSuppliers($commercial)
            ->map(fn (User $supplier) => [
                'id' => (int) $supplier->id,
                'name' => $supplier->company?->name ?? 'Supplier #'.$supplier->id,
            ])
            ->sortBy(fn (array $option) => mb_strtolower($option['name']))
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function ccaFilterOptions(User $actor): Collection
    {
        return User::query()
            ->where('company_id', $actor->company_id)
            ->whereHas('role', fn (Builder $query) => $query->where(
                'slug',
                CallCenterAgentEligibilityService::ROLE_SLUG
            ))
            ->with('profile')
            ->orderBy('id')
            ->get()
            ->map(function (User $cca) {
                $name = trim(($cca->profile?->first_name ?? '').' '.($cca->profile?->last_name ?? ''));

                return [
                    'id' => (int) $cca->id,
                    'name' => $name !== '' ? $name : ($cca->phone ?? 'CCA #'.$cca->id),
                ];
            })
            ->values();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusFilterOptions(): array
    {
        $options = array_map(
            static fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            OrderStatus::cases()
        );

        $options[] = [
            'value' => self::FILTER_PENDING_APPROVAL,
            'label' => 'Pending Approval',
        ];

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sourceFilterOptions(): array
    {
        return array_map(
            static fn (OrderSource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ],
            OrderSource::cases()
        );
    }

    public function findForCompany(User $actor, string $orderUuid): ?Order
    {
        return $this->baseQuery($actor)
            ->where('uuid', $orderUuid)
            ->with([
                'customer',
                'resellerCompany',
                'supplier.company',
                'cca.profile',
                'market.currency',
                'currency',
                'items.variant.product',
                'address',
                'draftCourier',
                'draftCourierService',
                'draftCourierCity',
                'creator.role',
                'statusHistories' => fn ($query) => $query->orderBy('id')->with('changedByUser.profile'),
                'paymentSubmissions' => fn ($query) => $query->latest('id'),
                'ccaAssignments' => fn ($query) => $query->orderBy('id')->with([
                    'cca.profile',
                    'assignedByUser.profile',
                ]),
                'comments' => fn ($query) => $query->orderBy('id')->with('authorUser.profile'),
                'shipment.courier',
                'shipment.service',
                'shipment.city',
                'shipment.events' => fn ($query) => $query->orderBy('id'),
            ])
            ->first();
    }

    private function baseQuery(User $actor): Builder
    {
        return Order::query()
            ->where('reseller_company_id', (int) $actor->company_id);
    }

    private function filteredQuery(User $actor, Request $request, bool $ignoreStatus = false): Builder
    {
        $query = $this->baseQuery($actor);
        $filters = $this->activeFilters($request);

        $this->applyTabFilter($query, $actor, $filters['tab']);
        $this->applyAssignmentFilter($query, $actor, $filters['assignment']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name_snapshot', 'like', '%'.$search.'%')
                    ->orWhere('primary_phone_snapshot', 'like', '%'.$search.'%')
                    ->orWhere('secondary_phone_snapshot', 'like', '%'.$search.'%');
            });
        }

        if ($filters['order_number'] !== '') {
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
        }

        if ($filters['customer_name'] !== '') {
            $query->where('customer_name_snapshot', 'like', '%'.$filters['customer_name'].'%');
        }

        if ($filters['customer_phone'] !== '') {
            $phone = $filters['customer_phone'];
            $query->where(function (Builder $inner) use ($phone): void {
                $inner->where('primary_phone_snapshot', 'like', '%'.$phone.'%')
                    ->orWhere('secondary_phone_snapshot', 'like', '%'.$phone.'%');
            });
        }

        if (! $ignoreStatus && $filters['status'] !== '') {
            $statusFilter = strtoupper(trim($filters['status']));

            if ($statusFilter === self::FILTER_PENDING_APPROVAL) {
                $query->whereHas('paymentSubmissions', function (Builder $inner): void {
                    $inner->where('method', OrderPaymentMethod::BANK_TRANSFER)
                        ->where('review_status', OrderPaymentReviewStatus::PENDING_REVIEW);
                });
            } else {
                $status = OrderStatus::tryFrom($filters['status'])
                    ?? OrderStatus::tryFrom($statusFilter);
                if ($status !== null) {
                    $query->where('status', $status->value);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($filters['supplier_id'] !== '') {
            $supplierId = (int) $filters['supplier_id'];
            $commercial = $this->resolveCommercialReseller($actor);
            $allowed = $this->supplierAssignmentService
                ->assignedSupplierIds($commercial)
                ->map(fn ($id) => (int) $id)
                ->all();

            if (in_array($supplierId, $allowed, true)) {
                $query->where('supplier_id', $supplierId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $this->applyProductFilter($query, $actor, $filters['product_id']);
        $this->applyDateFilters($query, $filters);

        // Pool / unassigned rows have no CCA; ignore the CCA filter on those tabs.
        $tabIgnoresCca = in_array($filters['tab'], ['pool', 'unassigned'], true)
            || in_array($filters['assignment'], ['pool', 'unassigned'], true);

        if (! $tabIgnoresCca && $filters['cca_id'] !== '') {
            $ccaId = (int) $filters['cca_id'];
            $belongsToCompany = User::query()
                ->whereKey($ccaId)
                ->where('company_id', $actor->company_id)
                ->exists();

            if ($belongsToCompany) {
                $query->where('cca_id', $ccaId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($filters['source'] !== '') {
            $source = OrderSource::tryFrom($filters['source'])
                ?? OrderSource::tryFrom(strtoupper($filters['source']));
            if ($source === null && strtolower($filters['source']) === 'manual') {
                $source = OrderSource::MANUAL;
            }
            if ($source === null && strtolower($filters['source']) === 'meta') {
                $source = OrderSource::META_IMPORT;
            }

            if ($source !== null) {
                $query->where('source', $source->value);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function applyTabFilter(Builder $query, User $actor, string $tab): void
    {
        if ($tab === '' || $tab === 'all' || $tab === 'company') {
            return;
        }

        if ($tab === 'my') {
            $query->where('cca_id', (int) $actor->id);

            return;
        }

        $state = OrderAssignmentState::tryFrom($tab);
        if ($state !== null) {
            $query->assignmentState($state);
        }
    }

    private function applyAssignmentFilter(Builder $query, User $actor, string $assignment): void
    {
        if ($assignment === '' || $assignment === 'all') {
            return;
        }

        if ($assignment === 'my') {
            $query->where('cca_id', (int) $actor->id);

            return;
        }

        $state = OrderAssignmentState::tryFrom($assignment);
        if ($state !== null) {
            $query->assignmentState($state);
        }
    }

    /**
     * Restrict to orders that contain at least one line for the selected product.
     * Unauthorized / inaccessible product ids yield an empty result set.
     */
    private function applyProductFilter(Builder $query, User $actor, string $productIdFilter): void
    {
        if ($productIdFilter === '') {
            return;
        }

        $productId = (int) $productIdFilter;

        if ($productId <= 0 || ! $this->isAccessibleProduct($actor, $productId)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('items', function (Builder $items) use ($productId): void {
            $items->where('product_id', $productId);
        });
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function selectedProductFilter(User $actor, string $productIdFilter): ?array
    {
        if ($productIdFilter === '') {
            return null;
        }

        $productId = (int) $productIdFilter;

        if ($productId <= 0) {
            return null;
        }

        $product = $this->accessibleProductsQuery($actor)
            ->whereKey($productId)
            ->first(['id', 'name']);

        if ($product === null) {
            return null;
        }

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
        ];
    }

    private function isAccessibleProduct(User $actor, int $productId): bool
    {
        return $this->accessibleProductsQuery($actor)
            ->whereKey($productId)
            ->exists();
    }

    /**
     * Products from assigned suppliers in the reseller company's allowed markets.
     */
    private function accessibleProductsQuery(User $actor): Builder
    {
        $commercial = $this->resolveCommercialReseller($actor);
        $allowedMarketIds = $this->allowedMarketIds($actor);
        $assignedSupplierIds = $this->supplierAssignmentService
            ->assignedSupplierIds($commercial)
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($assignedSupplierIds === [] || $allowedMarketIds === []) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::query()
            ->whereIn('supplier_id', $assignedSupplierIds)
            ->whereIn('market_id', $allowedMarketIds)
            ->where('status', ProductStatus::ACTIVE)
            ->where('system_visible', true);
    }

    /**
     * @return list<int>
     */
    private function allowedMarketIds(User $actor): array
    {
        $actor->loadMissing('company');
        $company = $actor->company;

        if ($company === null) {
            return [];
        }

        return $company->allowedMarkets()
            ->pluck('markets.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyDateFilters(Builder $query, array $filters): void
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $preset = $filters['date_preset'];

        if ($preset !== '' && $preset !== 'all' && $preset !== 'custom') {
            $today = now()->startOfDay();

            [$from, $to] = match ($preset) {
                'today' => [$today->toDateString(), $today->toDateString()],
                'yesterday' => [
                    $today->copy()->subDay()->toDateString(),
                    $today->copy()->subDay()->toDateString(),
                ],
                'last7' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
                'last30' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
                default => [$from, $to],
            };
        }

        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    private function resolveCommercialReseller(User $actor): User
    {
        $actor->loadMissing('company.owner');

        if ($this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            return $actor->company?->owner ?? $actor;
        }

        return $actor;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::PER_PAGE);

        return max(1, min(100, $perPage > 0 ? $perPage : self::PER_PAGE));
    }
}
