@php
    $workspaceOrders = $workspaceOrders ?? [
        'orders' => [],
        'summary' => [
            'PRINT' => 0,
            'PRINTED' => 0,
            'PACKING' => 0,
            'DISPATCHED' => 0,
        ],
        'couriers' => [],
        'products' => [],
    ];
    $ongoingSummaryCounts = $workspaceOrders['summary'] ?? [
        'PRINT' => 0,
        'PRINTED' => 0,
        'PACKING' => 0,
        'DISPATCHED' => 0,
    ];
    $ongoingOrders = $workspaceOrders['orders'] ?? [];
    $ongoingCourierOptions = $workspaceOrders['couriers'] ?? [];
    $ongoingProductOptions = $workspaceOrders['products'] ?? [];
@endphp

<div
    class="main-content-container overflow-hidden orders-list-prototype"
    id="resellerOngoingOrdersPage"
    data-empty-message-all="No orders are currently in fulfillment."
    data-empty-message-print="No orders are currently waiting for printing."
    data-empty-message-printed="No printed orders are waiting for packing."
    data-empty-message-packing="No orders are currently being packed."
    data-empty-message-dispatched="No dispatched orders are currently in progress."
    data-empty-message-search="No ongoing orders match your filters."
    data-per-page="25"
    data-orders='@json($ongoingOrders)'
>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
        <div>
            <h3 class="mb-1">Ongoing Orders</h3>
            <p class="fs-15 text-body mb-0">
                Track orders currently moving through fulfillment and dispatch.
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
                <span class="text-secondary">Ongoing Orders</span>
            </li>
        </ol>
    </nav>

    <div class="alert alert-light border mb-3" role="note">
        <div class="d-flex align-items-start gap-2">
            <span class="material-symbols-outlined text-primary mt-1" style="font-size: 20px;" aria-hidden="true">info</span>
            <div>
                <div class="fw-semibold fs-14 mb-1">Fulfillment tracking</div>
                <p class="fs-13 text-body mb-0">
                    Ongoing Orders shows only PRINT, PRINTED, PACKING, and DISPATCHED.
                    Call Center and Completed Orders handle pre-print and post-delivery workflows separately.
                </p>
            </div>
        </div>
    </div>

    <div id="ongoingOrdersAlertHost" class="alert d-none" role="status"></div>

    <div class="ongoing-summary-cards" role="group" aria-label="Fulfillment status summary">
        <button type="button" class="ongoing-summary-card" data-status-filter="PRINT" aria-pressed="false">
            <div class="summary-label">Printing</div>
            <p class="summary-count" id="ongoingSummaryPrint">{{ number_format((int) $ongoingSummaryCounts['PRINT']) }}</p>
            <div class="summary-supporting">PRINT</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-status-filter="PRINTED" aria-pressed="false">
            <div class="summary-label">Printed</div>
            <p class="summary-count" id="ongoingSummaryPrinted">{{ number_format((int) $ongoingSummaryCounts['PRINTED']) }}</p>
            <div class="summary-supporting">PRINTED</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-status-filter="PACKING" aria-pressed="false">
            <div class="summary-label">Packing</div>
            <p class="summary-count" id="ongoingSummaryPacking">{{ number_format((int) $ongoingSummaryCounts['PACKING']) }}</p>
            <div class="summary-supporting">PACKING</div>
        </button>
        <button type="button" class="ongoing-summary-card" data-status-filter="DISPATCHED" aria-pressed="false">
            <div class="summary-label">Dispatched</div>
            <p class="summary-count" id="ongoingSummaryDispatched">{{ number_format((int) $ongoingSummaryCounts['DISPATCHED']) }}</p>
            <div class="summary-supporting">DISPATCHED</div>
        </button>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
        <div class="p-20">
            <form id="ongoingOrdersFilterForm">
                <div class="search-row">
                    <div class="search-input-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" class="form-control" id="ongoingFilterSearch" name="search"
                            placeholder="Search order, customer, phone or waybill"
                            autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary text-white">Search</button>
                    <button type="button" class="btn btn-light border" id="ongoingOrdersClearFilters">Clear</button>
                </div>

                <div class="filter-chip-row" id="ongoingStatusChips" aria-label="Status filters">
                    <button type="button" class="filter-chip is-active" data-status-chip="">All</button>
                    <button type="button" class="filter-chip" data-status-chip="PRINT">Print</button>
                    <button type="button" class="filter-chip" data-status-chip="PRINTED">Printed</button>
                    <button type="button" class="filter-chip" data-status-chip="PACKING">Packing</button>
                    <button type="button" class="filter-chip" data-status-chip="DISPATCHED">Dispatched</button>
                </div>

                <div class="advanced-filters" aria-label="Ongoing order filters">
                    <div>
                        <label class="label" for="ongoingFilterStatus">Status</label>
                        <select class="form-select form-control" id="ongoingFilterStatus" name="status">
                            <option value="">All</option>
                            <option value="PRINT">Print</option>
                            <option value="PRINTED">Printed</option>
                            <option value="PACKING">Packing</option>
                            <option value="DISPATCHED">Dispatched</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ongoingFilterProduct">Product</label>
                        <select class="form-select form-control" id="ongoingFilterProduct" name="product_id">
                            <option value="">All products</option>
                            @foreach ($ongoingProductOptions as $product)
                                <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ongoingFilterCourier">Courier</label>
                        <select class="form-select form-control" id="ongoingFilterCourier" name="courier_id">
                            <option value="">All Couriers</option>
                            @foreach ($ongoingCourierOptions as $courier)
                                <option value="{{ $courier['id'] }}">{{ $courier['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ongoingFilterDateFrom">Date From</label>
                        <input type="date" class="form-control" id="ongoingFilterDateFrom" name="date_from">
                    </div>
                    <div>
                        <label class="label" for="ongoingFilterDateTo">Date To</label>
                        <input type="date" class="form-control" id="ongoingFilterDateTo" name="date_to">
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-light border w-100" id="ongoingOrdersResetFilters">Reset filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-4">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fs-18 mb-0">Ongoing Orders</h4>
                <div class="results-meta mt-1" id="ongoingOrdersResultsMeta"></div>
            </div>
        </div>

        <div>
            <div class="table-responsive">
                <table class="table align-middle orders-table" id="ongoingOrdersTable">
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Product</th>
                            <th scope="col">Courier</th>
                            <th scope="col">Waybill</th>
                            <th scope="col">COD Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ongoingOrdersTableBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Loading ongoing orders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="ongoingOrdersPaginationHost"></div>
        </div>
    </div>
</div>
