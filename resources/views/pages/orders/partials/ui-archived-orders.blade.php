@php
    $workspaceOrders = $workspaceOrders ?? [
        'orders' => [],
        'summary' => [
            'delivered' => 0,
            'returned' => 0,
            'settlement_pending' => 0,
            'settled' => 0,
        ],
        'couriers' => [],
        'products' => [],
    ];
    $archivedSummaryCounts = $workspaceOrders['summary'] ?? [
        'delivered' => 0,
        'returned' => 0,
        'settlement_pending' => 0,
        'settled' => 0,
    ];
    $archivedOrders = $workspaceOrders['orders'] ?? [];
    $archivedCourierOptions = $workspaceOrders['couriers'] ?? [];
    // workspace=archived is the Completed Orders URL (compatibility label).
    $archivedCompletedOrdersUrl = route('orders.index', ['workspace' => 'archived']);
@endphp

<div
    class="main-content-container overflow-hidden orders-list-prototype"
    id="resellerArchivedOrdersPage"
    data-empty-message-all="No archived orders found."
    data-empty-message-delivered="No archived delivered orders found."
    data-empty-message-returned="No archived returned orders found."
    data-empty-message-settlement-pending="No archived orders are currently settlement pending."
    data-empty-message-settled="No settled archived orders found."
    data-empty-message-search="No archived orders match your filters."
    data-per-page="25"
    data-orders='@json($archivedOrders)'
>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
        <div>
            <h3 class="mb-1">Archived Orders</h3>
            <p class="fs-15 text-body mb-0">
                Historical completed-order and reseller-settlement history outside the active Completed Orders window.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a
                href="{{ $archivedCompletedOrdersUrl }}"
                class="btn btn-light border"
                title="View recent completed orders in Completed Orders"
            >
                <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;" aria-hidden="true">history</span>
                View Completed Orders
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
                <span class="text-secondary">Archived Orders</span>
            </li>
        </ol>
    </nav>

    <div id="archivedOrdersAlertHost" class="alert d-none" role="status"></div>

    <div class="alert alert-light border mb-3" role="note">
        <div class="d-flex align-items-start gap-2">
            <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
            <div>
                <div class="fw-semibold fs-14 mb-1">Completed-order archive</div>
                <p class="fs-13 text-body mb-2">
                    Completed Orders contains the recent completed-order window.
                    Archived Orders contains older completed/settled history.
                </p>
                <p class="fs-13 text-body mb-0">
                    Archived records are historical/read-only.
                    No operational order actions are available here.
                </p>
            </div>
        </div>
    </div>

    <div class="ongoing-summary-cards" role="group" aria-label="Archived orders summary">
        <button type="button" class="ongoing-summary-card" data-summary-filter="delivered" aria-pressed="false">
            <div class="summary-label">Delivered</div>
            <p class="summary-count" id="archivedSummaryDelivered">{{ number_format((int) $archivedSummaryCounts['delivered']) }}</p>
            <div class="summary-supporting">Historical delivered orders</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="returned" aria-pressed="false">
            <div class="summary-label">Returned</div>
            <p class="summary-count" id="archivedSummaryReturned">{{ number_format((int) $archivedSummaryCounts['returned']) }}</p>
            <div class="summary-supporting">Historical returned orders</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="settlement_pending" aria-pressed="false">
            <div class="summary-label">Settlement Pending</div>
            <p class="summary-count" id="archivedSummarySettlementPending">{{ number_format((int) $archivedSummaryCounts['settlement_pending']) }}</p>
            <div class="summary-supporting">Orders awaiting reseller settlement</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="settled" aria-pressed="false">
            <div class="summary-label">Settled</div>
            <p class="summary-count" id="archivedSummarySettled">{{ number_format((int) $archivedSummaryCounts['settled']) }}</p>
            <div class="summary-supporting">Reseller settlement recorded</div>
        </button>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
        <div class="p-20">
            <form id="archivedOrdersFilterForm">
                <div class="search-row">
                    <div class="search-input-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" class="form-control" id="archivedFilterSearch" name="search"
                            placeholder="Search order, customer, phone, waybill or invoice"
                            autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary text-white">Search</button>
                    <button type="button" class="btn btn-light border" id="archivedOrdersClearFilters">Clear</button>
                </div>

                <div class="filter-chip-row" id="archivedOutcomeChips" aria-label="Outcome filters">
                    <button type="button" class="filter-chip is-active" data-outcome-chip="">All</button>
                    <button type="button" class="filter-chip" data-outcome-chip="DELIVERED">Delivered</button>
                    <button type="button" class="filter-chip" data-outcome-chip="RETURNED">Returned</button>
                </div>

                <div class="completed-settlement-note mb-3" role="note">
                    <p class="mb-2">
                        Archived Orders is historical completed/settled history.
                        Recent completed orders remain under Completed Orders.
                    </p>
                    <p class="mb-0">
                        <span class="settlement-component-label is-plus">PLUS</span>
                        reseller earnings and positive settlement components.
                        <span class="settlement-component-label is-minus">MINUS</span>
                        returns, fines, and negative adjustments.
                    </p>
                </div>

                <div class="advanced-filters completed-advanced-filters" aria-label="Archived order filters">
                    <div>
                        <label class="label" for="archivedFilterOutcome">Outcome</label>
                        <select class="form-select form-control" id="archivedFilterOutcome" name="outcome">
                            <option value="">All</option>
                            <option value="DELIVERED">Delivered</option>
                            <option value="RETURNED">Returned</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="archivedFilterSettlement">Settlement</label>
                        <select class="form-select form-control" id="archivedFilterSettlement" name="settlement_status">
                            <option value="">All</option>
                            <option value="Settlement Pending">Settlement Pending</option>
                            <option value="Settled">Settled</option>
                        </select>
                        <div class="form-text completed-settlement-hint" id="archivedSettlementFilterHint">
                            Reseller settlement for historical delivered and returned orders.
                        </div>
                    </div>
                    <div>
                        <label class="label" for="archivedFilterCourier">Courier</label>
                        <select class="form-select form-control" id="archivedFilterCourier" name="courier_id">
                            <option value="">All Couriers</option>
                            @foreach ($archivedCourierOptions as $courier)
                                <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="archivedFilterDateFrom">Date From</label>
                        <input type="date" class="form-control" id="archivedFilterDateFrom" name="date_from">
                    </div>
                    <div>
                        <label class="label" for="archivedFilterDateTo">Date To</label>
                        <input type="date" class="form-control" id="archivedFilterDateTo" name="date_to">
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-light border w-100" id="archivedOrdersResetFilters">Reset filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-4">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fs-18 mb-0">Archived Orders</h4>
                <div class="results-meta mt-1" id="archivedOrdersResultsMeta"></div>
            </div>
        </div>

        <div class="p-20">
            <div class="table-responsive">
                <table class="table align-middle orders-table" id="archivedOrdersTable">
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Product</th>
                            <th scope="col">Courier</th>
                            <th scope="col">Waybill</th>
                            <th scope="col">COD Amount</th>
                            <th scope="col">Outcome</th>
                            <th scope="col">Settlement</th>
                            <th scope="col">Completed At</th>
                            <th scope="col" class="text-end">View</th>
                        </tr>
                    </thead>
                    <tbody id="archivedOrdersTableBody">
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Loading archived orders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="archivedOrdersPaginationHost"></div>
        </div>
    </div>
</div>

<div id="archivedOrderDetailModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="archivedOrderDetailModalTitle">
    <div class="modal-panel-proto">
        <div class="modal-head d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fs-16" id="archivedOrderDetailModalTitle">Order detail</h5>
            <button type="button" class="btn btn-sm btn-light border" data-close-modal="archivedOrderDetailModal" aria-label="Close">Close</button>
        </div>
        <div class="modal-body" id="archivedOrderDetailModalBody"></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="archivedOrderDetailModal">Close</button>
        </div>
    </div>
</div>
