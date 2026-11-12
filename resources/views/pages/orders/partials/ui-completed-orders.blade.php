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
    $completedSummaryCounts = $workspaceOrders['summary'] ?? [
        'delivered' => 0,
        'returned' => 0,
        'settlement_pending' => 0,
        'settled' => 0,
    ];
    $completedOrders = $workspaceOrders['orders'] ?? [];
    $completedCourierOptions = $workspaceOrders['couriers'] ?? [];
    $completedProductOptions = $workspaceOrders['products'] ?? [];
@endphp

<div
    class="main-content-container overflow-hidden orders-list-prototype"
    id="resellerCompletedOrdersPage"
    data-empty-message-all="No delivered or returned orders yet."
    data-empty-message-delivered="No delivered orders found."
    data-empty-message-returned="No returned orders found."
    data-empty-message-settlement-pending="No orders are currently settlement pending."
    data-empty-message-settled="No settled orders found."
    data-empty-message-search="No completed orders match your filters."
    data-per-page="25"
    data-orders='@json($completedOrders)'
>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
        <div>
            <h3 class="mb-1">Completed Orders</h3>
            <p class="fs-15 text-body mb-0">
                View delivered and returned orders and their reseller settlement status.
            </p>
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
                <span class="text-secondary">Completed Orders</span>
            </li>
        </ol>
    </nav>

    <div id="completedOrdersAlertHost" class="alert d-none" role="status"></div>

    <div class="ongoing-summary-cards" role="group" aria-label="Completed orders summary">
        <button type="button" class="ongoing-summary-card" data-summary-filter="delivered" aria-pressed="false">
            <div class="summary-label">Delivered</div>
            <p class="summary-count" id="completedSummaryDelivered">{{ number_format((int) $completedSummaryCounts['delivered']) }}</p>
            <div class="summary-supporting">Successfully delivered orders</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="returned" aria-pressed="false">
            <div class="summary-label">Returned</div>
            <p class="summary-count" id="completedSummaryReturned">{{ number_format((int) $completedSummaryCounts['returned']) }}</p>
            <div class="summary-supporting">Orders returned after dispatch</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="settlement_pending" aria-pressed="false">
            <div class="summary-label">Settlement Pending</div>
            <p class="summary-count" id="completedSummarySettlementPending">{{ number_format((int) $completedSummaryCounts['settlement_pending']) }}</p>
            <div class="summary-supporting">Orders awaiting reseller settlement</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-summary-filter="settled" aria-pressed="false">
            <div class="summary-label">Settled</div>
            <p class="summary-count" id="completedSummarySettled">{{ number_format((int) $completedSummaryCounts['settled']) }}</p>
            <div class="summary-supporting">Reseller settlement recorded</div>
        </button>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
        <div class="p-20">
            <form id="completedOrdersFilterForm">
                <div class="search-row">
                    <div class="search-input-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" class="form-control" id="completedFilterSearch" name="search"
                            placeholder="Search order, customer, phone, waybill or invoice"
                            autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary text-white">Search</button>
                    <button type="button" class="btn btn-light border" id="completedOrdersClearFilters">Clear</button>
                </div>

                <div class="filter-chip-row" id="completedOutcomeChips" aria-label="Outcome filters">
                    <button type="button" class="filter-chip is-active" data-outcome-chip="">All</button>
                    <button type="button" class="filter-chip" data-outcome-chip="DELIVERED">Delivered</button>
                    <button type="button" class="filter-chip" data-outcome-chip="RETURNED">Returned</button>
                </div>

                <div class="completed-settlement-note mb-3" role="note">
                    <p class="mb-2">
                        Completed Orders contains the recent completed-order window.
                        Older completed/settled history will appear under Archived Orders.
                    </p>
                    <p class="mb-0">
                        <span class="settlement-component-label is-plus">PLUS</span>
                        reseller earnings and positive settlement components.
                        <span class="settlement-component-label is-minus">MINUS</span>
                        returns, fines, and negative adjustments.
                    </p>
                </div>

                <div class="advanced-filters completed-advanced-filters" aria-label="Completed order filters">
                    <div>
                        <label class="label" for="completedFilterOutcome">Outcome</label>
                        <select class="form-select form-control" id="completedFilterOutcome" name="outcome">
                            <option value="">All</option>
                            <option value="DELIVERED">Delivered</option>
                            <option value="RETURNED">Returned</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="completedFilterSettlement">Settlement</label>
                        <select class="form-select form-control" id="completedFilterSettlement" name="settlement_status">
                            <option value="">All</option>
                            <option value="Settlement Pending">Settlement Pending</option>
                            <option value="Settled">Settled</option>
                        </select>
                        <div class="form-text completed-settlement-hint" id="completedSettlementFilterHint">
                            Reseller settlement for delivered and returned orders.
                        </div>
                    </div>
                    <div>
                        <label class="label" for="completedFilterProduct">Product</label>
                        <select class="form-select form-control" id="completedFilterProduct" name="product_id">
                            <option value="">All products</option>
                            @foreach ($completedProductOptions as $product)
                                <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="completedFilterCourier">Courier</label>
                        <select class="form-select form-control" id="completedFilterCourier" name="courier_id">
                            <option value="">All Couriers</option>
                            @foreach ($completedCourierOptions as $courier)
                                <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="completedFilterDateFrom">Date From</label>
                        <input type="date" class="form-control" id="completedFilterDateFrom" name="date_from">
                    </div>
                    <div>
                        <label class="label" for="completedFilterDateTo">Date To</label>
                        <input type="date" class="form-control" id="completedFilterDateTo" name="date_to">
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-light border w-100" id="completedOrdersResetFilters">Reset filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-4">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fs-18 mb-0">Completed Orders</h4>
                <div class="results-meta mt-1" id="completedOrdersResultsMeta"></div>
            </div>
        </div>

        <div>
            <div class="table-responsive">
                <table class="table align-middle orders-table" id="completedOrdersTable">
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
                            <th scope="col" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="completedOrdersTableBody">
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Loading completed orders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="completedOrdersPaginationHost"></div>
        </div>
    </div>
</div>

<div id="completedOrderDetailModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="completedOrderDetailModalTitle">
    <div class="modal-panel-proto">
        <div class="modal-head d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fs-16" id="completedOrderDetailModalTitle">Order detail</h5>
            <button type="button" class="btn btn-sm btn-light border" data-close-modal="completedOrderDetailModal" aria-label="Close">Close</button>
        </div>
        <div class="modal-body" id="completedOrderDetailModalBody"></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="completedOrderDetailModal">Close</button>
        </div>
    </div>
</div>
