@extends('layout_main.app')

@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

@push('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .orders-list-prototype .pool-lock-banner {
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.75rem 0.95rem;
            margin-bottom: 1rem;
            font-size: 13px;
            line-height: 1.45;
        }

        .orders-list-prototype .pool-lock-banner strong {
            display: block;
            margin-bottom: 0.15rem;
        }

        .orders-list-prototype .prototype-banner {
            border: 1px dashed rgba(180, 83, 9, 0.45);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.25rem;
        }

        .orders-list-prototype .prototype-toast {
            position: sticky;
            top: 0.75rem;
            z-index: 30;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .prototype-toast.hidden,
        .orders-list-prototype .hidden {
            display: none !important;
        }

        .orders-list-prototype .role-switcher {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.08);
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .role-switcher select {
            font-size: 12px;
            padding: 0.15rem 0.45rem;
            height: auto;
            min-height: 0;
            border-radius: 6px;
        }

        .orders-list-prototype .workspace-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .workspace-tab {
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #fff;
            color: #334155;
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
        }

        .orders-list-prototype .workspace-tab:hover {
            background: #f8fafc;
        }

        .orders-list-prototype .workspace-tab.is-active {
            background: #ef4923;
            border-color: #ef4923;
            color: #fff;
        }

        .orders-list-prototype .workspace-tab .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.35rem;
            height: 1.2rem;
            padding: 0 0.35rem;
            margin-left: 0.35rem;
            border-radius: 999px;
            font-size: 11px;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
        }

        .orders-list-prototype .workspace-tab.is-active .tab-count {
            background: rgba(255, 255, 255, 0.22);
        }

        .orders-list-prototype .filter-chip .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.2rem;
            height: 1.1rem;
            padding: 0 0.3rem;
            margin-left: 0.35rem;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
            vertical-align: middle;
        }

        .orders-list-prototype .filter-chip.is-active .tab-count {
            background: rgba(255, 255, 255, 0.22);
        }

        .orders-list-prototype .pool-lock-banner {
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.75rem 0.95rem;
            margin-bottom: 1rem;
            font-size: 13px;
            line-height: 1.45;
        }

        .orders-list-prototype .pool-lock-banner strong {
            display: block;
            margin-bottom: 0.15rem;
        }

        .orders-list-prototype .search-filter-card .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: stretch;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap {
            flex: 1 1 280px;
            position: relative;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap .material-symbols-outlined {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap input {
            padding-left: 2.35rem;
        }

        .orders-list-prototype .filter-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.85rem;
        }

        .orders-list-prototype .filter-chip {
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #f8fafc;
            color: #334155;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
        }

        .orders-list-prototype .filter-chip.is-active {
            background: #ef4923;
            border-color: #ef4923;
            color: #fff;
        }

        .orders-list-prototype .advanced-filters {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 0.95rem;
            padding-top: 0.95rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
        }

        .orders-list-prototype .advanced-filters .label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.3rem;
        }

        .orders-list-prototype .bulk-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(239, 73, 35, 0.22);
            background: rgba(239, 73, 35, 0.06);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .bulk-toolbar .bulk-count {
            font-size: 13px;
            font-weight: 600;
            color: #ef4923;
        }

        .orders-list-prototype .bulk-toolbar .bulk-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .orders-list-prototype .orders-table {
            width: 100%;
            margin: 0;
            font-size: 13px;
        }

        .orders-list-prototype .orders-table thead th {
            font-size: 11px;
            font-weight: 650;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            white-space: nowrap;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.7rem 0.65rem;
            background: #fafbfc;
        }

        .orders-list-prototype .orders-table tbody td {
            padding: 0.8rem 0.65rem;
            vertical-align: top;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .orders-list-prototype .orders-table tbody tr:hover {
            background: #f8fafc;
        }

        .orders-list-prototype .orders-table tbody tr.is-selected {
            background: rgba(239, 73, 35, 0.06);
        }

        .orders-list-prototype .order-number {
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.25;
        }

        .orders-list-prototype .order-sub,
        .orders-proto-modal .order-sub {
            font-size: 12px;
            color: #64748b;
            margin-top: 0.15rem;
            line-height: 1.35;
        }

        .orders-list-prototype .customer-name {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.25;
        }

        .orders-list-prototype .amount-value {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .orders-list-prototype .badge-status,
        .orders-list-prototype .badge-source,
        .orders-list-prototype .badge-assign,
        .orders-proto-modal .badge-status,
        .orders-proto-modal .badge-source {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.28rem 0.55rem;
            border-radius: 4px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .orders-list-prototype .badge-status.is-pending,
        .orders-proto-modal .badge-status.is-pending { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
        .orders-list-prototype .badge-status.is-confirmed,
        .orders-proto-modal .badge-status.is-confirmed { background: #cff4fc; color: #055160; }
        .orders-list-prototype .badge-status.is-processing,
        .orders-proto-modal .badge-status.is-processing { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-delivered,
        .orders-proto-modal .badge-status.is-delivered { background: #d1e7dd; color: #0f5132; }
        .orders-list-prototype .badge-status.is-returned,
        .orders-proto-modal .badge-status.is-returned { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-cancelled,
        .orders-proto-modal .badge-status.is-cancelled { background: #f8d7da; color: #842029; }

        .orders-list-prototype .badge-source.is-manual { background: #e9ecef; color: #41464b; }
        .orders-list-prototype .badge-source.is-meta { background: rgba(239, 73, 35, 0.1); color: #c0391a; }

        .orders-list-prototype .badge-assign.is-pool { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-assign.is-unassigned { background: #f1f5f9; color: #475569; }
        .orders-list-prototype .badge-assign.is-me { background: #d1e7dd; color: #0f5132; }
        .orders-list-prototype .badge-assign.is-other { background: rgba(239, 73, 35, 0.12); color: #ef4923; }

        .orders-list-prototype .assign-block .assign-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.25;
        }

        .orders-list-prototype .assign-block .assign-role {
            font-size: 11px;
            color: #64748b;
        }

        .orders-list-prototype .status-select {
            font-size: 12px;
            padding: 0.25rem 0.45rem;
            min-height: 0;
            height: auto;
            border-radius: 6px;
            max-width: 8.5rem;
        }

        .orders-list-prototype .orders-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .orders-list-prototype .orders-pagination-meta {
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .orders-pagination-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .orders-list-prototype .orders-pagination-actions .btn {
            min-width: 4.5rem;
        }

        .orders-list-prototype .empty-state {
            text-align: center;
            padding: 2.75rem 1.25rem;
        }

        .orders-list-prototype .empty-state .material-symbols-outlined {
            font-size: 42px;
            color: #ef4923;
            margin-bottom: 0.75rem;
        }

        .orders-list-prototype .empty-state h4 {
            font-size: 17px;
            font-weight: 650;
            margin-bottom: 0.35rem;
        }

        .orders-list-prototype .empty-state p {
            font-size: 14px;
            color: #64748b;
            margin: 0 auto;
            max-width: 420px;
        }

        .orders-proto-modal.modal-backdrop-proto {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .orders-proto-modal.hidden {
            display: none !important;
        }

        .orders-proto-modal .modal-panel-proto {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .orders-proto-modal .modal-panel-proto .modal-head {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .orders-proto-modal .modal-panel-proto .modal-body {
            padding: 1.1rem 1.15rem;
        }

        .orders-proto-modal .modal-panel-proto .modal-foot {
            padding: 0.9rem 1.15rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .orders-proto-modal .selected-order-list {
            margin: 0.75rem 0 0;
            padding-left: 1.1rem;
            font-size: 13px;
            color: #334155;
            max-height: 160px;
            overflow: auto;
        }

        .orders-list-prototype .results-meta {
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .custom-date-row {
            display: none;
            grid-column: 1 / -1;
            grid-template-columns: repeat(2, minmax(0, 180px));
            gap: 0.75rem;
        }

        .orders-list-prototype .custom-date-row.is-visible {
            display: grid;
        }

        @media (max-width: 1199.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: 1fr;
            }
        }

        .orders-list-prototype .badge-status.is-1st-attempt { background: #cfe2ff; color: #084298; }
        .orders-list-prototype .badge-status.is-2nd-attempt { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-3rd-attempt { background: rgba(239, 73, 35, 0.12); color: #c0391a; }
        .orders-list-prototype .badge-status.is-hold { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-expiring { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
        .orders-list-prototype .badge-status.is-expired { background: #e9ecef; color: #41464b; }
    </style>
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
@if ($orderUiIsArchitectureList)
    @if ($orderUiWorkspace === 'new')
        @include('pages.orders.partials.ui-new-orders')
    @elseif ($orderUiWorkspace === 'import')
        @include('pages.orders.partials.ui-import')
    @else
        @include('pages.orders.partials.ui-list-workspace', ['bootstrap' => $bootstrap ?? []])
    @endif
    @include('pages.orders.partials.ui-scripts')
@else
    <div class="main-content-container overflow-hidden orders-list-prototype" id="ordersListPrototype">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Ongoing Orders</h3>
                <p class="fs-15 text-body mb-0">
                    Call center / reseller order management workspace.
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('orders.create') }}" class="btn btn-primary text-white hidden" id="createOrderBtn">
                    Create Order
                </a>
            </div>
        </div>

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                        <span class="text-body fs-14 hover">Dashboard</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Ongoing Orders</span>
                </li>
            </ol>
        </nav>

        <div id="prototypeToast" class="alert alert-success prototype-toast hidden" role="status"></div>

        <div id="poolLockBanner" class="pool-lock-banner hidden" role="status"></div>

        <div id="workspaceTabs" class="workspace-tabs" role="tablist"></div>

        <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
            <div class="p-20">
                <div class="search-row">
                    <div class="search-input-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" id="ordersSearchInput" class="form-control"
                            placeholder="Search orders by number, customer, phone, CCA, supplier, status..."
                            autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-primary text-white" id="ordersSearchBtn">Search</button>
                    <button type="button" class="btn btn-light border" id="ordersClearBtn">Clear</button>
                    <button type="button" class="btn btn-light border" id="toggleFiltersBtn">
                        Filters
                    </button>
                </div>

                <div class="filter-chip-row" id="statusChipRow" aria-label="Status filters"></div>

                <div id="advancedFilters" class="advanced-filters">
                    <div>
                        <label class="label" for="filterAssignment">Assignment</label>
                        <select id="filterAssignment" class="form-select form-control">
                            <option value="all">All</option>
                            <option value="my">My Orders</option>
                            <option value="unassigned">Unassigned</option>
                            <option value="pool">Order Pool</option>
                            <option value="assigned">Assigned</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="filterCca">CCA</label>
                        <select id="filterCca" class="form-select form-control"></select>
                    </div>
                    <div>
                        <label class="label" for="filterSupplier">Supplier</label>
                        <select id="filterSupplier" class="form-select form-control"></select>
                    </div>
                    <div>
                        <label class="label" for="filterSource">Order source</label>
                        <select id="filterSource" class="form-select form-control">
                            <option value="all">All</option>
                            <option value="manual">Manual</option>
                            <option value="meta">Meta Import</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="filterDatePreset">Date</label>
                        <select id="filterDatePreset" class="form-select form-control">
                            <option value="all">All time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7">Last 7 Days</option>
                            <option value="last30">Last 30 Days</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-light border w-100" id="resetFiltersBtn">Reset filters</button>
                    </div>
                    <div id="customDateRow" class="custom-date-row">
                        <div>
                            <label class="label" for="filterDateFrom">From</label>
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>
                        <div>
                            <label class="label" for="filterDateTo">To</label>
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="bulkToolbar" class="bulk-toolbar hidden">
            <div class="bulk-count"><span id="bulkSelectedCount">0</span> orders selected</div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-primary btn-sm text-white" id="bulkAssignBtn">Assign to CCA</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="bulkPoolBtn">Move to Order Pool</button>
                <button type="button" class="btn btn-light border btn-sm" id="bulkClearBtn">Clear Selection</button>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fs-18 mb-0" id="workspaceTitle">Orders</h4>
                    <div class="results-meta mt-1" id="resultsMeta"></div>
                </div>
                <div class="results-meta" id="userContextMeta"></div>
            </div>
            <div class="p-20" id="workspaceContent"></div>
        </div>
    </div>

    {{-- Modals sit outside overflow-hidden container so fixed overlay is not clipped --}}
    <div id="assignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="assignModalTitle">Assign orders</h5>
            </div>
            <div class="modal-body">
                <label class="label fs-14 mb-2" for="assignCcaSelect">Select CCA</label>
                <select id="assignCcaSelect" class="form-select form-control"></select>
                <div class="mt-3">
                    <div class="fs-13 text-body">Selected orders:</div>
                    <ul class="selected-order-list" id="assignOrderList"></ul>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="assignModal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="confirmAssignBtn">Assign Orders</button>
            </div>
        </div>
    </div>

    <div id="poolModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="poolModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="poolModalTitle">Move orders to Order Pool?</h5>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-2" id="poolModalCopy">
                    These orders will become available to CCAs in your company.
                </p>
                <ul class="selected-order-list" id="poolOrderList"></ul>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="poolModal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="confirmPoolBtn">Move to Pool</button>
            </div>
        </div>
    </div>

    {{-- Prototype stand-in for CCA order view status updates --}}
    <div id="orderViewModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="orderViewModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="orderViewModalTitle">Order view</h5>
            </div>
            <div class="modal-body" id="orderViewModalBody"></div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="orderViewModal">Close</button>
            </div>
        </div>
    </div>
@endif
@endsection

@unless ($orderUiIsArchitectureList)
@push('scripts')
<script type="application/json" id="ordersBootstrap">@json($bootstrap)</script>
<script>
(function () {
    'use strict';

    const bootstrapEl = document.getElementById('ordersBootstrap');
    if (!bootstrapEl) return;

    const bootstrap = JSON.parse(bootstrapEl.textContent);
    const actor = bootstrap.actor;
    const filters = bootstrap.filters;
    const orders = bootstrap.orders;

    let selectedOrderIds = [];
    let activeTab = filters.tab || defaultTabForRole();
    let statusFilter = filters.status !== '' ? filters.status : 'all';
    let searchQuery = filters.search || '';
    let filtersVisible = true;
    let currentPage = bootstrap.pagination.current_page;

    const root = document.getElementById('ordersListPrototype');
    if (!root) return;

    const els = {
        poolLockBanner: document.getElementById('poolLockBanner'),
        workspaceTabs: document.getElementById('workspaceTabs'),
        workspaceTitle: document.getElementById('workspaceTitle'),
        workspaceContent: document.getElementById('workspaceContent'),
        resultsMeta: document.getElementById('resultsMeta'),
        userContextMeta: document.getElementById('userContextMeta'),
        searchInput: document.getElementById('ordersSearchInput'),
        searchBtn: document.getElementById('ordersSearchBtn'),
        clearBtn: document.getElementById('ordersClearBtn'),
        toggleFiltersBtn: document.getElementById('toggleFiltersBtn'),
        advancedFilters: document.getElementById('advancedFilters'),
        statusChipRow: document.getElementById('statusChipRow'),
        filterAssignment: document.getElementById('filterAssignment'),
        filterCca: document.getElementById('filterCca'),
        filterSupplier: document.getElementById('filterSupplier'),
        filterSource: document.getElementById('filterSource'),
        filterDatePreset: document.getElementById('filterDatePreset'),
        filterDateFrom: document.getElementById('filterDateFrom'),
        filterDateTo: document.getElementById('filterDateTo'),
        customDateRow: document.getElementById('customDateRow'),
        resetFiltersBtn: document.getElementById('resetFiltersBtn'),
        bulkToolbar: document.getElementById('bulkToolbar'),
        bulkSelectedCount: document.getElementById('bulkSelectedCount'),
        bulkAssignBtn: document.getElementById('bulkAssignBtn'),
        bulkPoolBtn: document.getElementById('bulkPoolBtn'),
        bulkClearBtn: document.getElementById('bulkClearBtn'),
        toast: document.getElementById('prototypeToast'),
        assignModal: document.getElementById('assignModal'),
        poolModal: document.getElementById('poolModal'),
        assignCcaSelect: document.getElementById('assignCcaSelect'),
        assignOrderList: document.getElementById('assignOrderList'),
        poolOrderList: document.getElementById('poolOrderList'),
        assignModalTitle: document.getElementById('assignModalTitle'),
        poolModalTitle: document.getElementById('poolModalTitle'),
        confirmAssignBtn: document.getElementById('confirmAssignBtn'),
        confirmPoolBtn: document.getElementById('confirmPoolBtn'),
        createOrderBtn: document.getElementById('createOrderBtn'),
    };

    function isReseller() {
        return actor.role === 'reseller';
    }

    function isCca() {
        return actor.role === 'cca';
    }

    function canAssign() {
        return !!actor.can_assign;
    }

    function defaultTabForRole() {
        return isReseller() ? 'all' : 'my';
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Request failed');
            throw new Error(msg);
        }
        return data;
    }

    function buildQuery(overrides) {
        overrides = overrides || {};
        const state = {
            search: overrides.search !== undefined ? overrides.search : searchQuery,
            status: overrides.status !== undefined ? overrides.status : (statusFilter === 'all' ? '' : statusFilter),
            tab: overrides.tab !== undefined ? overrides.tab : activeTab,
            assignment: overrides.assignment !== undefined ? overrides.assignment : (els.filterAssignment.value === 'all' ? '' : els.filterAssignment.value),
            cca_id: overrides.cca_id !== undefined ? overrides.cca_id : (els.filterCca.value === 'all' ? '' : els.filterCca.value),
            supplier_id: overrides.supplier_id !== undefined ? overrides.supplier_id : (els.filterSupplier.value === 'all' ? '' : els.filterSupplier.value),
            source: overrides.source !== undefined ? overrides.source : (els.filterSource.value === 'all' ? '' : els.filterSource.value),
            date_preset: overrides.date_preset !== undefined ? overrides.date_preset : (els.filterDatePreset.value === 'all' ? '' : els.filterDatePreset.value),
            date_from: overrides.date_from !== undefined ? overrides.date_from : els.filterDateFrom.value,
            date_to: overrides.date_to !== undefined ? overrides.date_to : els.filterDateTo.value,
            page: overrides.page !== undefined ? overrides.page : currentPage,
        };
        const params = new URLSearchParams();
        Object.keys(state).forEach(function (key) {
            const val = state[key];
            if (val !== '' && val !== null && val !== undefined) {
                params.set(key, String(val));
            }
        });
        return params.toString();
    }

    function navigate(overrides) {
        const qs = buildQuery(overrides || {});
        window.location.href = bootstrap.routes.index + (qs ? '?' + qs : '');
    }

    function formatMoney(amount, currency) {
        return currency + ' ' + Number(amount).toLocaleString('en-LK');
    }

    function formatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return d.toLocaleString('en-US', {
            month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true
        });
    }

    function showToast(message, type) {
        els.toast.className = 'alert prototype-toast alert-' + (type || 'success');
        els.toast.textContent = message;
        els.toast.classList.remove('hidden');
        window.clearTimeout(showToast._t);
        showToast._t = window.setTimeout(function () { els.toast.classList.add('hidden'); }, 2800);
    }

    function canTakeFromPool() {
        return !bootstrap.pool_lock;
    }

    function assignmentState(order) {
        if (order.assignment === 'pool') return 'pool';
        if (order.assignment === 'unassigned') return 'unassigned';
        if (order.isMine) return 'me';
        if (order.assignment === 'assigned') return 'other';
        return 'unassigned';
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function statusCssClass(status) {
        return String(status || '').toLowerCase().replace(/_/g, '-');
    }

    function isMetaSource(source) {
        const s = String(source || '').toLowerCase();
        return s === 'meta' || s === 'meta_import';
    }

    function tabDefs() {
        const c = bootstrap.counts;
        if (isReseller()) {
            return [
                { id: 'all', label: 'All Orders', count: c.all },
                { id: 'assigned', label: 'Assigned', count: c.assigned },
                { id: 'unassigned', label: 'Unassigned', count: c.unassigned },
                { id: 'pool', label: 'Order Pool', count: c.pool },
            ];
        }
        return [
            { id: 'my', label: 'My Orders', count: c.my },
            { id: 'company', label: 'Company Orders', count: c.all },
            { id: 'pool', label: 'Order Pool', count: c.pool },
        ];
    }

    function renderTabs() {
        const tabs = tabDefs();
        if (!tabs.some(function (t) { return t.id === activeTab; })) {
            activeTab = defaultTabForRole();
        }
        els.workspaceTabs.innerHTML = tabs.map(function (t) {
            return '<button type="button" class="workspace-tab ' + (activeTab === t.id ? 'is-active' : '') + '" data-tab="' + t.id + '" role="tab" aria-selected="' + (activeTab === t.id) + '">' +
                escapeHtml(t.label) + '<span class="tab-count">' + t.count + '</span></button>';
        }).join('');
    }

    function renderStatusChips() {
        const chips = [{ value: 'all', label: 'All' }].concat(bootstrap.statuses);
        els.statusChipRow.innerHTML = chips.map(function (s) {
            const active = statusFilter === s.value;
            return '<button type="button" class="filter-chip ' + (active ? 'is-active' : '') + '" data-status-chip="' + escapeHtml(s.value) + '">' +
                escapeHtml(s.label) + '</button>';
        }).join('');
    }

    function populateFilterSelects() {
        els.filterCca.innerHTML = '<option value="all">All CCAs</option>' + bootstrap.ccas.map(function (c) {
            return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
        }).join('');

        els.filterSupplier.innerHTML = '<option value="all">All suppliers</option>' + bootstrap.suppliers.map(function (s) {
            return '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
        }).join('');

        els.filterSource.innerHTML = '<option value="all">All</option>' + bootstrap.sources.map(function (s) {
            return '<option value="' + escapeHtml(s.value) + '">' + escapeHtml(s.label) + '</option>';
        }).join('');

        els.assignCcaSelect.innerHTML = bootstrap.ccas.map(function (c) {
            return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
        }).join('');
    }

    function syncFilterControls() {
        els.searchInput.value = filters.search || '';
        els.filterAssignment.value = filters.assignment || 'all';
        els.filterCca.value = filters.cca_id || 'all';
        els.filterSupplier.value = filters.supplier_id || 'all';
        els.filterSource.value = filters.source || 'all';
        els.filterDatePreset.value = filters.date_preset || 'all';
        els.filterDateFrom.value = filters.date_from || '';
        els.filterDateTo.value = filters.date_to || '';
    }

    function renderPoolLockBanner() {
        const lock = bootstrap.pool_lock;
        if (!isCca() || !lock) {
            els.poolLockBanner.classList.add('hidden');
            els.poolLockBanner.innerHTML = '';
            return;
        }
        const showUrl = bootstrap.routes.show + '/' + encodeURIComponent(lock.order_uuid);
        els.poolLockBanner.classList.remove('hidden');
        els.poolLockBanner.innerHTML =
            '<strong>Pool pickup locked</strong>' +
            'You already have a pool order assigned to you (<span class="fw-semibold">' + escapeHtml(lock.order_number) + '</span>).' +
            ' Open the order view and update its status before taking another order from the Order Pool.' +
            '<div class="mt-2"><a href="' + escapeHtml(showUrl) + '" class="btn btn-sm btn-outline-warning">Open Order View</a></div>';
    }

    function renderPagination() {
        const p = bootstrap.pagination;
        if (p.total === 0) return '';
        const from = (p.current_page - 1) * p.per_page + 1;
        const to = Math.min(p.current_page * p.per_page, p.total);
        return '<div class="orders-pagination">' +
            '<div class="orders-pagination-meta">Showing ' + from + '–' + to + ' of ' + p.total + '</div>' +
            '<div class="orders-pagination-actions">' +
            '<button type="button" class="btn btn-sm btn-light border" data-page-nav="prev"' + (p.current_page <= 1 ? ' disabled' : '') + '>Previous</button>' +
            '<span class="orders-pagination-meta">Page ' + p.current_page + ' of ' + p.last_page + '</span>' +
            '<button type="button" class="btn btn-sm btn-light border" data-page-nav="next"' + (p.current_page >= p.last_page ? ' disabled' : '') + '>Next</button>' +
            '</div></div>';
    }

    function sourceHtml(order) {
        const meta = isMetaSource(order.source);
        const sourceBadge = meta
            ? '<span class="badge-source is-meta">Meta Import</span>'
            : '<span class="badge-source is-manual">Manual</span>';
        let createdBy = '';
        if (!meta && order.createdByRole) {
            if (isCca() && order.createdByRole === 'cca' && order.isMine) {
                createdBy = '<div class="order-sub">Created by You</div>';
            } else {
                createdBy = '<div class="order-sub">Created by ' + (order.createdByRole === 'cca' ? 'CCA' : 'Reseller') + '</div>';
            }
        }
        if (order.fromPool && order.assignment === 'assigned') {
            createdBy += '<div class="order-sub">From Order Pool</div>';
        }
        return sourceBadge + createdBy;
    }

    function createdCellHtml(order) {
        return '<div>' + sourceHtml(order) + '</div><div class="order-sub mt-1">' + formatDate(order.createdAt) + '</div>';
    }

    function itemsAmountHtml(order) {
        return '<div class="amount-value">' + formatMoney(order.amount, order.currency) + '</div>' +
            '<div class="order-sub">' + order.itemCount + ' item' + (order.itemCount === 1 ? '' : 's') + '</div>';
    }

    function courierHtml(order) {
        if (!order.courierName) {
            return '<span class="order-sub">—</span>';
        }
        return '<div class="customer-name">' + escapeHtml(order.courierName) + '</div>' +
            '<div class="order-sub">' + escapeHtml(order.trackingId || 'No tracking') + '</div>';
    }

    function assignmentHtml(order) {
        const state = assignmentState(order);
        if (state === 'pool') {
            return '<div class="assign-block"><span class="badge-assign is-pool">● Order Pool</span></div>';
        }
        if (state === 'unassigned') {
            return '<div class="assign-block"><span class="badge-assign is-unassigned">● Unassigned</span></div>';
        }
        if (state === 'me') {
            return '<div class="assign-block"><span class="badge-assign is-me">● Assigned to Me</span><div class="assign-role">CCA</div></div>';
        }
        return '<div class="assign-block"><span class="badge-assign is-other">● Assigned to CCA</span>' +
            '<div class="assign-name mt-1">' + escapeHtml(order.ccaName || '—') + '</div><div class="assign-role">CCA</div></div>';
    }

    function statusSelectHtml(order) {
        const options = bootstrap.statuses.map(function (s) {
            const selected = order.status === statusCssClass(s.value) ? ' selected' : '';
            return '<option value="' + escapeHtml(s.value) + '"' + selected + '>' + escapeHtml(s.label) + '</option>';
        }).join('');
        return '<select class="form-select status-select" disabled aria-label="Update status">' + options + '</select>';
    }

    function statusCellHtml(order) {
        const cls = statusCssClass(order.status);
        const badge = '<span class="badge-status is-' + cls + '">' + escapeHtml(order.statusLabel || order.status) + '</span>';
        if (isReseller()) {
            return '<div class="mb-1">' + badge + '</div>' + statusSelectHtml(order);
        }
        return badge;
    }

    function actionsCellHtml(order) {
        const viewBtn = '<a href="' + escapeHtml(order.showUrl) + '" class="btn btn-sm btn-light border">View</a>';
        if (isCca() && actor.can_claim && activeTab === 'pool' && order.assignment === 'pool') {
            const locked = !canTakeFromPool();
            return '<div class="d-flex flex-column gap-1 align-items-stretch">' +
                '<button type="button" class="btn btn-primary btn-sm text-white" data-assign-me="' + order.id + '"' + (locked ? ' disabled' : '') + '>Assign to Me</button>' +
                viewBtn + '</div>';
        }
        return viewBtn;
    }

    function emptyStateHtml(kind) {
        if (kind === 'pool') {
            return '<div class="empty-state"><span class="material-symbols-outlined">inbox</span><h4>The order pool is empty</h4><p>There are currently no orders waiting for CCA assignment.</p></div>';
        }
        if (kind === 'search') {
            return '<div class="empty-state"><span class="material-symbols-outlined">search_off</span><h4>No orders match your search</h4><p>Try a different order number, customer, phone, CCA, supplier, or status.</p></div>';
        }
        if (kind === 'my') {
            return '<div class="empty-state"><span class="material-symbols-outlined">person</span><h4>No orders assigned to you</h4><p>Pick up an order from the Order Pool, or wait for reseller assignment.</p></div>';
        }
        return '<div class="empty-state"><span class="material-symbols-outlined">shopping_cart</span><h4>No orders found</h4><p>There are no orders in this view.</p></div>';
    }

    function emptyKindForCurrentView() {
        if (orders.length) return null;
        const hasSearchOrFilters = searchQuery.trim() !== '' || statusFilter !== 'all' ||
            (filters.assignment && filters.assignment !== 'all') ||
            (filters.cca_id && filters.cca_id !== 'all') ||
            (filters.supplier_id && filters.supplier_id !== 'all') ||
            (filters.source && filters.source !== 'all') ||
            (filters.date_preset && filters.date_preset !== 'all');
        if (hasSearchOrFilters) return 'search';
        if (activeTab === 'pool') return 'pool';
        if (activeTab === 'my') return 'my';
        return 'empty';
    }

    function renderTable() {
        const showBulk = canAssign();
        const locked = isCca() && activeTab === 'pool' && !canTakeFromPool();
        const lock = bootstrap.pool_lock;
        const poolNote = locked && lock ? (
            '<div class="pool-lock-banner mb-3"><strong>Assign to Me is disabled</strong>' +
            'Complete a status update on your current pool order in the order view (' + escapeHtml(lock.order_number) + ') before taking another order.</div>'
        ) : '';

        const rows = orders.map(function (order) {
            const checked = selectedOrderIds.includes(order.id) ? ' checked' : '';
            const selectedClass = selectedOrderIds.includes(order.id) ? ' is-selected' : '';
            return '<tr class="' + selectedClass.trim() + '" data-order-row="' + order.id + '">' +
                (showBulk ? '<td><input type="checkbox" class="form-check-input" data-select-order="' + order.id + '"' + checked + '></td>' : '') +
                '<td><div class="order-number">' + escapeHtml(order.orderNumber) + '</div><div class="order-sub">#' + order.id + '</div></td>' +
                '<td><div class="customer-name">' + escapeHtml(order.customerName) + '</div><div class="order-sub">' + escapeHtml(order.customerPhone) + '</div></td>' +
                '<td>' + itemsAmountHtml(order) + '</td>' +
                '<td>' + assignmentHtml(order) + '</td>' +
                '<td>' + escapeHtml(order.supplierName || '—') + '</td>' +
                '<td>' + courierHtml(order) + '</td>' +
                '<td>' + statusCellHtml(order) + '</td>' +
                '<td>' + createdCellHtml(order) + '</td>' +
                '<td>' + actionsCellHtml(order) + '</td></tr>';
        }).join('');

        const allSelected = orders.length && orders.every(function (o) { return selectedOrderIds.includes(o.id); });

        return poolNote +
            '<div class="table-responsive"><table class="orders-table align-middle"><thead><tr>' +
            (showBulk ? '<th scope="col" style="width:36px"><input type="checkbox" class="form-check-input" id="selectAllOrders"' + (allSelected ? ' checked' : '') + '></th>' : '') +
            '<th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Items / Amount</th>' +
            '<th scope="col">CCA</th><th scope="col">Supplier</th><th scope="col">Courier</th>' +
            '<th scope="col">Status</th><th scope="col">Created</th><th scope="col">Actions</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>' + renderPagination();
    }

    function renderBulkToolbar() {
        if (!canAssign() || selectedOrderIds.length === 0) {
            els.bulkToolbar.classList.add('hidden');
            return;
        }
        els.bulkToolbar.classList.remove('hidden');
        els.bulkSelectedCount.textContent = String(selectedOrderIds.length);
    }

    function renderWorkspace() {
        const titles = {
            all: 'All Orders', assigned: 'Assigned Orders', unassigned: 'Unassigned Orders',
            pool: 'Order Pool', my: 'My Orders', company: 'Company Orders',
        };
        const p = bootstrap.pagination;
        els.workspaceTitle.textContent = titles[activeTab] || 'Orders';
        els.resultsMeta.textContent = p.total + ' in this view · ' + bootstrap.counts.all + ' total in company';
        els.userContextMeta.textContent = actor.name + ' · ' + actor.role.toUpperCase() + ' · ' + actor.company;

        const emptyKind = emptyKindForCurrentView();
        if (emptyKind) {
            els.workspaceContent.innerHTML = emptyStateHtml(emptyKind);
            renderBulkToolbar();
            return;
        }
        els.workspaceContent.innerHTML = renderTable();
        renderBulkToolbar();
    }

    function fullRender() {
        renderPoolLockBanner();
        renderTabs();
        renderStatusChips();
        renderWorkspace();
        if (els.createOrderBtn) {
            els.createOrderBtn.href = bootstrap.routes.create;
            els.createOrderBtn.classList.toggle('hidden', !actor.can_create);
        }
        els.customDateRow.classList.toggle('is-visible', els.filterDatePreset.value === 'custom');
        els.advancedFilters.style.display = filtersVisible ? '' : 'none';
    }

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function selectedOrders() {
        return orders.filter(function (o) { return selectedOrderIds.includes(o.id); });
    }

    function openAssignModal() {
        const selected = selectedOrders();
        if (!selected.length) return;
        els.assignModalTitle.textContent = 'Assign ' + selected.length + ' order' + (selected.length === 1 ? '' : 's');
        els.assignOrderList.innerHTML = selected.map(function (o) {
            return '<li>' + escapeHtml(o.orderNumber) + '</li>';
        }).join('');
        openModal('assignModal');
    }

    function openPoolModal() {
        const selected = selectedOrders();
        if (!selected.length) return;
        els.poolModalTitle.textContent = 'Move ' + selected.length + ' order' + (selected.length === 1 ? '' : 's') + ' to Order Pool?';
        els.poolOrderList.innerHTML = selected.map(function (o) {
            return '<li>' + escapeHtml(o.orderNumber) + '</li>';
        }).join('');
        openModal('poolModal');
    }

    async function assignSelectedToCca() {
        const ccaId = Number(els.assignCcaSelect.value);
        if (!ccaId) return;
        els.confirmAssignBtn.disabled = true;
        try {
            await postJson(bootstrap.routes.bulk_assign, { order_ids: selectedOrderIds, cca_id: ccaId });
            window.location.reload();
        } catch (err) {
            showToast(err.message || 'Bulk assign failed.', 'danger');
            els.confirmAssignBtn.disabled = false;
        }
    }

    async function moveSelectedToPool() {
        els.confirmPoolBtn.disabled = true;
        try {
            await postJson(bootstrap.routes.bulk_pool, { order_ids: selectedOrderIds });
            window.location.reload();
        } catch (err) {
            showToast(err.message || 'Move to pool failed.', 'danger');
            els.confirmPoolBtn.disabled = false;
        }
    }

    async function assignToMe(orderId) {
        if (!canTakeFromPool()) {
            showToast('Update your current pool order status before taking another.', 'warning');
            return;
        }
        const order = orders.find(function (o) { return o.id === orderId; });
        if (!order || order.assignment !== 'pool') return;
        const btn = document.querySelector('[data-assign-me="' + orderId + '"]');
        if (btn) btn.disabled = true;
        try {
            await postJson(bootstrap.routes.claim + '/' + encodeURIComponent(order.uuid) + '/cca/claim', {});
            window.location.href = bootstrap.routes.index + '?' + buildQuery({ tab: 'my', page: 1 });
        } catch (err) {
            showToast(err.message || 'Unable to claim order.', 'danger');
            if (btn) btn.disabled = false;
        }
    }

    els.workspaceTabs.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-tab]');
        if (!btn) return;
        selectedOrderIds = [];
        navigate({ tab: btn.getAttribute('data-tab'), page: 1 });
    });

    els.statusChipRow.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-status-chip]');
        if (!btn) return;
        selectedOrderIds = [];
        navigate({ status: btn.getAttribute('data-status-chip') === 'all' ? '' : btn.getAttribute('data-status-chip'), page: 1 });
    });

    els.searchBtn.addEventListener('click', function () {
        selectedOrderIds = [];
        navigate({ search: els.searchInput.value.trim(), page: 1 });
    });

    els.searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            selectedOrderIds = [];
            navigate({ search: els.searchInput.value.trim(), page: 1 });
        }
    });

    els.clearBtn.addEventListener('click', function () {
        selectedOrderIds = [];
        navigate({ search: '', page: 1 });
    });

    els.toggleFiltersBtn.addEventListener('click', function () {
        filtersVisible = !filtersVisible;
        els.advancedFilters.style.display = filtersVisible ? '' : 'none';
        els.toggleFiltersBtn.textContent = filtersVisible ? 'Filters' : 'Show filters';
    });

    [els.filterAssignment, els.filterCca, els.filterSupplier, els.filterSource, els.filterDatePreset].forEach(function (el) {
        el.addEventListener('change', function () {
            selectedOrderIds = [];
            const overrides = { page: 1 };
            if (el === els.filterAssignment) overrides.assignment = el.value === 'all' ? '' : el.value;
            if (el === els.filterCca) overrides.cca_id = el.value === 'all' ? '' : el.value;
            if (el === els.filterSupplier) overrides.supplier_id = el.value === 'all' ? '' : el.value;
            if (el === els.filterSource) overrides.source = el.value === 'all' ? '' : el.value;
            if (el === els.filterDatePreset) overrides.date_preset = el.value === 'all' ? '' : el.value;
            navigate(overrides);
        });
    });

    [els.filterDateFrom, els.filterDateTo].forEach(function (el) {
        el.addEventListener('change', function () {
            if (els.filterDatePreset.value !== 'custom') return;
            selectedOrderIds = [];
            navigate({ date_from: els.filterDateFrom.value, date_to: els.filterDateTo.value, page: 1 });
        });
    });

    els.resetFiltersBtn.addEventListener('click', function () {
        selectedOrderIds = [];
        window.location.href = bootstrap.routes.index;
    });

    els.bulkAssignBtn.addEventListener('click', openAssignModal);
    els.bulkPoolBtn.addEventListener('click', openPoolModal);
    els.bulkClearBtn.addEventListener('click', function () {
        selectedOrderIds = [];
        fullRender();
    });

    els.confirmAssignBtn.addEventListener('click', assignSelectedToCca);
    els.confirmPoolBtn.addEventListener('click', moveSelectedToPool);

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(btn.getAttribute('data-close-modal')); });
    });

    [els.assignModal, els.poolModal].forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal(modal.id);
        });
    });

    els.workspaceContent.addEventListener('change', function (e) {
        const selectOrder = e.target.closest('[data-select-order]');
        if (selectOrder) {
            const id = Number(selectOrder.getAttribute('data-select-order'));
            if (selectOrder.checked) {
                if (!selectedOrderIds.includes(id)) selectedOrderIds.push(id);
            } else {
                selectedOrderIds = selectedOrderIds.filter(function (x) { return x !== id; });
            }
            fullRender();
            return;
        }
        if (e.target.id === 'selectAllOrders') {
            const pageIds = orders.map(function (o) { return o.id; });
            if (e.target.checked) {
                selectedOrderIds = Array.from(new Set(selectedOrderIds.concat(pageIds)));
            } else {
                selectedOrderIds = selectedOrderIds.filter(function (id) { return pageIds.indexOf(id) === -1; });
            }
            fullRender();
        }
    });

    els.workspaceContent.addEventListener('click', function (e) {
        const pageNav = e.target.closest('[data-page-nav]');
        if (pageNav && !pageNav.disabled) {
            const dir = pageNav.getAttribute('data-page-nav');
            const nextPage = bootstrap.pagination.current_page + (dir === 'next' ? 1 : -1);
            navigate({ page: nextPage });
            return;
        }
        const assignMe = e.target.closest('[data-assign-me]');
        if (assignMe && !assignMe.disabled) {
            assignToMe(Number(assignMe.getAttribute('data-assign-me')));
        }
    });

    populateFilterSelects();
    syncFilterControls();
    if (!filters.tab) {
        activeTab = defaultTabForRole();
        if (isCca() && activeTab === 'my') {
            navigate({ tab: 'my', page: 1 });
            return;
        }
    }
    fullRender();
})();
</script>
@endpush
@endunless
