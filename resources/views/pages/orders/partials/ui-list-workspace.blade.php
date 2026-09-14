@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));
    extract(require resource_path('views/pages/orders/partials/ui-mock-data.php'));
@endphp

@php
    $isCallCenterList = ($orderUiWorkspace ?? '') === 'call-center';
    $listTitle = $isCallCenterList ? 'Call Center Orders' : 'Archived Orders';
    $listCopy = $isCallCenterList
        ? 'Same Feeder order-list workspace, filtered to call-center assignment and attempt statuses.'
        : 'Completed, returned, and expired orders. Operational workflow still ends at Confirmed.';
    $archiveKey = $orderUiArchive ?? 'completed';
    $orderUiListMock = [
        'orders' => $orderUiMockOrders,
        'ccas' => $orderUiMockCcas,
        'suppliers' => $orderUiMockSuppliers,
        'products' => $orderUiMockProducts,
        'perPage' => 5,
        'workspace' => $isCallCenterList ? 'call-center' : 'archived',
        'archive' => $archiveKey,
        'showUrl' => $orderUiUrls['view'],
        'holdUrl' => $orderUiUrls['hold'],
        'expiringUrl' => $orderUiUrls['expiring'],
        'expiredUrl' => $orderUiUrls['expired'],
        'confirmUrl' => $orderUiUrls['confirm'],
    ];
@endphp

<div class="main-content-container overflow-hidden orders-list-prototype" id="orderUiListWorkspace"
    data-workspace="{{ $isCallCenterList ? 'call-center' : 'archived' }}"
    data-archive="{{ $archiveKey }}">
    @include('pages.orders.partials.ui-page-header', [
        'orderUiPageTitle' => $listTitle,
        'orderUiPageCopy' => $listCopy,
        'orderUiCrumbs' => $isCallCenterList
            ? [['label' => 'Call Center Orders']]
            : [
                ['label' => 'Archived Orders'],
                ['label' => ucfirst($archiveKey)],
            ],
        'orderUiPrimaryHref' => $isCallCenterList ? $orderUiUrls['create'] : null,
        'orderUiPrimaryLabel' => $isCallCenterList ? 'Create Order' : null,
        'orderUiSecondaryHref' => $orderUiUrls['ongoing'],
        'orderUiSecondaryLabel' => 'Ongoing Orders',
    ])

    <div class="order-ui-banner">
        <strong>UI architecture preview</strong>
        Counts, filters, and rows below use isolated example data. Opening View uses the existing single-order visual with a preview state — no new backend queries.
    </div>

    <div id="orderUiListToast" class="alert alert-success prototype-toast hidden" role="status"></div>

    <div class="workspace-tabs" id="orderUiWorkspaceTabs" role="tablist"></div>

    <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
        <div class="p-20">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" id="orderUiSearchInput" class="form-control"
                        placeholder="Search orders by number, customer, phone, CCA..."
                        autocomplete="off">
                </div>
                <button type="button" class="btn btn-primary text-white" id="orderUiSearchBtn">Search</button>
                <button type="button" class="btn btn-light border" id="orderUiClearBtn">Clear</button>
            </div>

            @if ($isCallCenterList)
                <div class="order-ui-global-search mt-2">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="orderUiGlobalSearch" value="1">
                        <label class="form-check-label fs-13" for="orderUiGlobalSearch">
                            Search global orders
                            <span class="order-sub d-inline">Include orders placed by other resellers (view details only)</span>
                        </label>
                    </div>
                </div>
            @endif

            <div class="filter-chip-row" id="orderUiStatusChips" aria-label="Status filters"></div>

            @if ($isCallCenterList)
                <div class="advanced-filters" id="orderUiAdvancedFilters" aria-label="Call center filters">
                    <div>
                        <label class="label" for="orderUiFilterCca">CCA</label>
                        <select id="orderUiFilterCca" class="form-select form-control">
                            <option value="all">All CCAs</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="orderUiFilterSupplier">Supplier</label>
                        <select id="orderUiFilterSupplier" class="form-select form-control">
                            <option value="all">All suppliers</option>
                        </select>
                    </div>
                    <div class="order-ui-product-filter">
                        <label class="label" for="orderUiFilterProduct">Product</label>
                        <div class="order-ui-autocomplete">
                            <input type="text" id="orderUiFilterProduct" class="form-control"
                                placeholder="Type product name or code..."
                                autocomplete="off"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="orderUiProductSuggestions">
                            <ul id="orderUiProductSuggestions" class="order-ui-autocomplete-list hidden" role="listbox"></ul>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="orderUiFilterDateFrom">Date from</label>
                        <input type="date" id="orderUiFilterDateFrom" class="form-control">
                    </div>
                    <div>
                        <label class="label" for="orderUiFilterDateTo">Date to</label>
                        <input type="date" id="orderUiFilterDateTo" class="form-control">
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-light border w-100" id="orderUiResetFiltersBtn">Reset filters</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($isCallCenterList)
        <div id="orderUiBulkToolbar" class="bulk-toolbar hidden">
            <div class="bulk-count"><span id="orderUiBulkSelectedCount">0</span> orders selected</div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-primary btn-sm text-white" id="orderUiBulkAssignBtn">Assign to CCA</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="orderUiBulkPoolBtn">Move to Order Pool</button>
                <button type="button" class="btn btn-light border btn-sm" id="orderUiBulkClearBtn">Clear Selection</button>
            </div>
        </div>
    @endif

    <div class="card bg-white rounded-10 border border-white mb-4">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fs-18 mb-0" id="orderUiWorkspaceTitle">{{ $listTitle }}</h4>
                <div class="results-meta mt-1" id="orderUiResultsMeta"></div>
            </div>
        </div>
        <div class="p-20" id="orderUiWorkspaceContent"></div>
    </div>
</div>

@if ($isCallCenterList)
    <div id="orderUiBulkAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="orderUiBulkAssignModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="orderUiBulkAssignModalTitle">Assign selected orders</h5>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-2">
                    Choose a CCA for the selected orders. This action is visual only on this pass.
                </p>
                <ul class="selected-order-list" id="orderUiBulkAssignOrderList"></ul>
                <div class="mt-3">
                    <label class="label fs-14 mb-2" for="orderUiBulkAssignCcaSelect">Select CCA</label>
                    <select id="orderUiBulkAssignCcaSelect" class="form-select form-control"></select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="orderUiBulkAssignModal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="orderUiConfirmBulkAssignBtn">Assign to CCA</button>
            </div>
        </div>
    </div>

    <div id="orderUiBulkPoolModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="orderUiBulkPoolModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="orderUiBulkPoolModalTitle">Move to Order Pool</h5>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-2">
                    Selected orders will become available for any eligible CCA to claim. Visual only on this pass.
                </p>
                <ul class="selected-order-list" id="orderUiBulkPoolOrderList"></ul>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="orderUiBulkPoolModal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="orderUiConfirmBulkPoolBtn">Move to Pool</button>
            </div>
        </div>
    </div>
@endif

<script type="application/json" id="orderUiListMock">@json($orderUiListMock)</script>
