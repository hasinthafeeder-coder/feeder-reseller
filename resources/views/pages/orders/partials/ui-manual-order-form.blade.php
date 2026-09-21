        @php
    $orderFormMode = $orderFormMode ?? 'create';
    $isEditMode = $orderFormMode === 'edit';
    $formDefaults = $formDefaults ?? [];
    $fd = static function (string $key, $fallback = '') use ($formDefaults) {
        return old($key, $formDefaults[$key] ?? $fallback);
    };
    $formAction = $formAction ?? ($isEditMode
        ? ($catalogRoutes['update'] ?? '#')
        : ($catalogRoutes['store'] ?? '#'));
@endphp
        <form method="POST" action="{{ $formAction }}" id="manualOrderForm" data-order-form-mode="{{ $orderFormMode }}" novalidate>
            @csrf
            <input type="hidden" name="intent" id="orderIntent" value="">
            <input type="hidden" name="assignment_target" id="assignmentTarget" value="">
            <input type="hidden" name="assign_cca_id" id="assignCcaId" value="">
            <input type="hidden" name="duplicate_warning_overridden" id="duplicateWarningOverridden" value="0">
            <input type="hidden" name="after_hours_warning_shown" id="afterHoursWarningShown" value="0">
            <input type="hidden" name="supplier_id" id="supplierId" value="{{ $fd('supplier_id', '') }}">
            <div class="row g-4">
                <div class="col-lg-8">
                    {{-- Customer Information --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section" data-section="customer">
                        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="fs-18 mb-0">Customer</h4>
                            @if ($canBanCustomer ?? auth()->user()?->hasPermission('customers.bans.create'))
                                <button type="button" class="btn btn-sm btn-outline-danger" id="openBanUserBtn">
                                    Ban this Customer
                                </button>
                            @endif
                        </div>
                        <div class="p-20">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="label fs-14 mb-2" for="customerName">Customer name</label>
                                    <input type="text" class="form-control" id="customerName" name="customer_name"
                                        value="{{ $fd('customer_name', '') }}" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="label fs-14 mb-2" for="primaryPhone">Phone 1</label>
                                    <input type="text" class="form-control" id="primaryPhone" name="primary_phone"
                                        value="{{ $fd('primary_phone', '') }}" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="label fs-14 mb-2" for="secondaryPhone">Phone 2</label>
                                    <input type="text" class="form-control" id="secondaryPhone" name="secondary_phone"
                                        value="{{ $fd('secondary_phone', '') }}" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <div id="customerRiskPanel" class="customer-banned-warning hidden" role="alert" aria-live="polite"></div>
                                    <div id="customerExtraPanel" class="hidden" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($isEditMode && isset($order))
                        @include('pages.orders.partials.ui-order-status-actions', [
                            'showStatusModal' => false,
                        ])
                    @endif

                    {{-- Customer Order History --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section sidebar-info-card" data-section="history">
                        <div class="p-20 border-bottom py-3">
                            <h4 class="fs-16 mb-0">Customer Order History</h4>
                        </div>
                        <div class="card-body-compact" id="customerHistoryPanel">
                            <p class="sidebar-empty">No previous orders found for this customer.</p>
                        </div>
                    </div>

                    {{-- Delivery: address + courier in one container --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section" data-section="delivery">
                        <div class="p-20 border-bottom">
                            <h4 class="fs-18 mb-0">Delivery</h4>
                        </div>
                        <div class="p-20">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="label fs-14 mb-2" for="addressLine1">Address</label>
                                    <input type="text" class="form-control" id="addressLine1" name="address_line1"
                                        value="{{ old('address_line1', $fd('address_line1', '')) }}" autocomplete="off">
                                    <input type="hidden" name="address_line2" value="">
                                    <input type="hidden" name="full_address_text" id="fullAddressText" value="{{ old('full_address_text', $fd('full_address_text', $fd('address_line1', ''))) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierId">Courier service</label>
                                    <select class="form-select form-control" id="courierId" name="courier_id">
                                        <option value="">Optional — select courier</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierDistrict">District</label>
                                    <select class="form-select form-control" id="courierDistrict" disabled>
                                        <option value="">Select district</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierCity">City</label>
                                    <select class="form-select form-control" id="courierCity" disabled>
                                        <option value="">Select city</option>
                                    </select>
                                </div>
                                <input type="hidden" name="district_name" id="districtNameValue" value="{{ $fd('district_name', '') }}">
                                <input type="hidden" name="city_name" id="cityNameValue" value="{{ $fd('city_name', '') }}">
                                <input type="hidden" name="courier_city_id" id="courierCityIdValue" value="{{ $fd('courier_city_id', '') }}">
                                @if ($canAssignCourier ?? false)
                                    <div class="col-12">
                                        <div id="assignedCourierPanel" class="alert alert-success mb-0 {{ empty($shipmentBootstrap['waybill'] ?? null) ? 'hidden' : '' }}" role="status">
                                            <div class="fw-medium" id="assignedCourierName">{{ $shipmentBootstrap['courier']['name'] ?? '' }}</div>
                                            <div class="fs-13" id="assignedCourierWaybill">{{ ($shipmentBootstrap['waybill'] ?? '') !== '' ? 'Waybill: '.$shipmentBootstrap['waybill'] : '' }}</div>
                                        </div>
                                        <div id="assignCourierError" class="text-danger fs-13 mb-2 hidden" role="alert"></div>
                                        <div id="assignCourierDebug" class="alert alert-danger mb-2 hidden" role="alert">
                                            <div class="fw-medium mb-1" id="assignCourierDebugTitle"></div>
                                            <div class="fs-13 mb-2" id="assignCourierDebugMeta"></div>
                                            <div class="fs-13 fw-medium">Provider Response</div>
                                            <pre id="assignCourierDebugResponse" class="assign-courier-debug-pre"></pre>
                                            <div class="fs-13 fw-medium mt-2">Request Payload</div>
                                            <pre id="assignCourierDebugRequest" class="assign-courier-debug-pre"></pre>
                                        </div>
                                        <button type="button" class="btn btn-primary text-white" id="assignCourierBtn" disabled>
                                            Assign Courier
                                        </button>
                                        <p class="fs-13 text-body mb-0 mt-2" id="assignCourierHint">
                                            Select courier, state/district, and city, then assign to create the courier shipment.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Product / Order items (market tick boxes at top) --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section" data-section="items">
                        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="fs-18 mb-0">Order items</h4>
                            <button type="button" class="btn btn-sm btn-primary text-white" id="addLineBtn">
                                Add item
                            </button>
                        </div>
                        <div class="p-20">
                            <div class="mb-3">
                                <label class="label fs-14 mb-2 d-block">Market</label>
                                <div class="market-checks" id="marketChecks" role="group" aria-label="Market selection"></div>
                                <input type="hidden" name="market_id" id="marketId" value="{{ $fd('market_id', '') }}">
                            </div>

                            <div id="afterHoursPanel" class="alert alert-warning mb-3 hidden" role="alert"></div>

                            <div id="orderLines"></div>
                        </div>
                    </div>

                    {{-- Order Timeline --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section sidebar-info-card" data-section="timeline">
                        <div class="p-20 border-bottom py-3">
                            <h4 class="fs-16 mb-0">Order Timeline</h4>
                        </div>
                        <div class="card-body-compact">
                            <ul class="order-timeline" id="orderTimelineList" aria-live="polite"></ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="order-summary-sticky">
                        {{-- Order Summary --}}
                        <div class="card bg-white rounded-10 border border-white" data-section="summary">
                            <div class="p-20 border-bottom">
                                <h4 class="fs-18 mb-0">Order Summary</h4>
                            </div>
                            <div class="p-20">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-body">Items subtotal</span>
                                    <span class="fw-medium" id="itemsSubtotalLabel">LKR 0.00</span>
                                </div>
                                <div class="mb-3">
                                    <label class="label fs-14 mb-2" for="discountAmount">Discount</label>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                        id="discountAmount" name="discount_amount" value="{{ $fd('discount_amount', '') }}">
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-body">Courier fee</span>
                                    <span class="fw-medium" id="courierFeeLabel">LKR 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 pt-2 border-top">
                                    <span class="fw-semibold">Customer payable</span>
                                    <span class="fw-semibold" id="customerPayableLabel">LKR 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-0">
                                    <span class="text-body">Total weight</span>
                                    <span class="fw-medium" id="totalWeightLabel">0.000 kg</span>
                                </div>

                                <div class="order-summary-review" aria-label="Order review information">
                                    <p class="fs-13 text-body mb-3">Order review information</p>

                                    <div class="order-summary-review-block">
                                        <div class="order-summary-review-label">Customer</div>
                                        <p class="order-summary-review-value" id="reviewCustomerBlock">—</p>
                                    </div>

                                    <div class="order-summary-review-block">
                                        <div class="order-summary-review-label">Delivery</div>
                                        <p class="order-summary-review-value" id="reviewDeliveryBlock">—</p>
                                    </div>

                                    <div class="order-summary-review-block">
                                        <div class="order-summary-review-label">Courier</div>
                                        <p class="order-summary-review-value" id="reviewCourierBlock">—</p>
                                    </div>
                                </div>

                                <p class="fs-13 text-body mb-0 mt-3">
                                    Totals are a live preview. The server recalculates authoritative amounts on submit.
                                </p>
                            </div>
                        </div>

                        @if ($isEditMode && isset($order))
                            @include('pages.orders.partials.ui-order-payment', [
                                'canSubmitBankTransfer' => $canSubmitBankTransfer ?? false,
                            ])
                            @include('pages.orders.partials.ui-order-cca-assignment', [
                                'fieldIdPrefix' => 'editCca',
                            ])
                        @else
                            <div class="card bg-white rounded-10 border border-white mt-3" data-section="payment">
                                <div class="p-20 border-bottom">
                                    <h4 class="fs-18 mb-0">Payment</h4>
                                </div>
                                <div class="p-20">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="ui_payment_preview" id="payCod" value="cod" checked data-create-payment-method>
                                        <label class="form-check-label fs-14" for="payCod">Cash on delivery</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="ui_payment_preview" id="payBank" value="bank" data-create-payment-method>
                                        <label class="form-check-label fs-14" for="payBank">Bank transfer</label>
                                    </div>
                                    <div id="createBankTransferHint" class="order-payment-bank-fields hidden">
                                        <p class="fs-13 text-body mb-0">
                                            After the order is created, open the order and submit the payment slip, reference number, amount, and description for admin review.
                                        </p>
                                    </div>
                                    <p class="fs-13 text-body mb-0 mt-3" id="createCodPaymentHint">
                                        COD is the default payment method for new orders.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div id="duplicatePanel" class="card bg-white rounded-10 border border-warning mt-4 mb-0 hidden">
                <div class="p-20 border-bottom">
                    <h4 class="fs-18 mb-0 text-warning">Duplicate warning</h4>
                </div>
                <div class="p-20">
                    <p class="fs-14 mb-3">
                        Potential incomplete duplicate orders were found for this customer and variant(s).
                        Review them, then explicitly confirm to continue.
                    </p>
                    <div id="duplicateList" class="mb-3"></div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmDuplicateOverride">
                        <label class="form-check-label" for="confirmDuplicateOverride">
                            I reviewed the duplicates and want to Continue
                        </label>
                    </div>
                </div>
            </div>

            <div class="order-create-actions">
                <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
                    @if ($isEditMode)
                        <button type="button" class="btn btn-primary text-white" id="saveOrderChangesBtn">
                            Save changes
                        </button>
                    @else
                        <button type="button" class="btn btn-light border" id="sendToCallCenterBtn">
                            Send to Call Center
                        </button>
                        <button type="button" class="btn btn-primary text-white" id="confirmOrderBtn">
                            Confirm Order
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>

    @unless ($isEditMode)
    <div id="callCenterAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="callCenterAssignModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="callCenterAssignModalTitle">Send to Call Center</h5>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-3">Choose how this order should be assigned.</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetCca" value="cca">
                    <label class="form-check-label fs-14" for="assignTargetCca">Select CCA</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetPool" value="pool">
                    <label class="form-check-label fs-14" for="assignTargetPool">Send to Order Pool</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetUnassigned" value="unassigned" checked>
                    <label class="form-check-label fs-14" for="assignTargetUnassigned">Leave Unassigned</label>
                </div>
                <div class="mt-3">
                    <label class="label fs-14 mb-2" for="modalAssignCcaSelect">Select CCA</label>
                    <select id="modalAssignCcaSelect" class="form-select form-control" disabled></select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" id="closeCallCenterAssignModal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="confirmSendToCallCenterBtn">Send to Call Center</button>
            </div>
        </div>
    </div>
    @endunless

    @include('pages.orders.partials.ui-ban-modal', [
        'banName' => $fd('customer_name', ''),
        'banPhone1' => $fd('primary_phone', ''),
        'banPhone2' => $fd('secondary_phone', ''),
        'banUrl' => $catalogRoutes['customerBan'] ?? '',
        'banMode' => $isEditMode ? 'order' : 'catalog',
    ])
