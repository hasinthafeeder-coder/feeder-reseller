@extends('layout_main.app')

@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

@push('styles')
    <style>
        .order-create-prototype .order-create-section + .order-create-section {
            margin-top: 1.25rem;
        }

        .order-create-prototype .order-line-row {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            background: #fff;
        }

        .order-create-prototype .order-line-row .line-field-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.35rem;
            line-height: 1.2;
            min-height: 1.2em;
        }

        .order-create-prototype .order-line-row .line-control-height {
            height: 38px;
            min-height: 38px;
        }

        .order-create-prototype .order-line-row .form-control.line-control-height,
        .order-create-prototype .order-line-row .form-select.line-control-height {
            padding-top: 0.375rem;
            padding-bottom: 0.375rem;
        }

        .order-create-prototype .order-line-row .remove-line-btn.line-control-height {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding-top: 0;
            padding-bottom: 0;
            line-height: 1;
        }

        .order-create-prototype .order-line-row .line-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 1.25rem;
            margin-top: 0.65rem;
            padding-top: 0.55rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
            font-size: 12px;
            color: #64748b;
            line-height: 1.35;
        }

        .order-create-prototype .order-line-row .line-meta-item strong {
            font-weight: 600;
            color: #334155;
        }

        .order-create-prototype .order-line-row .line-selected-price {
            display: inline-block;
            width: 7.5rem;
            height: 28px;
            padding: 0.15rem 0.45rem;
            font-size: 12px;
            vertical-align: middle;
        }

        .order-create-prototype .order-line-row .line-selected-price-wrap.hidden {
            display: none;
        }

        .order-create-prototype #customerRiskPanel.hidden,
        .order-create-prototype #customerExtraPanel.hidden,
        .order-create-prototype #afterHoursPanel.hidden,
        .order-create-prototype #duplicatePanel.hidden,
        .order-create-prototype .product-search-results.hidden,
        .order-create-prototype .orders-proto-modal.hidden {
            display: none;
        }

        .order-create-prototype .customer-banned-warning {
            margin-top: 0.75rem;
            padding: 0.65rem 0.85rem;
            border: 1px solid rgba(220, 53, 69, 0.35);
            border-radius: 8px;
            background: #f8d7da;
            color: #842029;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
        }

        .order-create-prototype .customer-banned-warning .banned-warning-title {
            display: block;
            margin-bottom: 0.15rem;
        }

        .order-create-prototype .customer-banned-warning .banned-warning-detail {
            display: block;
            font-weight: 500;
            color: #a71d2a;
        }

        .order-create-prototype .market-checks {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }

        .order-create-prototype .product-search-wrap {
            position: relative;
        }

        .order-create-prototype .product-search-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            z-index: 15;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .product-search-option {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 0.65rem 0.85rem;
            font-size: 14px;
            color: inherit;
        }

        .order-create-prototype .product-search-option:hover,
        .order-create-prototype .product-search-option:focus {
            background: rgba(13, 110, 253, 0.06);
            outline: none;
        }

        .order-create-prototype .product-search-empty {
            padding: 0.75rem 0.85rem;
            font-size: 13px;
            color: #64748b;
        }

        .order-create-prototype .section-divider {
            border: 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin: 1.25rem 0;
        }

        .order-create-prototype .order-summary-review {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .order-create-prototype .order-summary-review-block + .order-summary-review-block {
            margin-top: 0.85rem;
        }

        .order-create-prototype .order-summary-review-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .order-create-prototype .order-summary-review-value {
            font-size: 14px;
            color: inherit;
            margin-bottom: 0;
            white-space: pre-line;
        }

        .order-create-prototype .order-summary-review-value.is-placeholder {
            color: #94a3b8;
        }

        .order-create-prototype .order-create-actions {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 0.5rem;
            padding-top: 1.25rem;
            padding-bottom: 0.5rem;
        }

        .order-create-prototype .order-create-actions .btn {
            width: 100%;
        }

        @media (min-width: 576px) {
            .order-create-prototype .order-create-actions .btn {
                width: auto;
            }
        }

        .order-create-prototype .sidebar-info-card + .sidebar-info-card {
            margin-top: 1rem;
        }

        .order-create-prototype .sidebar-info-card .card-body-compact {
            padding: 0.85rem 1rem;
        }

        .order-create-prototype .history-table {
            width: 100%;
            margin: 0;
            font-size: 12px;
        }

        .order-create-prototype .history-table th {
            font-weight: 600;
            color: #64748b;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.35rem 0.25rem;
            white-space: nowrap;
        }

        .order-create-prototype .history-table td {
            padding: 0.45rem 0.25rem;
            vertical-align: top;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .order-create-prototype .history-table tr:last-child td {
            border-bottom: 0;
        }

        .order-create-prototype .history-order-no {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-items {
            color: #64748b;
            max-width: 9rem;
        }

        .order-create-prototype .history-items-name {
            color: inherit;
        }

        .order-create-prototype .history-items-amount {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            margin-top: 0.15rem;
        }

        .order-create-prototype .history-reseller {
            min-width: 7.5rem;
            max-width: 11rem;
        }

        .order-create-prototype .history-reseller-company {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-reseller-name {
            font-size: 11px;
            color: #64748b;
        }

        .order-create-prototype .history-courier {
            min-width: 7rem;
            max-width: 10rem;
        }

        .order-create-prototype .history-courier-name {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-courier-tracking {
            font-size: 11px;
            color: #64748b;
        }

        .order-create-prototype .history-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem 0.55rem;
            margin-bottom: 0.85rem;
        }

        .order-create-prototype .history-summary-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.55rem;
            border-radius: 6px;
            background: #f1f5f9;
            font-size: 12px;
            color: #334155;
            line-height: 1.2;
        }

        .order-create-prototype .history-summary-chip strong {
            font-weight: 700;
            color: #0f172a;
        }

        .order-create-prototype .history-summary-chip.is-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }

        .order-create-prototype .history-summary-chip.is-returned {
            background: #fff3cd;
            color: #664d03;
        }

        .order-create-prototype .history-summary-chip.is-cancelled,
        .order-create-prototype .history-summary-chip.is-failed {
            background: #f8d7da;
            color: #842029;
        }

        .order-create-prototype .history-summary-chip.is-pending,
        .order-create-prototype .history-summary-chip.is-processing {
            background: #cfe2ff;
            color: #084298;
        }

        .order-create-prototype .history-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .history-pagination-meta {
            font-size: 12px;
            color: #64748b;
        }

        .order-create-prototype .history-pagination-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .order-create-prototype .history-pagination-actions .btn {
            min-width: 4.5rem;
        }

        .order-create-prototype .sidebar-empty {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }

        .order-create-prototype .order-timeline {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .order-create-prototype .order-timeline-item {
            position: relative;
            padding-left: 1.35rem;
            padding-bottom: 0.95rem;
        }

        .order-create-prototype .order-timeline-item:last-child {
            padding-bottom: 0;
        }

        .order-create-prototype .order-timeline-item::before {
            content: '';
            position: absolute;
            left: 0.28rem;
            top: 0.55rem;
            bottom: -0.2rem;
            width: 2px;
            background: rgba(15, 23, 42, 0.1);
        }

        .order-create-prototype .order-timeline-item:last-child::before {
            display: none;
        }

        .order-create-prototype .order-timeline-dot {
            position: absolute;
            left: 0;
            top: 0.28rem;
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.35);
            z-index: 1;
        }

        .order-create-prototype .order-timeline-dot.type-customer { background: #198754; box-shadow: 0 0 0 1px rgba(25, 135, 84, 0.35); }
        .order-create-prototype .order-timeline-dot.type-product { background: #0dcaf0; box-shadow: 0 0 0 1px rgba(13, 202, 240, 0.35); }
        .order-create-prototype .order-timeline-dot.type-courier { background: #6f42c1; box-shadow: 0 0 0 1px rgba(111, 66, 193, 0.35); }
        .order-create-prototype .order-timeline-dot.type-market { background: #fd7e14; box-shadow: 0 0 0 1px rgba(253, 126, 20, 0.35); }
        .order-create-prototype .order-timeline-dot.type-discount { background: #dc3545; box-shadow: 0 0 0 1px rgba(220, 53, 69, 0.35); }
        .order-create-prototype .order-timeline-dot.type-created { background: #0d6efd; }

        .order-create-prototype .order-timeline-time {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0.1rem;
        }

        .order-create-prototype .order-timeline-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 0.1rem;
        }

        .order-create-prototype .order-timeline-desc {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .order-create-prototype .badge-status {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }

        .order-create-prototype .bg-success-subtle { background: #d1e7dd; color: #0f5132; }
        .order-create-prototype .text-success { color: #198754; }
        .order-create-prototype .bg-warning-subtle { background: #fff3cd; color: #664d03; }
        .order-create-prototype .text-warning { color: #997404; }
        .order-create-prototype .bg-danger-subtle { background: #f8d7da; color: #842029; }
        .order-create-prototype .text-danger { color: #dc3545; }
        .order-create-prototype .bg-primary-subtle { background: #cfe2ff; color: #084298; }
        .order-create-prototype .text-primary { color: #0d6efd; }
        .order-create-prototype .bg-secondary-subtle { background: #e9ecef; color: #41464b; }
        .order-create-prototype .text-secondary { color: #6c757d; }

        @media (min-width: 992px) {
            .order-create-prototype .order-summary-sticky {
                position: sticky;
                top: 20px;
                max-height: calc(100vh - 40px);
                overflow-y: auto;
            }
        }

        .order-create-prototype .orders-proto-modal.modal-backdrop-proto {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-head {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-body {
            padding: 1.1rem 1.15rem;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-foot {
            padding: 0.9rem 1.15rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
    </style>
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
@if ($orderUiScreen)
    @include('pages.orders.partials.ui-single-order')
    @include('pages.orders.partials.ui-scripts')
@else
    <div class="main-content-container overflow-hidden order-create-prototype" id="manualOrderPrototype">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Create Order</h3>
                <p class="fs-15 text-body mb-0">
                    Manual order entry for call-center / reseller workflows.
                </p>
            </div>
            <a href="{{ route('orders.index') }}" class="btn btn-light border">Back to Orders</a>
        </div>

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                        <span class="text-body fs-14 hover">Dashboard</span>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('orders.index') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Orders</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Create</span>
                </li>
            </ol>
        </nav>

        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning mb-4" role="alert">
                {{ session('warning') }}
            </div>
        @endif

        <form method="POST" action="{{ $catalogRoutes['store'] }}" id="manualOrderForm" novalidate>
            @csrf
            <input type="hidden" name="intent" id="orderIntent" value="">
            <input type="hidden" name="assignment_target" id="assignmentTarget" value="">
            <input type="hidden" name="assign_cca_id" id="assignCcaId" value="">
            <input type="hidden" name="duplicate_warning_overridden" id="duplicateWarningOverridden" value="0">
            <input type="hidden" name="after_hours_warning_shown" id="afterHoursWarningShown" value="0">
            <input type="hidden" name="supplier_id" id="supplierId" value="{{ old('supplier_id', '') }}">
            <div class="row g-4">
                <div class="col-lg-8">
                    {{-- Customer Information --}}
                    <div class="card bg-white rounded-10 border border-white order-create-section" data-section="customer">
                        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="fs-18 mb-0">Customer</h4>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="openBanUserBtn">
                                Ban this User
                            </button>
                        </div>
                        <div class="p-20">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="label fs-14 mb-2" for="customerName">Customer name</label>
                                    <input type="text" class="form-control" id="customerName" name="customer_name"
                                        value="{{ old('customer_name', '') }}" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="label fs-14 mb-2" for="primaryPhone">Phone 1</label>
                                    <input type="text" class="form-control" id="primaryPhone" name="primary_phone"
                                        value="{{ old('primary_phone', '') }}" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="label fs-14 mb-2" for="secondaryPhone">Phone 2</label>
                                    <input type="text" class="form-control" id="secondaryPhone" name="secondary_phone"
                                        value="{{ old('secondary_phone', '') }}" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <div id="customerRiskPanel" class="customer-banned-warning hidden" role="alert" aria-live="polite"></div>
                                    <div id="customerExtraPanel" class="hidden" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                        value="{{ trim(old('address_line1', '').(old('address_line2') ? ', '.old('address_line2') : '')) }}" autocomplete="off">
                                    <input type="hidden" name="address_line2" value="">
                                    <input type="hidden" name="full_address_text" id="fullAddressText" value="{{ old('full_address_text', old('address_line1', '')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierId">Courier service</label>
                                    <select class="form-select form-control" id="courierId" name="courier_id">
                                        <option value="">Optional — select courier</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierDistrict">District</label>
                                    <select class="form-select form-control" id="courierDistrict" name="district_name" disabled>
                                        <option value="">Select district</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="label fs-14 mb-2" for="courierCity">City</label>
                                    <select class="form-select form-control" id="courierCity" name="city_name" disabled>
                                        <option value="">Select city</option>
                                    </select>
                                </div>
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
                                <input type="hidden" name="market_id" id="marketId" value="{{ old('market_id', '') }}">
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
                                        id="discountAmount" name="discount_amount" value="{{ old('discount_amount', '') }}">
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

                        <div class="card bg-white rounded-10 border border-white mt-3" data-section="payment">
                            <div class="p-20 border-bottom">
                                <h4 class="fs-18 mb-0">Payment</h4>
                            </div>
                            <div class="p-20">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="ui_payment_preview" id="payCod" value="cod" checked>
                                    <label class="form-check-label fs-14" for="payCod">Cash on delivery</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="ui_payment_preview" id="payBank" value="bank">
                                    <label class="form-check-label fs-14" for="payBank">Bank transfer</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="ui_payment_preview" id="payGateway" value="gateway">
                                    <label class="form-check-label fs-14" for="payGateway">Online gateway</label>
                                </div>
                                <p class="fs-13 text-body mb-0 mt-3">
                                    Payment method is visual only. It is not submitted or processed from this screen.
                                </p>
                            </div>
                        </div>
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
                    <button type="button" class="btn btn-light border" id="sendToCallCenterBtn">
                        Send to Call Center
                    </button>
                    <button type="button" class="btn btn-primary text-white" id="confirmOrderBtn">
                        Confirm Order
                    </button>
                </div>
            </div>
        </form>
    </div>

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

    @include('pages.orders.partials.ui-ban-modal')
@endif
@endsection

@unless ($orderUiScreen)
@push('scripts')
<script>
(function () {
    'use strict';

    const pageBootstrap = {
        markets: @json($markets),
        eligibleCcas: @json($eligibleCcas),
        isCca: @json($isCca),
        routes: @json($catalogRoutes),
        duplicateOrders: @json($duplicateOrders ?? []),
        oldItems: @json($oldItems ?? []),
        csrf: @json(csrf_token()),
    };

    const oldDistrictName = @json(old('district_name', ''));
    const oldCityName = @json(old('city_name', ''));
    const oldCourierId = @json(old('courier_id', ''));
    const oldMarketId = @json(old('market_id', ''));

    const marketChecks = document.getElementById('marketChecks');
    const marketIdInput = document.getElementById('marketId');
    const courierSelect = document.getElementById('courierId');
    const courierDistrict = document.getElementById('courierDistrict');
    const courierCity = document.getElementById('courierCity');
    const linesContainer = document.getElementById('orderLines');
    const addLineBtn = document.getElementById('addLineBtn');
    const discountInput = document.getElementById('discountAmount');
    const afterHoursPanel = document.getElementById('afterHoursPanel');
    const customerRiskPanel = document.getElementById('customerRiskPanel');
    const primaryPhone = document.getElementById('primaryPhone');
    const secondaryPhone = document.getElementById('secondaryPhone');
    const customerName = document.getElementById('customerName');
    const addressLine1 = document.getElementById('addressLine1');
    const fullAddressText = document.getElementById('fullAddressText');
    const form = document.getElementById('manualOrderForm');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');
    const sendToCallCenterBtn = document.getElementById('sendToCallCenterBtn');
    const reviewCustomerBlock = document.getElementById('reviewCustomerBlock');
    const reviewDeliveryBlock = document.getElementById('reviewDeliveryBlock');
    const reviewCourierBlock = document.getElementById('reviewCourierBlock');
    const customerHistoryPanel = document.getElementById('customerHistoryPanel');
    const customerExtraPanel = document.getElementById('customerExtraPanel');
    const orderTimelineList = document.getElementById('orderTimelineList');
    const supplierIdInput = document.getElementById('supplierId');
    const orderIntentInput = document.getElementById('orderIntent');
    const assignmentTargetInput = document.getElementById('assignmentTarget');
    const assignCcaIdInput = document.getElementById('assignCcaId');
    const duplicateWarningOverriddenInput = document.getElementById('duplicateWarningOverridden');
    const afterHoursWarningShownInput = document.getElementById('afterHoursWarningShown');
    const duplicatePanel = document.getElementById('duplicatePanel');
    const duplicateList = document.getElementById('duplicateList');
    const confirmDuplicateOverride = document.getElementById('confirmDuplicateOverride');
    const callCenterAssignModal = document.getElementById('callCenterAssignModal');
    const modalAssignCcaSelect = document.getElementById('modalAssignCcaSelect');
    const closeCallCenterAssignModal = document.getElementById('closeCallCenterAssignModal');
    const confirmSendToCallCenterBtn = document.getElementById('confirmSendToCallCenterBtn');

    let lineIndex = 0;
    let currencyCode = 'LKR';
    let courierFee = 0;
    let selectedMarketId = marketIdInput.value || null;
    let marketSuppliers = [];
    let lockedSupplierId = supplierIdInput.value || null;
    let createCouriers = [];
    let createCourierDistricts = [];
    let createCourierCities = [];
    let liveItemsSubtotal = 0;
    let liveTotalWeight = 0;
    let afterHoursData = null;
    let lastLookupResult = null;
    let duplicateCheckToken = 0;
    let timelineReady = false;
    let timelineEvents = [];
    let discountTimelineTimer = null;
    let orderHistoryRecords = [];
    let orderHistoryPage = 1;
    const ORDER_HISTORY_PAGE_SIZE = 10;

    function normalizePhone(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function money(amount, code) {
        const prefix = (code || currencyCode || '');
        return (prefix ? prefix + ' ' : '')
            + Number(amount || 0).toLocaleString('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function fetchCatalog(route, params) {
        const url = new URL(route, window.location.origin);

        Object.entries(params || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => url.searchParams.append(key + '[]', item));
                return;
            }

            url.searchParams.set(key, value);
        });

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Catalog request failed');
        }

        const payload = await response.json();
        return payload.data;
    }

    function setLockedSupplier(supplierId) {
        lockedSupplierId = supplierId ? String(supplierId) : null;
        supplierIdInput.value = lockedSupplierId || '';
        loadCouriers({ preferredCourierId: courierSelect.value });
    }

    async function loadSuppliersForMarket(marketId) {
        if (!marketId) {
            marketSuppliers = [];
            return;
        }

        marketSuppliers = await fetchCatalog(pageBootstrap.routes.suppliers, {
            market_id: marketId,
        });

        if (!lockedSupplierId && marketSuppliers.length === 1) {
            setLockedSupplier(marketSuppliers[0].id);
        }
    }

    function reviewText(value, placeholder) {
        const text = String(value || '').trim();
        return text || placeholder;
    }

    function setReviewBlock(el, lines, emptyPlaceholder) {
        const filled = lines.filter((line) => String(line || '').trim() !== '');
        if (!filled.length) {
            el.textContent = emptyPlaceholder;
            el.classList.add('is-placeholder');
            return;
        }
        el.textContent = filled.join('\n');
        el.classList.remove('is-placeholder');
    }

    function updateOrderReview() {
        setReviewBlock(reviewCustomerBlock, [
            reviewText(customerName.value, ''),
            reviewText(primaryPhone.value, ''),
            reviewText(secondaryPhone.value, ''),
        ], 'No customer details yet');

        const city = reviewText(courierCity.value, '');
        const district = reviewText(courierDistrict.value, '');
        const cityDistrict = [city, district].filter(Boolean).join(', ');

        setReviewBlock(reviewDeliveryBlock, [
            reviewText(addressLine1.value, ''),
            cityDistrict,
        ], 'No delivery details yet');

        const courier = selectedCourier();
        setReviewBlock(reviewCourierBlock, [
            courier ? courier.name : '',
            reviewText(courierDistrict.value, ''),
            reviewText(courierCity.value, ''),
        ], 'No courier selected');
    }

    function statusBadgeClass(status) {
        const key = String(status || '').toLowerCase();
        if (key === 'delivered') return 'bg-success-subtle text-success';
        if (key === 'returned') return 'bg-warning-subtle text-warning';
        if (key === 'cancelled' || key === 'failed') return 'bg-danger-subtle text-danger';
        if (key === 'pending' || key === 'processing') return 'bg-primary-subtle text-primary';
        return 'bg-secondary-subtle text-secondary';
    }

    function buildOrderHistorySummary(history) {
        const counts = {
            total: history.length,
            delivered: 0,
            returned: 0,
            cancelled: 0,
            failed: 0,
            processing: 0,
            pending: 0,
            other: 0,
        };

        history.forEach((order) => {
            const key = String(order.status || '').toLowerCase();
            if (key === 'delivered') counts.delivered += 1;
            else if (key === 'returned') counts.returned += 1;
            else if (key === 'cancelled') counts.cancelled += 1;
            else if (key === 'failed') counts.failed += 1;
            else if (key === 'processing') counts.processing += 1;
            else if (key === 'pending') counts.pending += 1;
            else counts.other += 1;
        });

        return counts;
    }

    function renderOrderHistorySummary(counts) {
        const chips = [
            { label: 'Total', value: counts.total, className: '' },
            { label: 'Delivered', value: counts.delivered, className: 'is-delivered' },
            { label: 'Returned', value: counts.returned, className: 'is-returned' },
            { label: 'Cancelled', value: counts.cancelled, className: 'is-cancelled' },
            { label: 'Failed', value: counts.failed, className: 'is-failed' },
            { label: 'Processing', value: counts.processing, className: 'is-processing' },
            { label: 'Pending', value: counts.pending, className: 'is-pending' },
        ];

        if (counts.other > 0) {
            chips.push({ label: 'Other', value: counts.other, className: '' });
        }

        return `
            <div class="history-summary" aria-label="Order history summary">
                ${chips.map((chip) => `
                    <span class="history-summary-chip ${chip.className}">
                        ${escapeHtml(chip.label)}
                        <strong>${chip.value}</strong>
                    </span>
                `).join('')}
            </div>
        `;
    }

    function renderOrderHistoryRows(orders) {
        return orders.map((order) => {
            const amount = money(order.amount, order.currency || 'LKR');
            const resellerCompany = order.reseller_company || '—';
            const resellerName = order.reseller_name || '—';
            const courierName = order.courier_name || '—';
            const trackingNumber = order.tracking_number || '—';
            return `
                <tr>
                    <td>
                        <div class="history-order-no">${escapeHtml(order.order_number)}</div>
                        <div class="text-body" style="font-size:11px">${escapeHtml(order.date)}</div>
                    </td>
                    <td class="history-reseller">
                        <div class="history-reseller-company">${escapeHtml(resellerCompany)}</div>
                        <div class="history-reseller-name">${escapeHtml(resellerName)}</div>
                    </td>
                    <td class="history-items">
                        <div class="history-items-name">${escapeHtml(order.items)}</div>
                        <div class="history-items-amount">${escapeHtml(amount)}</div>
                    </td>
                    <td class="history-courier">
                        <div class="history-courier-name">${escapeHtml(courierName)}</div>
                        <div class="history-courier-tracking">${escapeHtml(trackingNumber)}</div>
                    </td>
                    <td>
                        <span class="badge badge-status ${statusBadgeClass(order.status)}">
                            ${escapeHtml(order.status)}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderOrderHistoryPagination(total, page, pageSize) {
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        const from = total === 0 ? 0 : ((page - 1) * pageSize) + 1;
        const to = Math.min(total, page * pageSize);

        return `
            <div class="history-pagination">
                <div class="history-pagination-meta">
                    Showing ${from}–${to} of ${total} · Page ${page} of ${totalPages}
                </div>
                <div class="history-pagination-actions">
                    <button type="button" class="btn btn-sm btn-light border" id="historyPrevPageBtn"
                        ${page <= 1 ? 'disabled' : ''}>
                        Previous
                    </button>
                    <button type="button" class="btn btn-sm btn-light border" id="historyNextPageBtn"
                        ${page >= totalPages ? 'disabled' : ''}>
                        Next
                    </button>
                </div>
            </div>
        `;
    }

    function paintOrderHistoryPage() {
        const total = orderHistoryRecords.length;
        if (!total) {
            customerHistoryPanel.innerHTML =
                '<p class="sidebar-empty">No previous orders found for this customer.</p>';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / ORDER_HISTORY_PAGE_SIZE));
        if (orderHistoryPage > totalPages) orderHistoryPage = totalPages;
        if (orderHistoryPage < 1) orderHistoryPage = 1;

        const start = (orderHistoryPage - 1) * ORDER_HISTORY_PAGE_SIZE;
        const pageOrders = orderHistoryRecords.slice(start, start + ORDER_HISTORY_PAGE_SIZE);
        const summary = buildOrderHistorySummary(orderHistoryRecords);

        customerHistoryPanel.innerHTML = `
            ${renderOrderHistorySummary(summary)}
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
                    <tbody>${renderOrderHistoryRows(pageOrders)}</tbody>
                </table>
            </div>
            ${renderOrderHistoryPagination(total, orderHistoryPage, ORDER_HISTORY_PAGE_SIZE)}
        `;

        const prevBtn = document.getElementById('historyPrevPageBtn');
        const nextBtn = document.getElementById('historyNextPageBtn');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (orderHistoryPage <= 1) return;
                orderHistoryPage -= 1;
                paintOrderHistoryPage();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const maxPage = Math.max(1, Math.ceil(total / ORDER_HISTORY_PAGE_SIZE));
                if (orderHistoryPage >= maxPage) return;
                orderHistoryPage += 1;
                paintOrderHistoryPage();
            });
        }
    }

    function renderOrderHistory(customer) {
        orderHistoryRecords = customer?.order_history || [];
        orderHistoryPage = 1;
        paintOrderHistoryPage();
    }

    function refreshCustomerInsightPanels() {
        renderOrderHistory(lastLookupResult);
    }

    function formatTimelineClock(date) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    }

    function pushTimelineEvent({ title, description, type, key }) {
        if (!timelineReady && type !== 'created') {
            return;
        }

        const now = new Date();
        const entry = {
            key: key || null,
            time: formatTimelineClock(now),
            date: 'Today',
            title,
            description,
            type: type || 'created',
        };

        if (key) {
            const existingIndex = timelineEvents.findIndex((event) => event.key === key);
            if (existingIndex >= 0) {
                timelineEvents[existingIndex] = entry;
                renderTimeline();
                return;
            }
        }

        timelineEvents.push(entry);
        if (timelineEvents.length > 25) {
            timelineEvents = timelineEvents.slice(-25);
        }
        renderTimeline();
    }

    function renderTimeline() {
        if (!timelineEvents.length) {
            orderTimelineList.innerHTML =
                '<li class="sidebar-empty px-0">No timeline activity yet.</li>';
            return;
        }

        const newestFirst = timelineEvents.slice().reverse();
        orderTimelineList.innerHTML = newestFirst.map((event) => `
            <li class="order-timeline-item">
                <span class="order-timeline-dot type-${escapeHtml(event.type)}"></span>
                <div class="order-timeline-time">${escapeHtml(event.date)} · ${escapeHtml(event.time)}</div>
                <div class="order-timeline-title">${escapeHtml(event.title)}</div>
                <p class="order-timeline-desc">${escapeHtml(event.description)}</p>
            </li>
        `).join('');
    }

    function clearSelect(select, placeholder) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function selectedMarket() {
        return pageBootstrap.markets.find((m) => String(m.id) === String(selectedMarketId)) || null;
    }

    function selectedCourier() {
        return createCouriers.find((c) => String(c.id) === String(courierSelect.value)) || null;
    }

    function recalc() {
        let subtotal = 0;
        let weight = 0;

        linesContainer.querySelectorAll('.order-line-row').forEach((row) => {
            syncLinePricingDisplay(row);
            const qty = Number(row.querySelector('.line-qty')?.value || 0);
            const price = Number(row.querySelector('.line-price')?.value || 0);
            const unitWeight = Number(row.querySelector('.line-weight')?.value || 0);
            const lineTotal = qty * price;
            subtotal += lineTotal;
            weight += qty * unitWeight;
            const totalEl = row.querySelector('.line-total');
            if (totalEl) totalEl.textContent = money(lineTotal);
        });

        liveItemsSubtotal = subtotal;
        liveTotalWeight = weight;

        const discount = Number(discountInput.value || 0);
        const payable = Math.max(0, subtotal - discount + courierFee);

        document.getElementById('itemsSubtotalLabel').textContent = money(subtotal);
        document.getElementById('courierFeeLabel').textContent = money(courierFee);
        document.getElementById('customerPayableLabel').textContent = money(payable);
        document.getElementById('totalWeightLabel').textContent = weight.toFixed(3) + ' kg';
        updateOrderReview();
    }

    async function searchProducts(query) {
        if (!selectedMarketId) {
            return [];
        }

        const supplierIds = lockedSupplierId
            ? [lockedSupplierId]
            : marketSuppliers.map((supplier) => String(supplier.id));

        if (!supplierIds.length) {
            return [];
        }

        const q = String(query || '').trim();
        const merged = [];
        const seen = new Set();

        await Promise.all(supplierIds.map(async (supplierId) => {
            const products = await fetchCatalog(pageBootstrap.routes.products, {
                market_id: selectedMarketId,
                supplier_id: supplierId,
                search: q,
            });

            products.forEach((product) => {
                if (seen.has(product.id)) {
                    return;
                }

                seen.add(product.id);
                merged.push({
                    ...product,
                    supplier_id: Number(supplierId),
                });
            });
        }));

        return merged.slice(0, 8);
    }

    function collectSelectedVariantIds() {
        const ids = [];
        linesContainer.querySelectorAll('.line-variant').forEach((select) => {
            if (select.value) {
                ids.push(Number(select.value));
            }
        });
        return ids;
    }

    async function fetchAfterHours() {
        if (!selectedMarketId) {
            afterHoursPanel.classList.add('hidden');
            afterHoursPanel.textContent = '';
            afterHoursData = null;
            afterHoursWarningShownInput.value = '0';
            return;
        }

        try {
            afterHoursData = await fetchCatalog(pageBootstrap.routes.afterHours, {
                market_id: selectedMarketId,
            });

            afterHoursPanel.classList.remove('hidden');

            if (afterHoursData.after_hours) {
                afterHoursPanel.className = 'alert alert-warning mb-3';
                afterHoursPanel.innerHTML =
                    'After-hours order window. Local time '
                    + escapeHtml(afterHoursData.local_time)
                    + ' (' + escapeHtml(afterHoursData.timezone) + '). '
                    + 'If rejected/returned, penalty '
                    + escapeHtml(money(afterHoursData.after_hours_penalty_amount, afterHoursData.currency_code))
                    + ' may apply.';
                afterHoursWarningShownInput.value = '1';
            } else {
                afterHoursPanel.className = 'alert alert-info mb-3';
                afterHoursPanel.innerHTML =
                    'Within operating hours. Local time '
                    + escapeHtml(afterHoursData.local_time)
                    + ' (' + escapeHtml(afterHoursData.timezone) + ').';
                afterHoursWarningShownInput.value = '0';
            }
        } catch (error) {
            afterHoursPanel.classList.add('hidden');
            afterHoursData = null;
            afterHoursWarningShownInput.value = '0';
        }
    }

    async function setMarket(marketId, { resetLines = true, fromUser = false } = {}) {
        selectedMarketId = marketId ? String(marketId) : null;
        marketIdInput.value = selectedMarketId || '';

        marketChecks.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = String(input.value) === String(selectedMarketId);
        });

        const market = selectedMarket();
        currencyCode = market?.currency_code || '';

        if (selectedMarketId) {
            await loadSuppliersForMarket(selectedMarketId);
        } else {
            marketSuppliers = [];
        }

        await fetchAfterHours();

        if (resetLines) {
            linesContainer.innerHTML = '';
            lineIndex = 0;
            if (selectedMarketId) {
                addLine({ silent: true });
            }
        }

        refreshCustomerRiskFromLookup(lastLookupResult);
        scheduleDuplicateCheck();
        await loadCouriers({
            preferredCourierId: courierSelect.value || oldCourierId,
            preferredDistrict: courierDistrict.value || oldDistrictName,
            preferredCity: courierCity.value || oldCityName,
        });
        recalc();

        if (fromUser) {
            pushTimelineEvent({
                key: 'market',
                type: 'market',
                title: market ? 'Market selected' : 'Market cleared',
                description: market
                    ? (market.name + ' (' + market.currency_code + ')')
                    : 'No market selected',
            });
        }
    }

    function loadMarkets() {
        marketChecks.innerHTML = '';
        pageBootstrap.markets.forEach((market) => {
            const wrap = document.createElement('div');
            wrap.className = 'form-check mb-0';

            const input = document.createElement('input');
            input.className = 'form-check-input';
            input.type = 'checkbox';
            input.id = 'marketCheck_' + market.id;
            input.value = String(market.id);
            input.name = 'market_checks[]';

            const label = document.createElement('label');
            label.className = 'form-check-label fs-14';
            label.htmlFor = input.id;
            label.textContent = market.name + ' (' + market.currency_code + ')';

            input.addEventListener('change', () => {
                if (input.checked) {
                    setMarket(market.id, { fromUser: true });
                } else if (String(selectedMarketId) === String(market.id)) {
                    setMarket(null, { fromUser: true });
                }
            });

            wrap.appendChild(input);
            wrap.appendChild(label);
            marketChecks.appendChild(wrap);
        });
    }

    async function loadCouriers({ preferredCourierId = null, preferredDistrict = null, preferredCity = null } = {}) {
        clearSelect(courierSelect, 'Optional — select courier');
        createCouriers = [];
        resetCourierLocations();
        courierFee = 0;

        if (!selectedMarketId || !lockedSupplierId || !pageBootstrap.routes.couriers) {
            recalc();
            return;
        }

        createCouriers = await fetchCatalog(pageBootstrap.routes.couriers, {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
        });

        createCouriers.forEach((courier) => {
            const option = document.createElement('option');
            option.value = courier.id;
            option.textContent = courier.name;
            courierSelect.appendChild(option);
        });

        if (preferredCourierId && createCouriers.some((c) => String(c.id) === String(preferredCourierId))) {
            courierSelect.value = String(preferredCourierId);
            await loadDistrictsForCourier(preferredDistrict, preferredCity);
            await refreshCourierFeePreview();
        }

        recalc();
    }

    function resetCourierLocations() {
        clearSelect(courierDistrict, 'Select district');
        clearSelect(courierCity, 'Select city');
        courierDistrict.disabled = true;
        courierCity.disabled = true;
        createCourierDistricts = [];
        createCourierCities = [];
    }

    async function loadDistrictsForCourier(preferredDistrict = null, preferredCity = null) {
        clearSelect(courierDistrict, 'Select district');
        clearSelect(courierCity, 'Select city');
        createCourierDistricts = [];
        createCourierCities = [];

        const courier = selectedCourier();
        if (!courier || !selectedMarketId || !lockedSupplierId) {
            resetCourierLocations();
            return;
        }

        courierDistrict.disabled = false;
        createCourierDistricts = await fetchCatalog(pageBootstrap.routes.courierDistricts, {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
            courier_id: courier.id,
        });

        createCourierDistricts.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.district;
            option.textContent = row.district;
            courierDistrict.appendChild(option);
        });

        const districtNames = createCourierDistricts.map((d) => d.district);
        if (preferredDistrict && districtNames.includes(preferredDistrict)) {
            courierDistrict.value = preferredDistrict;
            await loadCities(preferredDistrict, preferredCity);
        } else {
            courierCity.disabled = true;
        }
    }

    async function loadCities(districtNameValue, preferredCity = null) {
        clearSelect(courierCity, 'Select city');
        createCourierCities = [];

        const courier = selectedCourier();
        if (!courier || !districtNameValue || !selectedMarketId || !lockedSupplierId) {
            courierCity.disabled = true;
            return;
        }

        courierCity.disabled = false;
        createCourierCities = await fetchCatalog(pageBootstrap.routes.courierCities, {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
            courier_id: courier.id,
            district: districtNameValue,
        });

        createCourierCities.forEach((city) => {
            const option = document.createElement('option');
            option.value = city.city_name;
            option.textContent = city.city_name;
            courierCity.appendChild(option);
        });

        if (preferredCity && createCourierCities.some((c) => c.city_name === preferredCity)) {
            courierCity.value = preferredCity;
        } else if (courierCity.options.length > 1) {
            courierCity.selectedIndex = 1;
        }
    }

    async function refreshCourierFeePreview() {
        const courier = selectedCourier();
        if (!courier || !selectedMarketId || !lockedSupplierId || !pageBootstrap.routes.courierFeePreview) {
            courierFee = 0;
            recalc();
            return;
        }

        try {
            const preview = await fetchCatalog(pageBootstrap.routes.courierFeePreview, {
                market_id: selectedMarketId,
                supplier_id: lockedSupplierId,
                courier_id: courier.id,
                total_weight: liveTotalWeight,
                items_subtotal: liveItemsSubtotal,
                discount_amount: Number(discountInput.value || 0),
            });
            courierFee = Number(preview.courier_fee_amount || 0);
        } catch (e) {
            courierFee = 0;
        }

        recalc();
    }

    function refreshCustomerRiskFromLookup(lookup) {
        customerRiskPanel.classList.add('hidden');
        customerRiskPanel.innerHTML = '';
        customerExtraPanel.classList.add('hidden');
        customerExtraPanel.innerHTML = '';

        const messages = [];

        if (lookup?.is_banned) {
            const detailParts = [];
            if (lookup.display_name) detailParts.push(lookup.display_name);
            if (lookup.ban_reason) detailParts.push(lookup.ban_reason);
            messages.push({
                title: 'Warning: this customer is banned',
                detail: detailParts.length
                    ? detailParts.join(' · ')
                    : 'Primary phone matches a globally banned customer.',
            });
        }

        if (messages.length) {
            customerRiskPanel.classList.remove('hidden');
            customerRiskPanel.innerHTML = messages.map((message) => `
                <div>
                    <span class="banned-warning-title">${escapeHtml(message.title)}</span>
                    <span class="banned-warning-detail">${escapeHtml(message.detail)}</span>
                </div>
            `).join('');
        }

        const extraAlerts = [];

        if (lookup?.phone_conflict) {
            extraAlerts.push(`
                <div class="alert alert-danger mb-2" role="alert">
                    ${escapeHtml(lookup.phone_conflict.message || 'Primary and secondary phones belong to different customers.')}
                </div>
            `);
        }

        if (lookup?.crib) {
            const crib = lookup.crib;
            extraAlerts.push(`
                <div class="alert alert-warning mb-0" role="alert">
                    <strong>CRIB risk:</strong>
                    ${escapeHtml(crib.risk_level || '')}
                    ${crib.risk_code ? ' (' + escapeHtml(crib.risk_code) + ')' : ''}
                    ${crib.risk_summary ? ' — ' + escapeHtml(crib.risk_summary) : ''}
                </div>
            `);
        }

        if (extraAlerts.length) {
            customerExtraPanel.classList.remove('hidden');
            customerExtraPanel.innerHTML = extraAlerts.join('');
        }
    }

    async function lookupCustomerByPhone() {
        const market = selectedMarket();
        const phone = primaryPhone.value.trim();

        if (!phone || !market) {
            lastLookupResult = null;
            renderOrderHistory(null);
            refreshCustomerRiskFromLookup(null);
            return;
        }

        try {
            const lookup = await fetchCatalog(pageBootstrap.routes.customerLookup, {
                phone,
                country_id: market.country_id,
                market_id: market.id,
                secondary_phone: secondaryPhone.value.trim() || undefined,
            });

            lastLookupResult = lookup;
            renderOrderHistory(lookup);
            refreshCustomerRiskFromLookup(lookup);

            if (lookup.found && lookup.display_name && !lookup.is_banned && !customerName.value.trim()) {
                customerName.value = lookup.display_name;
            }

            if (timelineReady && normalizePhone(phone).length >= 9) {
                pushTimelineEvent({
                    key: 'customer-lookup',
                    type: 'customer',
                    title: lookup.is_banned ? 'Banned customer matched' : (lookup.found ? 'Customer identified' : 'Customer not found'),
                    description: lookup.found
                        ? ((lookup.display_name || 'Customer') + ' matched by phone number')
                        : 'No customer matched this phone number',
                });
            }
        } catch (error) {
            lastLookupResult = null;
            renderOrderHistory(null);
            refreshCustomerRiskFromLookup(null);
        }
    }

    function variantOptionLabel(variant) {
        if (variant.price_locked) {
            return variant.name + ' — ' + money(variant.customer_unit_price ?? variant.selling_price);
        }

        const min = variant.suggested_price_min;
        const max = variant.suggested_price_max;
        if (min !== null && min !== undefined && max !== null && max !== undefined) {
            return variant.name + ' — ' + money(min) + ' – ' + money(max);
        }

        return variant.name;
    }

    function applyVariantPricing(row, option, preferredSelectedPrice) {
        const priceInput = row.querySelector('.line-price');
        const selectedPriceInput = row.querySelector('.line-selected-price');
        const selectedPriceWrap = row.querySelector('.line-selected-price-wrap');
        const costEl = row.querySelector('.line-cost');
        const sellingPriceEl = row.querySelector('.line-selling-price');
        const commissionEl = row.querySelector('.line-company-commission');
        const customerPriceEl = row.querySelector('.line-customer-price');
        const profitEl = row.querySelector('.line-profit');

        if (!option || !option.value) {
            if (priceInput) priceInput.value = '';
            if (selectedPriceInput) {
                selectedPriceInput.value = '';
                selectedPriceInput.removeAttribute('name');
                selectedPriceInput.removeAttribute('min');
                selectedPriceInput.removeAttribute('max');
                selectedPriceInput.disabled = true;
            }
            if (selectedPriceWrap) selectedPriceWrap.classList.add('hidden');
            if (costEl) costEl.textContent = '—';
            if (sellingPriceEl) sellingPriceEl.textContent = '—';
            if (commissionEl) commissionEl.textContent = '—';
            if (customerPriceEl) customerPriceEl.textContent = money(0);
            if (profitEl) profitEl.textContent = money(0);
            return;
        }

        const priceLocked = option.dataset.priceLocked === '1';
        const cost = Number(option.dataset.cost || 0);
        const commission = Number(option.dataset.companyCommission || 0);
        const sellingPrice = option.dataset.sellingPrice !== undefined && option.dataset.sellingPrice !== ''
            ? Number(option.dataset.sellingPrice)
            : null;
        const min = option.dataset.suggestedPriceMin !== undefined && option.dataset.suggestedPriceMin !== ''
            ? Number(option.dataset.suggestedPriceMin)
            : null;
        const max = option.dataset.suggestedPriceMax !== undefined && option.dataset.suggestedPriceMax !== ''
            ? Number(option.dataset.suggestedPriceMax)
            : null;
        const defaultCustomer = option.dataset.customerUnitPrice !== undefined && option.dataset.customerUnitPrice !== ''
            ? Number(option.dataset.customerUnitPrice)
            : (priceLocked ? sellingPrice : min);

        if (costEl) costEl.textContent = money(cost);
        if (commissionEl) commissionEl.textContent = money(commission);

        if (priceLocked) {
            if (sellingPriceEl) sellingPriceEl.textContent = money(sellingPrice);
            if (selectedPriceWrap) selectedPriceWrap.classList.add('hidden');
            if (selectedPriceInput) {
                selectedPriceInput.value = '';
                selectedPriceInput.removeAttribute('name');
                selectedPriceInput.removeAttribute('min');
                selectedPriceInput.removeAttribute('max');
                selectedPriceInput.disabled = true;
            }
            if (priceInput) priceInput.value = defaultCustomer != null ? String(defaultCustomer) : '';
        } else {
            if (sellingPriceEl) {
                sellingPriceEl.textContent = (min != null && max != null)
                    ? (money(min) + ' – ' + money(max))
                    : '—';
            }
            if (selectedPriceWrap) selectedPriceWrap.classList.remove('hidden');
            if (selectedPriceInput) {
                const index = row.dataset.index;
                selectedPriceInput.disabled = false;
                selectedPriceInput.name = 'items[' + index + '][selected_selling_price]';
                if (min != null) selectedPriceInput.min = String(min);
                if (max != null) selectedPriceInput.max = String(max);
                const preferred = preferredSelectedPrice != null && preferredSelectedPrice !== ''
                    ? Number(preferredSelectedPrice)
                    : defaultCustomer;
                selectedPriceInput.value = preferred != null ? Number(preferred).toFixed(2) : '';
            }
            if (priceInput) {
                priceInput.value = selectedPriceInput?.value || '';
            }
        }

        syncLinePricingDisplay(row);
    }

    function syncLinePricingDisplay(row) {
        const priceInput = row.querySelector('.line-price');
        const selectedPriceInput = row.querySelector('.line-selected-price');
        const variantSelect = row.querySelector('.line-variant');
        const option = variantSelect?.options[variantSelect.selectedIndex];
        const customerPriceEl = row.querySelector('.line-customer-price');
        const profitEl = row.querySelector('.line-profit');

        if (selectedPriceInput && !selectedPriceInput.disabled && selectedPriceInput.value !== '') {
            if (priceInput) priceInput.value = selectedPriceInput.value;
        }

        const customerPrice = Number(priceInput?.value || 0);
        const cost = Number(option?.dataset?.cost || 0);
        const commission = Number(option?.dataset?.companyCommission || 0);
        const profit = customerPrice - cost - commission;

        if (customerPriceEl) customerPriceEl.textContent = money(customerPrice);
        if (profitEl) profitEl.textContent = money(profit);
    }

    async function fillVariants(select, productId, preferredId, priceInput, weightInput, barcodeEl, preferredSelectedPrice) {
        clearSelect(select, 'Select variant');
        priceInput.value = '';
        weightInput.value = '';
        if (barcodeEl) barcodeEl.textContent = '—';
        const clearRow = select.closest('.order-line-row');
        const clearItemCode = clearRow?.querySelector('.line-item-code');
        if (clearItemCode) clearItemCode.textContent = '—';
        applyVariantPricing(clearRow, null);

        if (!productId || !selectedMarketId || !lockedSupplierId) {
            recalc();
            return;
        }

        try {
            const variants = await fetchCatalog(pageBootstrap.routes.variants, {
                market_id: selectedMarketId,
                supplier_id: lockedSupplierId,
                product_id: productId,
            });

            variants.forEach((variant) => {
                const option = document.createElement('option');
                option.value = variant.id;
                option.textContent = variantOptionLabel(variant);
                option.dataset.priceLocked = variant.price_locked ? '1' : '0';
                option.dataset.cost = variant.cost ?? '0';
                option.dataset.companyCommission = variant.company_commission ?? '0';
                option.dataset.sellingPrice = variant.selling_price ?? '';
                option.dataset.suggestedPriceMin = variant.suggested_price_min ?? '';
                option.dataset.suggestedPriceMax = variant.suggested_price_max ?? '';
                option.dataset.customerUnitPrice = variant.customer_unit_price ?? '';
                option.dataset.weight = variant.weight;
                option.dataset.barcode = variant.barcode || '';
                if (preferredId && String(preferredId) === String(variant.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (!preferredId && select.options.length > 1) {
                select.selectedIndex = 1;
            }

            const selected = select.options[select.selectedIndex];
            if (selected && selected.value) {
                weightInput.value = selected.dataset.weight || '';
                if (barcodeEl) {
                    barcodeEl.textContent = selected.dataset.barcode || '—';
                }
                const row = select.closest('.order-line-row');
                const itemCodeEl = row?.querySelector('.line-item-code');
                if (itemCodeEl) itemCodeEl.textContent = selected.value || '—';
                applyVariantPricing(row, selected, preferredSelectedPrice);
            }
        } catch (error) {
            // Leave variant select empty when catalog lookup fails.
        }

        recalc();
        scheduleDuplicateCheck();
    }

    function renderDuplicateList(orders) {
        return orders.map((order) => {
            const items = (order.items || [])
                .map((item) => `${item.product} / ${item.variant} × ${item.quantity}`)
                .join('; ');

            return `
                <div class="border rounded p-2 mb-2 fs-14">
                    <strong>${escapeHtml(order.order_number || ('Order #' + order.id))}</strong>
                    <span class="badge badge-status ms-2 ${statusBadgeClass(order.status)}">${escapeHtml(order.status)}</span>
                    <div class="text-body fs-13 mt-1">${escapeHtml(order.created_at || '')}</div>
                    <div class="text-body fs-13">${escapeHtml(items)}</div>
                </div>
            `;
        }).join('');
    }

    function showDuplicatePanel(orders) {
        duplicatePanel.classList.remove('hidden');
        duplicateList.innerHTML = renderDuplicateList(orders);
        confirmDuplicateOverride.checked = false;
        duplicateWarningOverriddenInput.value = '0';
    }

    function hideDuplicatePanel() {
        duplicatePanel.classList.add('hidden');
        duplicateList.innerHTML = '';
        confirmDuplicateOverride.checked = false;
        duplicateWarningOverriddenInput.value = '0';
    }

    async function scheduleDuplicateCheck() {
        const market = selectedMarket();
        const phone = primaryPhone.value.trim();
        const variantIds = collectSelectedVariantIds();

        if (!phone || !market || !variantIds.length) {
            hideDuplicatePanel();
            return;
        }

        const token = ++duplicateCheckToken;

        try {
            const duplicates = await fetchCatalog(pageBootstrap.routes.duplicates, {
                phone,
                country_id: market.country_id,
                variant_ids: variantIds,
            });

            if (token !== duplicateCheckToken) {
                return;
            }

            if (duplicates.length) {
                showDuplicatePanel(duplicates);
            } else {
                hideDuplicatePanel();
            }
        } catch (error) {
            // Keep duplicate panel state unchanged on transient lookup failures.
        }
    }

    async function renderProductResults(row, resultsEl, query) {
        resultsEl.innerHTML = '';
        resultsEl.classList.remove('hidden');

        if (!selectedMarketId) {
            resultsEl.innerHTML = '<div class="product-search-empty">Select a market first.</div>';
            return;
        }

        if (!marketSuppliers.length && !lockedSupplierId) {
            resultsEl.innerHTML = '<div class="product-search-empty">No suppliers available for this market.</div>';
            return;
        }

        resultsEl.innerHTML = '<div class="product-search-empty">Searching...</div>';

        try {
            const products = await searchProducts(query);
            resultsEl.innerHTML = '';

            if (!products.length) {
                resultsEl.innerHTML = '<div class="product-search-empty">No products found.</div>';
                return;
            }

            products.forEach((product) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'product-search-option';
                button.textContent = product.name;
                button.dataset.productId = String(product.id);
                button.dataset.supplierId = String(product.supplier_id);
                button.addEventListener('click', () => {
                    selectProductForRow(row, product);
                    resultsEl.classList.add('hidden');
                });
                resultsEl.appendChild(button);
            });
        } catch (error) {
            resultsEl.innerHTML = '<div class="product-search-empty">Product search failed.</div>';
        }
    }

    function selectProductForRow(row, product) {
        const searchInput = row.querySelector('.line-product-search');
        const productIdInput = row.querySelector('.line-product-id');
        const variantSelect = row.querySelector('.line-variant');
        const priceInput = row.querySelector('.line-price');
        const weightInput = row.querySelector('.line-weight');
        const barcodeEl = row.querySelector('.line-barcode');

        if (product.supplier_id) {
            setLockedSupplier(product.supplier_id);
        }

        searchInput.value = product.name;
        productIdInput.value = product.id;
        fillVariants(variantSelect, product.id, null, priceInput, weightInput, barcodeEl);

        const qty = Number(row.querySelector('.line-qty')?.value || 1);
        pushTimelineEvent({
            type: 'product',
            title: 'Product added',
            description: product.name + ' × ' + qty,
        });
    }

    function addLine(preset) {
        if (!selectedMarketId) {
            return;
        }

        const index = lineIndex++;
        const row = document.createElement('div');
        row.className = 'order-line-row';
        row.dataset.index = String(index);
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md">
                    <label class="line-field-label">Product</label>
                    <div class="product-search-wrap">
                        <input type="text" class="form-control line-control-height line-product-search" placeholder="Search product..."
                            autocomplete="off" name="items_ui_${index}_product_search">
                        <input type="hidden" class="line-product-id" name="items_ui_${index}_product" value="">
                        <div class="product-search-results hidden" role="listbox"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3">
                    <label class="line-field-label">Variant</label>
                    <select class="form-select form-control line-control-height line-variant" name="items[${index}][product_variant_id]">
                        <option value="">Select variant</option>
                    </select>
                </div>
                <div class="col-3 col-md-2 col-lg-1">
                    <label class="line-field-label">Qty</label>
                    <input type="number" min="1" class="form-control line-control-height line-qty" name="items[${index}][quantity]" value="${preset?.quantity || 1}">
                </div>
                <div class="col-3 col-md-2 col-lg-auto">
                    <label class="line-field-label">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-light border remove-line-btn line-control-height">Remove</button>
                    <input type="hidden" class="line-price" value="">
                    <input type="hidden" class="line-weight" value="">
                </div>
            </div>
            <div class="line-meta">
                <span class="line-meta-item">Item code: <strong class="line-item-code">—</strong></span>
                <span class="line-meta-item">Barcode: <strong class="line-barcode">—</strong></span>
                <span class="line-meta-item">Cost: <strong class="line-cost">—</strong></span>
                <span class="line-meta-item">Selling Price: <strong class="line-selling-price">—</strong></span>
                <span class="line-meta-item line-selected-price-wrap hidden">Your Selling Price:
                    <input type="number" step="0.01" min="0" class="form-control line-selected-price" value="" disabled>
                </span>
                <span class="line-meta-item">Company Commission: <strong class="line-company-commission">—</strong></span>
                <span class="line-meta-item">Customer Price: <strong class="line-customer-price">0.00</strong></span>
                <span class="line-meta-item">Profit: <strong class="line-profit">0.00</strong></span>
                <span class="line-meta-item">Line total: <strong class="line-total">0.00</strong></span>
            </div>
        `;

        linesContainer.appendChild(row);

        const searchInput = row.querySelector('.line-product-search');
        const resultsEl = row.querySelector('.product-search-results');
        const productIdInput = row.querySelector('.line-product-id');
        const variantSelect = row.querySelector('.line-variant');
        const qtyInput = row.querySelector('.line-qty');
        const priceInput = row.querySelector('.line-price');
        const weightInput = row.querySelector('.line-weight');
        const barcodeEl = row.querySelector('.line-barcode');
        const selectedPriceInput = row.querySelector('.line-selected-price');

        let searchTimer = null;

        searchInput.addEventListener('focus', () => {
            renderProductResults(row, resultsEl, searchInput.value);
        });

        searchInput.addEventListener('input', () => {
            productIdInput.value = '';
            clearSelect(variantSelect, 'Select variant');
            priceInput.value = '';
            weightInput.value = '';
            barcodeEl.textContent = '—';
            const itemCodeEl = row.querySelector('.line-item-code');
            if (itemCodeEl) itemCodeEl.textContent = '—';
            applyVariantPricing(row, null);
            recalc();

            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                renderProductResults(row, resultsEl, searchInput.value);
            }, 180);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                resultsEl.classList.add('hidden');
            }
        });

        variantSelect.addEventListener('change', () => {
            const selected = variantSelect.options[variantSelect.selectedIndex];
            weightInput.value = selected?.dataset?.weight || '';
            barcodeEl.textContent = selected?.dataset?.barcode || '—';
            const itemCodeEl = row.querySelector('.line-item-code');
            if (itemCodeEl) itemCodeEl.textContent = selected?.value || '—';
            applyVariantPricing(row, selected && selected.value ? selected : null);
            recalc();
            scheduleDuplicateCheck();
        });

        selectedPriceInput.addEventListener('input', () => {
            const min = selectedPriceInput.min !== '' ? Number(selectedPriceInput.min) : null;
            const max = selectedPriceInput.max !== '' ? Number(selectedPriceInput.max) : null;
            let value = Number(selectedPriceInput.value);
            if (selectedPriceInput.value !== '' && !Number.isNaN(value)) {
                if (min != null && value < min) {
                    selectedPriceInput.setCustomValidity('Selected selling price must be at least ' + min.toFixed(2));
                } else if (max != null && value > max) {
                    selectedPriceInput.setCustomValidity('Selected selling price must be at most ' + max.toFixed(2));
                } else {
                    selectedPriceInput.setCustomValidity('');
                }
            } else {
                selectedPriceInput.setCustomValidity('');
            }
            recalc();
        });

        qtyInput.addEventListener('input', recalc);

        row.querySelector('.remove-line-btn').addEventListener('click', () => {
            const productName = row.querySelector('.line-product-search')?.value || 'Line item';
            row.remove();
            recalc();
            pushTimelineEvent({
                type: 'product',
                title: 'Product line removed',
                description: productName,
            });
        });

        if (preset?.product_id) {
            productIdInput.value = preset.product_id;
            if (preset.product_name) {
                searchInput.value = preset.product_name;
            }
            fillVariants(
                variantSelect,
                preset.product_id,
                preset?.product_variant_id || null,
                priceInput,
                weightInput,
                barcodeEl,
                preset?.selected_selling_price ?? null
            );
        }

        if (timelineReady && !preset?.silent) {
            pushTimelineEvent({
                type: 'product',
                title: 'Product line added',
                description: 'New empty order line ready for product selection',
            });
        }
    }

    function duplicatePanelVisible() {
        return !duplicatePanel.classList.contains('hidden');
    }

    function prepareSubmit(intent, assignmentTarget, assignCcaId) {
        orderIntentInput.value = intent;
        assignmentTargetInput.value = assignmentTarget || '';
        assignCcaIdInput.value = assignCcaId || '';

        if (duplicatePanelVisible() && !confirmDuplicateOverride.checked) {
            window.alert('Please review the duplicate orders and confirm Continue before submitting.');
            return false;
        }

        if (!supplierIdInput.value) {
            window.alert('Please select at least one product to determine the supplier.');
            return false;
        }

        if (duplicatePanelVisible() && confirmDuplicateOverride.checked) {
            duplicateWarningOverriddenInput.value = '1';
        }

        return true;
    }

    function submitForm() {
        form.submit();
    }

    function openCallCenterAssignModal() {
        modalAssignCcaSelect.innerHTML = '';
        pageBootstrap.eligibleCcas.forEach((cca) => {
            const option = document.createElement('option');
            option.value = String(cca.id);
            option.textContent = cca.name;
            modalAssignCcaSelect.appendChild(option);
        });

        document.getElementById('assignTargetUnassigned').checked = true;
        modalAssignCcaSelect.disabled = true;
        callCenterAssignModal.classList.remove('hidden');
    }

    function closeCallCenterModal() {
        callCenterAssignModal.classList.add('hidden');
    }

    document.querySelectorAll('input[name="modalAssignmentTarget"]').forEach((input) => {
        input.addEventListener('change', () => {
            modalAssignCcaSelect.disabled = input.value !== 'cca';
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.product-search-wrap')) {
            linesContainer.querySelectorAll('.product-search-results').forEach((el) => {
                el.classList.add('hidden');
            });
        }
    });

    courierSelect.addEventListener('change', async () => {
        const courier = selectedCourier();
        courierFee = 0;

        if (courier) {
            await loadDistrictsForCourier();
            await refreshCourierFeePreview();
        } else {
            resetCourierLocations();
            recalc();
        }

        pushTimelineEvent({
            key: 'courier',
            type: 'courier',
            title: courier ? 'Courier draft selected' : 'Courier draft cleared',
            description: courier
                ? (courier.name + ' — draft only, no shipment booked')
                : 'No courier selected',
        });
    });

    courierDistrict.addEventListener('change', async () => {
        await loadCities(courierDistrict.value);
        updateOrderReview();
        if (courierDistrict.value) {
            pushTimelineEvent({
                key: 'district',
                type: 'courier',
                title: 'District selected',
                description: courierDistrict.value,
            });
        }
    });

    courierCity.addEventListener('change', () => {
        updateOrderReview();
        if (courierCity.value) {
            pushTimelineEvent({
                key: 'city',
                type: 'courier',
                title: 'City selected',
                description: courierCity.value,
            });
        }
    });

    addLineBtn.addEventListener('click', () => addLine());
    discountInput.addEventListener('input', () => {
        recalc();
        if (selectedCourier()) {
            refreshCourierFeePreview();
        }
        window.clearTimeout(discountTimelineTimer);
        discountTimelineTimer = window.setTimeout(() => {
            pushTimelineEvent({
                key: 'discount',
                type: 'discount',
                title: 'Discount updated',
                description: money(discountInput.value || 0),
            });
        }, 400);
    });

    let phoneTimer = null;
    primaryPhone.addEventListener('input', () => {
        window.clearTimeout(phoneTimer);
        phoneTimer = window.setTimeout(() => {
            lookupCustomerByPhone();
            scheduleDuplicateCheck();
            updateOrderReview();
        }, 350);
        updateOrderReview();
    });
    primaryPhone.addEventListener('blur', () => {
        lookupCustomerByPhone();
        scheduleDuplicateCheck();
    });
    secondaryPhone.addEventListener('input', () => {
        window.clearTimeout(phoneTimer);
        phoneTimer = window.setTimeout(() => {
            lookupCustomerByPhone();
            updateOrderReview();
        }, 350);
        updateOrderReview();
    });

    [customerName, addressLine1].forEach((el) => {
        el.addEventListener('input', () => {
            if (el === addressLine1 && fullAddressText) {
                fullAddressText.value = addressLine1.value;
            }
            updateOrderReview();
        });
    });

    confirmDuplicateOverride.addEventListener('change', () => {
        duplicateWarningOverriddenInput.value = confirmDuplicateOverride.checked ? '1' : '0';
    });

    form.addEventListener('submit', (event) => {
        if (!orderIntentInput.value) {
            event.preventDefault();
            window.alert('Choose Confirm Order or Send to Call Center.');
        }
    });

    confirmOrderBtn.addEventListener('click', () => {
        if (!prepareSubmit('confirm', '', '')) {
            return;
        }
        submitForm();
    });

    sendToCallCenterBtn.addEventListener('click', () => {
        if (pageBootstrap.isCca) {
            if (!prepareSubmit('send_to_call_center', 'unassigned', '')) {
                return;
            }
            submitForm();
            return;
        }

        openCallCenterAssignModal();
    });

    closeCallCenterAssignModal.addEventListener('click', closeCallCenterModal);
    confirmSendToCallCenterBtn.addEventListener('click', () => {
        const selectedTarget = document.querySelector('input[name="modalAssignmentTarget"]:checked');
        const target = selectedTarget ? selectedTarget.value : 'unassigned';
        const assignCcaId = target === 'cca' ? modalAssignCcaSelect.value : '';

        if (target === 'cca' && !assignCcaId) {
            window.alert('Please select a CCA.');
            return;
        }

        if (!prepareSubmit('send_to_call_center', target, assignCcaId)) {
            return;
        }

        closeCallCenterModal();
        submitForm();
    });

    const openBanUserBtn = document.getElementById('openBanUserBtn');
    const banUserModal = document.getElementById('banUserModal');
    const confirmBanUserBtn = document.getElementById('confirmBanUserBtn');
    if (openBanUserBtn && banUserModal) {
        openBanUserBtn.addEventListener('click', () => {
            const nameInput = document.getElementById('banCustomerName');
            const phone1Input = document.getElementById('banPhoneOne');
            const phone2Input = document.getElementById('banPhoneTwo');
            if (nameInput) nameInput.value = customerName.value;
            if (phone1Input) phone1Input.value = primaryPhone.value;
            if (phone2Input) phone2Input.value = secondaryPhone.value;
            banUserModal.classList.remove('hidden');
        });
        banUserModal.addEventListener('click', (event) => {
            if (event.target === banUserModal) {
                banUserModal.classList.add('hidden');
            }
        });
        banUserModal.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => banUserModal.classList.add('hidden'));
        });
    }
    if (confirmBanUserBtn) {
        confirmBanUserBtn.addEventListener('click', () => {
            if (banUserModal) banUserModal.classList.add('hidden');
            window.alert('Ban is visual only. CustomerBanService was not called.');
        });
    }

    callCenterAssignModal.addEventListener('click', (event) => {
        if (event.target === callCenterAssignModal) {
            closeCallCenterModal();
        }
    });

    async function initPage() {
        timelineReady = false;
        timelineEvents = [];
        pushTimelineEvent({
            type: 'created',
            title: 'Order started',
            description: 'Manual order entry initiated',
        });

        loadMarkets();
        resetCourierLocations();

        if (oldMarketId) {
            await setMarket(oldMarketId, { resetLines: false });
        } else if (pageBootstrap.markets.length === 1) {
            await setMarket(pageBootstrap.markets[0].id, { resetLines: false });
        }

        if (pageBootstrap.oldItems.length) {
            pageBootstrap.oldItems.forEach((item) => {
                addLine({
                    product_id: item.product_id,
                    product_variant_id: item.product_variant_id,
                    quantity: item.quantity,
                    selected_selling_price: item.selected_selling_price ?? null,
                    silent: true,
                });
            });
        } else if (selectedMarketId) {
            addLine({ silent: true });
        }

        await loadCouriers({
            preferredCourierId: oldCourierId,
            preferredDistrict: oldDistrictName,
            preferredCity: oldCityName,
        });

        if (pageBootstrap.duplicateOrders.length) {
            showDuplicatePanel(pageBootstrap.duplicateOrders);
        }

        await lookupCustomerByPhone();
        recalc();
        updateOrderReview();

        timelineReady = true;
    }

    initPage();
})();
</script>
@endpush
@endunless
