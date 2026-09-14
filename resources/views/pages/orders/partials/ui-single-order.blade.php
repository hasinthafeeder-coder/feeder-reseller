@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));
    extract(require resource_path('views/pages/orders/partials/ui-mock-data.php'));
@endphp

@php
    $screen = $orderUiScreen ?? 'view';
    $order = $orderUiMockSample;
    $isReadonly = in_array($screen, ['confirm', 'view', 'expired'], true);
    $isExpired = $screen === 'expired';
    $isExpiring = $screen === 'expiring';
    $isHold = $screen === 'hold';
    $isConfirm = $screen === 'confirm';

    $titleMap = [
        'confirm' => 'Confirm Order',
        'hold' => 'Hold Order',
        'expiring' => 'Expiring Order',
        'expired' => 'Expired Order',
        'view' => 'Order '.$order['orderNumber'],
        'edit' => 'Edit Order',
    ];
    $copyMap = [
        'confirm' => 'Review customer, products, courier, and payable amount before confirmation.',
        'hold' => 'This order is on hold. Reminder and notes are visual only on this pass.',
        'expiring' => 'This order is approaching expiration and can be reactivated from this screen.',
        'expired' => 'This order is expired and view-only.',
        'view' => 'Same Feeder single-order layout used for create, edit, view, and call-center handling.',
        'edit' => 'Same Feeder single-order form. Fields stay aligned with Create Order.',
    ];
@endphp

<div class="main-content-container overflow-hidden order-create-prototype {{ $isReadonly ? 'order-ui-readonly' : '' }}" id="orderUiSingle">
    @include('pages.orders.partials.ui-page-header', [
        'orderUiPageTitle' => $titleMap[$screen] ?? 'Order',
        'orderUiPageCopy' => $copyMap[$screen] ?? '',
        'orderUiCrumbs' => [
            ['label' => $isConfirm ? 'New Orders' : 'Orders', 'href' => $isConfirm ? $orderUiUrls['create'] : route('orders.index')],
            ['label' => $titleMap[$screen] ?? 'Order'],
        ],
        'orderUiSecondaryHref' => route('orders.index'),
        'orderUiSecondaryLabel' => 'Back to Orders',
    ])

    <div class="order-ui-banner">
        <strong>UI architecture preview</strong>
        This is the shared single-order layout. Preview another state without changing backend behaviour:
        <div class="order-ui-preview-switch">
            <a href="{{ $orderUiUrls['view'] }}" class="filter-chip {{ $screen === 'view' ? 'is-active' : '' }}">View</a>
            <a href="{{ $orderUiUrls['confirm'] }}" class="filter-chip {{ $isConfirm ? 'is-active' : '' }}">Confirm</a>
            <a href="{{ $orderUiUrls['hold'] }}" class="filter-chip {{ $isHold ? 'is-active' : '' }}">Hold</a>
            <a href="{{ $orderUiUrls['expiring'] }}" class="filter-chip {{ $isExpiring ? 'is-active' : '' }}">Expiring</a>
            <a href="{{ $orderUiUrls['expired'] }}" class="filter-chip {{ $isExpired ? 'is-active' : '' }}">Expired</a>
            <a href="{{ $orderUiUrls['create'] }}" class="filter-chip">Create (live)</a>
        </div>
    </div>

    @if ($isConfirm)
        <div class="order-ui-state-banner is-confirm">
            <strong>Confirmation step</strong>
            Courier, district, and city are required before booking. Success should become Confirmed. Failure should become Hold. Booking is not called from this UI pass.
        </div>
    @endif

    @if ($isHold)
        <div class="order-ui-state-banner is-hold">
            <strong>Hold</strong>
            Reminder {{ $order['holdReminder'] ?? '2026-09-16' }} · {{ $order['holdNote'] ?? 'Customer asked to call later.' }}
        </div>
    @endif

    @if ($isExpiring)
        <div class="order-ui-state-banner is-expiring">
            <strong>Expiring</strong>
            This order is approaching expiration. Reactivate it to continue call-center handling.
        </div>
    @endif

    @if ($isExpired)
        <div class="order-ui-state-banner is-expired">
            <strong>Expired</strong>
            This order is view-only. Fields, assignment, and confirmation actions are disabled.
        </div>
    @endif

    <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
        <span class="badge-status is-{{ $order['status'] }}">{{ $order['statusLabel'] }}</span>
        <span class="badge-source {{ $order['source'] === 'meta' ? 'is-meta' : 'is-manual' }}">
            {{ $order['source'] === 'meta' ? 'Meta Import' : 'Manual' }}
        </span>
        @if ($order['assignment'] === 'pool')
            <span class="badge-assign is-pool">● Order Pool</span>
        @elseif ($order['assignment'] === 'unassigned')
            <span class="badge-assign is-unassigned">● Unassigned</span>
        @else
            <span class="badge-assign is-other">● Assigned to CCA</span>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card bg-white rounded-10 border border-white order-create-section">
                <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="fs-18 mb-0">Customer</h4>
                    @unless ($isExpired)
                        <button type="button" class="btn btn-sm btn-outline-danger" data-open-order-ui-modal="banUserModal">
                            Ban this User
                        </button>
                    @endunless
                </div>
                <div class="p-20">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="label fs-14 mb-2" for="uiCustomerName">Customer name</label>
                            <input type="text" class="form-control" id="uiCustomerName" value="{{ $order['customerName'] }}" {{ $isReadonly ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="label fs-14 mb-2" for="uiPhone1">Phone 1</label>
                            <input type="text" class="form-control" id="uiPhone1" value="{{ $order['customerPhone'] }}" {{ $isReadonly ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="label fs-14 mb-2" for="uiPhone2">Phone 2</label>
                            <input type="text" class="form-control" id="uiPhone2" value="{{ $order['secondaryPhone'] }}" {{ $isReadonly ? 'readonly' : '' }}>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white rounded-10 border border-white order-create-section sidebar-info-card">
                <div class="p-20 border-bottom py-3">
                    <h4 class="fs-16 mb-0">Customer Order History</h4>
                </div>
                <div class="card-body-compact">
                    <div class="history-summary" aria-label="Order history summary">
                        <span class="history-summary-chip">Total <strong>3</strong></span>
                        <span class="history-summary-chip is-delivered">Delivered <strong>1</strong></span>
                        <span class="history-summary-chip is-returned">Returned <strong>1</strong></span>
                        <span class="history-summary-chip is-cancelled">Cancelled <strong>1</strong></span>
                    </div>
                    <div class="alert alert-warning mb-3" role="status">
                        <strong>CRIB risk:</strong> Medium (CR-04) — previous return on this phone number.
                    </div>
                    <div class="table-responsive">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Reseller</th>
                                    <th>Items</th>
                                    <th>Courier</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orderUiMockHistory as $history)
                                    <tr>
                                        <td>
                                            <div class="history-order-no">{{ $history['order_number'] }}</div>
                                            <div class="text-body" style="font-size:11px">{{ $history['date'] }}</div>
                                        </td>
                                        <td class="history-reseller">
                                            <div class="history-reseller-company">{{ $history['reseller_company'] }}</div>
                                            <div class="history-reseller-name">{{ $history['reseller_name'] }}</div>
                                        </td>
                                        <td class="history-items">
                                            <div class="history-items-name">{{ $history['items'] }}</div>
                                            <div class="history-items-amount">LKR {{ number_format($history['amount'], 2) }}</div>
                                        </td>
                                        <td class="history-courier">
                                            <div class="history-courier-name">{{ $history['courier_name'] }}</div>
                                            <div class="history-courier-tracking">{{ $history['tracking_number'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-status {{ $history['status'] === 'Delivered' ? 'bg-success-subtle text-success' : ($history['status'] === 'Returned' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }}">
                                                {{ $history['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card bg-white rounded-10 border border-white order-create-section">
                <div class="p-20 border-bottom">
                    <h4 class="fs-18 mb-0">Delivery</h4>
                </div>
                <div class="p-20">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="label fs-14 mb-2" for="uiAddress">Address</label>
                            <input type="text" class="form-control" id="uiAddress" value="{{ $order['address'] }}" {{ $isReadonly ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-4">
                            <label class="label fs-14 mb-2" for="uiCourier">Courier</label>
                            <select class="form-select form-control" id="uiCourier" {{ $isExpired ? 'disabled' : '' }}>
                                <option value="">Select courier</option>
                                @foreach ($orderUiMockCouriers as $courier)
                                    <option value="{{ $courier['id'] }}" @selected(($order['courierName'] ?? '') === $courier['name'] || ($isConfirm && $courier['id'] === 'courier-1'))>
                                        {{ $courier['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="label fs-14 mb-2" for="uiDistrict">District</label>
                            <select class="form-select form-control" id="uiDistrict" {{ $isExpired ? 'disabled' : '' }}>
                                <option value="{{ $order['district'] }}" selected>{{ $order['district'] }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="label fs-14 mb-2" for="uiCity">City</label>
                            <select class="form-select form-control" id="uiCity" {{ $isExpired ? 'disabled' : '' }}>
                                <option value="{{ $order['city'] }}" selected>{{ $order['city'] }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white rounded-10 border border-white order-create-section">
                <div class="p-20 border-bottom">
                    <h4 class="fs-18 mb-0">Order items</h4>
                </div>
                <div class="p-20">
                    <div class="order-line-row">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md">
                                <label class="line-field-label">Product</label>
                                <input type="text" class="form-control line-control-height" value="{{ explode(' / ', $order['itemName'])[0] }}" {{ $isReadonly ? 'readonly' : '' }}>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="line-field-label">Variant</label>
                                <input type="text" class="form-control line-control-height" value="{{ explode(' / ', $order['itemName'])[1] ?? 'Default' }}" {{ $isReadonly ? 'readonly' : '' }}>
                            </div>
                            <div class="col-3 col-md-2 col-lg-1">
                                <label class="line-field-label">Qty</label>
                                <input type="number" class="form-control line-control-height" value="{{ $order['itemCount'] }}" {{ $isReadonly ? 'readonly' : '' }}>
                            </div>
                        </div>
                        <div class="line-meta">
                            <span class="line-meta-item">Item code: <strong>{{ $order['itemCode'] }}</strong></span>
                            <span class="line-meta-item">Unit price: <strong>LKR {{ number_format($order['subtotal'] / max(1, $order['itemCount']), 2) }}</strong></span>
                            <span class="line-meta-item">Line total: <strong>LKR {{ number_format($order['subtotal'], 2) }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($isHold)
                <div class="card bg-white rounded-10 border border-white order-create-section">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Hold reminder</h4>
                    </div>
                    <div class="p-20">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="label fs-14 mb-2" for="uiHoldDate">Reminder date</label>
                                <input type="date" class="form-control" id="uiHoldDate" value="{{ $order['holdReminder'] ?? '2026-09-16' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="label fs-14 mb-2" for="uiHoldInfo">Reminder information</label>
                                <input type="text" class="form-control" id="uiHoldInfo" value="Call after 6:00 PM">
                            </div>
                            <div class="col-12">
                                <label class="label fs-14 mb-2" for="uiHoldNote">Add note</label>
                                <input type="text" class="form-control" id="uiHoldNote" value="{{ $order['holdNote'] ?? '' }}" placeholder="Add a hold note for the timeline">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card bg-white rounded-10 border border-white order-create-section sidebar-info-card">
                <div class="p-20 border-bottom py-3">
                    <h4 class="fs-16 mb-0">Order Timeline</h4>
                </div>
                <div class="card-body-compact">
                    <ul class="order-timeline">
                        @foreach ($orderUiMockTimeline as $event)
                            <li class="order-timeline-item">
                                <span class="order-timeline-dot type-{{ $event['type'] }}"></span>
                                <div class="order-timeline-time">{{ $event['at'] }} · {{ $event['user'] }}</div>
                                <div class="order-timeline-title">{{ $event['title'] }}</div>
                                <p class="order-timeline-desc">{{ $event['description'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="order-summary-sticky">
                <div class="card bg-white rounded-10 border border-white">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Order Summary</h4>
                    </div>
                    <div class="p-20">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Items subtotal</span>
                            <span class="fw-medium">LKR {{ number_format($order['subtotal'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Discount</span>
                            <span class="fw-medium">LKR {{ number_format($order['discount'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Courier fee</span>
                            <span class="fw-medium">LKR {{ number_format($order['courierFee'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pt-2 border-top">
                            <span class="fw-semibold">Customer payable</span>
                            <span class="fw-semibold">LKR {{ number_format($order['amount'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-body">Reseller net preview</span>
                            <span class="fw-medium">LKR {{ number_format(max(0, $order['subtotal'] - $order['discount']), 2) }}</span>
                        </div>

                        <div class="order-summary-review" aria-label="Order review information">
                            <p class="fs-13 text-body mb-3">Order review information</p>
                            <div class="order-summary-review-block">
                                <div class="order-summary-review-label">Customer</div>
                                <p class="order-summary-review-value">{{ $order['customerName'] }}
{{ $order['customerPhone'] }}
{{ $order['secondaryPhone'] !== '' ? $order['secondaryPhone'] : 'No secondary phone' }}</p>
                            </div>
                            <div class="order-summary-review-block">
                                <div class="order-summary-review-label">Address</div>
                                <p class="order-summary-review-value">{{ $order['address'] }}</p>
                            </div>
                            <div class="order-summary-review-block">
                                <div class="order-summary-review-label">Courier</div>
                                <p class="order-summary-review-value">{{ $order['courierName'] ?: 'Pronto Lanka' }}
{{ $order['district'] }}, {{ $order['city'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white rounded-10 border border-white mt-3">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Payment</h4>
                    </div>
                    <div class="p-20">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="uiPaymentMethod" id="uiPayCod" value="cod" checked {{ $isExpired ? 'disabled' : '' }}>
                            <label class="form-check-label fs-14" for="uiPayCod">Cash on delivery</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="uiPaymentMethod" id="uiPayBank" value="bank" {{ $isExpired ? 'disabled' : '' }}>
                            <label class="form-check-label fs-14" for="uiPayBank">Bank transfer</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="uiPaymentMethod" id="uiPayGateway" value="gateway" {{ $isExpired ? 'disabled' : '' }}>
                            <label class="form-check-label fs-14" for="uiPayGateway">Online gateway</label>
                        </div>
                        <p class="fs-13 text-body mb-0 mt-3">Payment methods are visual only. No payment is processed from this screen.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="order-create-actions">
        <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
            @if ($isExpired)
                <button type="button" class="btn btn-light border" disabled>View only</button>
            @elseif ($isExpiring)
                <button type="button" class="btn btn-light border" data-order-ui-toast="Reactivation is visual only on this pass.">Keep expired path</button>
                <button type="button" class="btn btn-primary text-white" data-order-ui-toast="Reactivate is visual only. No status job runs from this screen.">Reactivate order</button>
            @elseif ($isHold)
                <button type="button" class="btn btn-light border" data-order-ui-toast="Hold note would be added to the timeline later.">Save hold note</button>
                <button type="button" class="btn btn-primary text-white" data-open-order-ui-modal="callCenterAssignModal">Send to Call Center</button>
            @elseif ($isConfirm)
                <button type="button" class="btn btn-light border" data-open-order-ui-modal="callCenterAssignModal">Send to Call Center</button>
                <button type="button" class="btn order-ui-confirm-action" id="orderUiConfirmBookBtn">Confirm &amp; book courier</button>
            @else
                <button type="button" class="btn btn-light border" data-open-order-ui-modal="callCenterAssignModal">Send to Call Center</button>
                <a href="{{ $orderUiUrls['confirm'] }}" class="btn btn-primary text-white">Continue to confirm</a>
            @endif
        </div>
    </div>
</div>

@include('pages.orders.partials.ui-ban-modal', [
    'banName' => $order['customerName'],
    'banPhone1' => $order['customerPhone'],
    'banPhone2' => $order['secondaryPhone'],
])
@include('pages.orders.partials.ui-call-center-modal')

<div id="orderUiToast" class="alert alert-success prototype-toast hidden" role="status"></div>
