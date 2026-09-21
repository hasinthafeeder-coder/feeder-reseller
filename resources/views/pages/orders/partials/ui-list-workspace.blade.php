@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));
@endphp

@php
    $isCallCenterList = ($orderUiWorkspace ?? '') === 'call-center';
    $listTitle = $isCallCenterList ? 'Call Center Orders' : 'Archived Orders';
    $listCopy = $isCallCenterList
        ? 'Assigned and pool orders for call-center handling within your reseller company.'
        : 'Completed, returned, and expired orders. Operational workflow still ends at Confirmed.';
    $archiveKey = $orderUiArchive ?? 'completed';

    if ($isCallCenterList) {
        $orderUiListPayload = $bootstrap ?? [
            'workspace' => 'call-center',
            'orders' => [],
            'ccas' => [],
            'suppliers' => [],
            'statuses' => [],
            'sources' => [],
            'counts' => ['all' => 0, 'assigned' => 0, 'unassigned' => 0, 'pool' => 0, 'my' => 0],
            'status_counts' => ['all' => 0],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
            'filters' => [],
            'actor' => ['role' => 'reseller', 'can_assign' => false, 'can_claim' => false, 'can_create' => false],
            'pool_lock' => null,
            'routes' => [
                'index' => route('orders.index', ['workspace' => 'call-center']),
                'show' => url('/orders'),
                'create' => route('orders.create'),
                'bulk_assign' => route('orders.bulk.assign'),
                'bulk_pool' => route('orders.bulk.pool'),
                'claim' => url('/orders'),
            ],
        ];
        $orderUiListPayload['workspace'] = 'call-center';
        $orderUiListPayload['live'] = true;
    } else {
        extract(require resource_path('views/pages/orders/partials/ui-mock-data.php'));
        $orderUiListPayload = [
            'orders' => $orderUiMockOrders,
            'ccas' => $orderUiMockCcas,
            'suppliers' => $orderUiMockSuppliers,
            'products' => $orderUiMockProducts,
            'perPage' => 5,
            'workspace' => 'archived',
            'archive' => $archiveKey,
            'live' => false,
            'showUrl' => $orderUiUrls['view'],
            'holdUrl' => $orderUiUrls['hold'],
            'expiringUrl' => $orderUiUrls['expiring'],
            'expiredUrl' => $orderUiUrls['expired'],
            'confirmUrl' => $orderUiUrls['confirm'],
        ];
    }

    $actorCanAssign = (bool) ($orderUiListPayload['actor']['can_assign'] ?? false);
    $actorCanCreate = (bool) ($orderUiListPayload['actor']['can_create'] ?? false);
@endphp

<div class="main-content-container overflow-hidden orders-list-prototype" id="orderUiListWorkspace"
    data-workspace="{{ $isCallCenterList ? 'call-center' : 'archived' }}"
    data-archive="{{ $archiveKey }}"
    data-live="{{ $isCallCenterList ? '1' : '0' }}">
    @include('pages.orders.partials.ui-page-header', [
        'orderUiPageTitle' => $listTitle,
        'orderUiPageCopy' => $listCopy,
        'orderUiCrumbs' => $isCallCenterList
            ? [['label' => 'Call Center Orders']]
            : [
                ['label' => 'Archived Orders'],
                ['label' => ucfirst($archiveKey)],
            ],
        'orderUiPrimaryHref' => ($isCallCenterList && $actorCanCreate) ? $orderUiUrls['create'] : null,
        'orderUiPrimaryLabel' => ($isCallCenterList && $actorCanCreate) ? 'Create Order' : null,
        'orderUiSecondaryHref' => $orderUiUrls['ongoing'],
        'orderUiSecondaryLabel' => 'Ongoing Orders',
    ])

    @unless ($isCallCenterList)
        <div class="order-ui-banner">
            <strong>UI architecture preview</strong>
            Counts, filters, and rows below use isolated example data. Opening View uses the existing single-order visual with a preview state — no new backend queries.
        </div>
    @endunless

    <div id="orderUiListToast" class="alert alert-success prototype-toast hidden" role="status"></div>

    @if ($isCallCenterList)
        <div id="orderUiPoolLockBanner" class="pool-lock-banner hidden" role="status"></div>
    @endif

    <div class="workspace-tabs" id="orderUiWorkspaceTabs" role="tablist"></div>

    <div class="card bg-white rounded-10 border border-white mb-3 search-filter-card">
        <div class="p-20">
            <div class="search-row">
                <div class="search-input-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="search" id="orderUiSearchInput" class="form-control"
                        placeholder="Search orders by number, customer, phone, CCA..."
                        autocomplete="off"
                        value="{{ $isCallCenterList ? ($orderUiListPayload['filters']['search'] ?? '') : '' }}">
                </div>
                <button type="button" class="btn btn-primary text-white" id="orderUiSearchBtn">Search</button>
                <button type="button" class="btn btn-light border" id="orderUiClearBtn">Clear</button>
            </div>

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
                    <div>
                        <label class="label" for="orderUiFilterDatePreset">Date</label>
                        <select id="orderUiFilterDatePreset" class="form-select form-control">
                            <option value="all">All time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7">Last 7 Days</option>
                            <option value="last30">Last 30 Days</option>
                            <option value="custom">Custom</option>
                        </select>
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

    @if ($isCallCenterList && $actorCanAssign)
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
            @if ($isCallCenterList)
                <div class="results-meta" id="orderUiUserContextMeta"></div>
            @endif
        </div>
        <div class="p-20" id="orderUiWorkspaceContent"></div>
    </div>
</div>

@if ($isCallCenterList && $actorCanAssign)
    <div id="orderUiBulkAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="orderUiBulkAssignModalTitle">
        <div class="modal-panel-proto">
            <div class="modal-head">
                <h5 class="mb-0 fs-16" id="orderUiBulkAssignModalTitle">Assign selected orders</h5>
            </div>
            <div class="modal-body">
                <p class="fs-14 text-body mb-2">
                    Choose a CCA for the selected currently unassigned orders.
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
                    Selected unassigned orders will become available for any eligible CCA to claim.
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

<script type="application/json" id="orderUiListMock">@json($orderUiListPayload)</script>
