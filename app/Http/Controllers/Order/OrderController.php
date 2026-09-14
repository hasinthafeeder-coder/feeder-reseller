<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AssignOrderCcaRequest;
use App\Http\Requests\Order\BookOrderShipmentRequest;
use App\Http\Requests\Order\BulkAssignOrderCcaRequest;
use App\Http\Requests\Order\BulkOrderIdsRequest;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreManualOrderRequest;
use App\Http\Requests\Order\StoreOrderCommentRequest;
use App\Http\Requests\Order\UpdateOrderDiscountRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Services\Order\ResellerManualOrderService;
use App\Services\Order\ResellerOrderCatalogService;
use App\Services\Order\ResellerOrderListService;
use App\Services\Order\ResellerOrderWorkflowService;
use App\Services\Order\ResellerShipmentWorkflowService;
use Feeder\Core\Exceptions\DuplicateOrderWarningException;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly ResellerOrderListService $orderListService,
        private readonly ResellerOrderCatalogService $orderCatalogService,
        private readonly ResellerManualOrderService $manualOrderService,
        private readonly ResellerOrderWorkflowService $orderWorkflowService,
        private readonly ResellerShipmentWorkflowService $shipmentWorkflowService,
    ) {}

    public function index(IndexOrderRequest $request): View|JsonResponse
    {
        $actor = Auth::user();
        $bootstrap = $this->orderListService->bootstrap($actor, $request);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['data' => $bootstrap]);
        }

        return view('pages.orders.index', [
            'bootstrap' => $bootstrap,
        ]);
    }

    public function create(): View
    {
        $actor = Auth::user();

        return view('pages.orders.create', [
            'markets' => $this->orderCatalogService->marketsForReseller($actor),
            'eligibleCcas' => $this->orderCatalogService->eligibleCcasForCompany($actor),
            'isCca' => app(CallCenterAgentEligibilityService::class)
                ->isEligible($actor, (int) $actor->company_id),
            'duplicateOrders' => session('duplicate_orders', []),
            'oldItems' => $this->enrichOldItems(old('items', [])),
            'catalogRoutes' => [
                'suppliers' => route('orders.catalog.suppliers'),
                'products' => route('orders.catalog.products'),
                'variants' => route('orders.catalog.variants'),
                'afterHours' => route('orders.catalog.after-hours'),
                'customerLookup' => route('orders.catalog.customer-lookup'),
                'duplicates' => route('orders.catalog.duplicates'),
                'couriers' => route('orders.catalog.couriers'),
                'courierDistricts' => route('orders.catalog.courier-districts'),
                'courierCities' => route('orders.catalog.courier-cities'),
                'courierFeePreview' => route('orders.catalog.courier-fee-preview'),
                'store' => route('orders.store'),
            ],
        ]);
    }

    /**
     * Restore product_id for previously submitted variant lines after validation redirects.
     *
     * @param  mixed  $items
     * @return list<array{product_id: ?int, product_variant_id: int, quantity: int, selected_selling_price?: float|null}>
     */
    private function enrichOldItems(mixed $items): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $productIdsByVariant = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->pluck('product_id', 'id');

        $enriched = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['product_variant_id'])) {
                continue;
            }

            $variantId = (int) $item['product_variant_id'];

            $row = [
                'product_id' => isset($productIdsByVariant[$variantId])
                    ? (int) $productIdsByVariant[$variantId]
                    : null,
                'product_variant_id' => $variantId,
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];

            if (
                array_key_exists('selected_selling_price', $item)
                && $item['selected_selling_price'] !== null
                && $item['selected_selling_price'] !== ''
            ) {
                $row['selected_selling_price'] = round((float) $item['selected_selling_price'], 2);
            }

            $enriched[] = $row;
        }

        return $enriched;
    }

    public function store(StoreManualOrderRequest $request): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $order = $this->manualOrderService->create($actor, $request->orderPayload());
        } catch (DuplicateOrderWarningException $e) {
            return redirect()
                ->route('orders.create')
                ->withInput($request->except(['duplicate_warning_overridden']))
                ->withErrors($e->errors())
                ->with('duplicate_orders', $this->manualOrderService->serializeDuplicates($e))
                ->with('warning', 'Potential duplicate orders were found. Review them and confirm Continue to create this order.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.create')
                ->withInput($request->all())
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order '.$order->order_number.' created successfully.');
    }

    public function show(string $order): View
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);
        $canBookShipment = $actor?->hasPermission('orders.shipment.book') === true;
        $eligibleCouriers = ($canBookShipment && $found->shipment === null)
            ? $this->shipmentWorkflowService->couriers($actor, $found)
            : [];

        return view('pages.orders.show', [
            'order' => $found,
            'statusOptions' => $this->orderWorkflowService->statusOptions(),
            'reactivationStatusOptions' => $this->orderWorkflowService->reactivationStatusOptions(),
            'commentContextOptions' => $this->orderWorkflowService->commentContextOptions(),
            'eligibleCcas' => $this->orderWorkflowService->eligibleCcas($actor, $found),
            'canReactivate' => $this->orderWorkflowService->canReactivate($found),
            'canBookShipment' => $canBookShipment,
            'eligibleCouriers' => $eligibleCouriers,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $order): RedirectResponse
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $this->orderWorkflowService->updateStatus(
                $actor,
                $found,
                $request->validated('status'),
                $request->validated('reason'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $found)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $found)
            ->with('success', 'Order status updated.');
    }

    public function assignCca(AssignOrderCcaRequest $request, string $order): RedirectResponse
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $this->orderWorkflowService->assignCca(
                $actor,
                $found,
                (int) $request->validated('cca_id'),
                $request->validated('note'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $found)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $found)
            ->with('success', 'Call center agent assigned.');
    }

    public function claimFromPool(string $order): RedirectResponse|JsonResponse
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $updated = $this->orderWorkflowService->claimFromPool($actor, $found);
        } catch (ValidationException $e) {
            if (request()->wantsJson()) {
                return response()->json(['message' => 'Unable to claim order.', 'errors' => $e->errors()], 422);
            }

            return redirect()
                ->route('orders.index')
                ->withErrors($e->errors());
        }

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Order claimed from the Order Pool.',
                'data' => $this->orderListService->serializeOrder($updated, $actor),
            ]);
        }

        return redirect()
            ->route('orders.show', $updated)
            ->with('success', 'Order claimed from the Order Pool.');
    }

    public function bulkAssignCca(BulkAssignOrderCcaRequest $request): RedirectResponse|JsonResponse
    {
        $actor = Auth::user();

        try {
            $orders = $this->orderWorkflowService->bulkAssignCca(
                $actor,
                $request->validated('order_ids'),
                (int) $request->validated('cca_id'),
                $request->validated('note'),
            );
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Bulk assign failed.', 'errors' => $e->errors()], 422);
            }

            return redirect()->route('orders.index')->withInput()->withErrors($e->errors());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Orders assigned.',
                'data' => $orders->map(fn ($order) => $this->orderListService->serializeOrder($order, $actor))->values(),
            ]);
        }

        return redirect()
            ->route('orders.index')
            ->with('success', $orders->count().' order(s) assigned.');
    }

    public function bulkMoveToPool(BulkOrderIdsRequest $request): RedirectResponse|JsonResponse
    {
        $actor = Auth::user();

        try {
            $orders = $this->orderWorkflowService->bulkMoveToPool(
                $actor,
                $request->validated('order_ids'),
            );
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Move to pool failed.', 'errors' => $e->errors()], 422);
            }

            return redirect()->route('orders.index')->withInput()->withErrors($e->errors());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Orders moved to the Order Pool.',
                'data' => $orders->map(fn ($order) => $this->orderListService->serializeOrder($order, $actor))->values(),
            ]);
        }

        return redirect()
            ->route('orders.index')
            ->with('success', $orders->count().' order(s) moved to the Order Pool.');
    }

    public function bulkUnassign(BulkOrderIdsRequest $request): RedirectResponse|JsonResponse
    {
        $actor = Auth::user();

        try {
            $orders = $this->orderWorkflowService->bulkUnassign(
                $actor,
                $request->validated('order_ids'),
            );
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unassign failed.', 'errors' => $e->errors()], 422);
            }

            return redirect()->route('orders.index')->withInput()->withErrors($e->errors());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Orders moved to Unassigned.',
                'data' => $orders->map(fn ($order) => $this->orderListService->serializeOrder($order, $actor))->values(),
            ]);
        }

        return redirect()
            ->route('orders.index')
            ->with('success', $orders->count().' order(s) unassigned.');
    }

    public function storeComment(StoreOrderCommentRequest $request, string $order): RedirectResponse
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $this->orderWorkflowService->addComment(
                $actor,
                $found,
                $request->validated('body'),
                $request->validated('context_type'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $found)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $found)
            ->with('success', 'Comment added.');
    }

    public function updateDiscount(UpdateOrderDiscountRequest $request, string $order): RedirectResponse
    {
        $actor = Auth::user();
        $found = $this->orderWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $this->orderWorkflowService->updateDiscount(
                $actor,
                $found,
                $request->validated('discount_amount'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $found)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $found)
            ->with('success', 'Discount updated.');
    }

    public function couriers(string $order): JsonResponse
    {
        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        return response()->json([
            'data' => $this->shipmentWorkflowService->couriers($actor, $found),
        ]);
    }

    public function courierServices(Request $request, string $order): JsonResponse
    {
        $request->validate([
            'courier_id' => ['required', 'integer', 'min:1'],
        ]);

        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $services = $this->shipmentWorkflowService->services(
                $actor,
                $found,
                (int) $request->integer('courier_id'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $services]);
    }

    public function courierDistricts(Request $request, string $order): JsonResponse
    {
        $request->validate([
            'courier_service_id' => ['required', 'integer', 'min:1'],
        ]);

        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $districts = $this->shipmentWorkflowService->districts(
                $actor,
                $found,
                (int) $request->integer('courier_service_id'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $districts]);
    }

    public function courierCities(Request $request, string $order): JsonResponse
    {
        $request->validate([
            'courier_service_id' => ['required', 'integer', 'min:1'],
            'district' => ['required', 'string', 'max:255'],
        ]);

        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $cities = $this->shipmentWorkflowService->cities(
                $actor,
                $found,
                (int) $request->integer('courier_service_id'),
                (string) $request->input('district'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $cities]);
    }

    public function courierFeePreview(Request $request, string $order): JsonResponse
    {
        $request->validate([
            'courier_id' => ['required', 'integer', 'min:1'],
        ]);

        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $preview = $this->shipmentWorkflowService->feePreview(
                $actor,
                $found,
                (int) $request->integer('courier_id'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $preview]);
    }

    public function bookShipment(BookOrderShipmentRequest $request, string $order): RedirectResponse
    {
        $actor = Auth::user();
        $found = $this->shipmentWorkflowService->findOrFailForCompany($actor, $order);

        try {
            $this->shipmentWorkflowService->book(
                $actor,
                $found,
                (int) $request->validated('courier_id'),
                (int) $request->validated('courier_service_id'),
                (int) $request->validated('courier_city_id'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $found)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('orders.show', $found)
            ->with('success', 'Shipment booked successfully.');
    }

    public function suppliers(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
        ]);

        $suppliers = $this->orderCatalogService->suppliersForMarket(
            Auth::user(),
            (int) $request->integer('market_id'),
        );

        return response()->json(['data' => $suppliers]);
    }

    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $products = $this->orderCatalogService->productsForSupplier(
            Auth::user(),
            (int) $request->integer('market_id'),
            (int) $request->integer('supplier_id'),
            $request->input('search'),
        );

        return response()->json(['data' => $products]);
    }

    public function variants(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
        ]);

        $variants = $this->orderCatalogService->variantsForProduct(
            Auth::user(),
            (int) $request->integer('market_id'),
            (int) $request->integer('supplier_id'),
            (int) $request->integer('product_id'),
        );

        return response()->json(['data' => $variants]);
    }

    public function afterHours(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
        ]);

        $result = $this->orderCatalogService->afterHoursForMarket(
            Auth::user(),
            (int) $request->integer('market_id'),
        );

        return response()->json(['data' => $result]);
    }

    public function customerLookup(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'country_id' => ['required', 'integer'],
            'market_id' => ['nullable', 'integer'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $this->orderCatalogService->lookupCustomerByPhone(
            Auth::user(),
            (string) $request->input('phone'),
            (int) $request->integer('country_id'),
            $request->filled('market_id') ? (int) $request->integer('market_id') : null,
            $request->input('secondary_phone'),
        );

        return response()->json(['data' => $result]);
    }

    public function duplicatesPreview(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'country_id' => ['required', 'integer'],
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['integer'],
        ]);

        $result = $this->orderCatalogService->previewDuplicates(
            Auth::user(),
            (string) $request->input('phone'),
            (int) $request->integer('country_id'),
            $request->input('variant_ids', []),
        );

        return response()->json(['data' => $result]);
    }

    public function catalogCouriers(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
        ]);

        try {
            $couriers = $this->orderCatalogService->couriersForCreate(
                Auth::user(),
                (int) $request->integer('market_id'),
                (int) $request->integer('supplier_id'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $couriers]);
    }

    public function catalogCourierDistricts(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'courier_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $districts = $this->orderCatalogService->courierDistrictsForCreate(
                Auth::user(),
                (int) $request->integer('market_id'),
                (int) $request->integer('supplier_id'),
                (int) $request->integer('courier_id'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $districts]);
    }

    public function catalogCourierCities(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'courier_id' => ['required', 'integer', 'min:1'],
            'district' => ['required', 'string', 'max:255'],
        ]);

        try {
            $cities = $this->orderCatalogService->courierCitiesForCreate(
                Auth::user(),
                (int) $request->integer('market_id'),
                (int) $request->integer('supplier_id'),
                (int) $request->integer('courier_id'),
                (string) $request->input('district'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $cities]);
    }

    public function catalogCourierFeePreview(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'courier_id' => ['required', 'integer', 'min:1'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'items_subtotal' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $preview = $this->orderCatalogService->courierFeePreviewForCreate(
                Auth::user(),
                (int) $request->integer('market_id'),
                (int) $request->integer('supplier_id'),
                (int) $request->integer('courier_id'),
                (float) $request->input('total_weight', 0),
                (float) $request->input('items_subtotal', 0),
                (float) $request->input('discount_amount', 0),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $preview]);
    }
}
