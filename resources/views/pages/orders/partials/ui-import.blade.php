@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));
    extract(require resource_path('views/pages/orders/partials/ui-mock-data.php'));
@endphp

<div class="main-content-container overflow-hidden orders-list-prototype" id="orderImportUi">
    @include('pages.orders.partials.ui-page-header', [
        'orderUiPageTitle' => 'Import Orders',
        'orderUiPageCopy' => 'Upload a spreadsheet, preview rows, then review validation before any orders are created.',
        'orderUiCrumbs' => [
            ['label' => 'New Orders', 'href' => $orderUiUrls['new']],
            ['label' => 'Import Orders'],
        ],
        'orderUiPrimaryHref' => $orderUiUrls['create'],
        'orderUiPrimaryLabel' => 'Create Order',
        'orderUiSecondaryHref' => $orderUiUrls['new'],
        'orderUiSecondaryLabel' => 'New Orders',
    ])

    <div class="order-ui-banner" id="orderImportBanner">
        <strong>Import Orders</strong>
        Upload a spreadsheet to validate rows. <code>delivery</code> is the courier fee. <code>item_code</code> is the product variant ID. Valid rows create PENDING orders when you import.
    </div>

    <div class="card bg-white rounded-10 border border-white mb-3">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="fs-18 mb-0">Upload</h4>
            <button type="button" class="btn btn-light border btn-sm" id="orderImportTemplateBtn">Download template</button>
        </div>
        <div class="p-20">
            <div class="order-ui-upload" id="orderImportDropzone">
                <span class="material-symbols-outlined d-block">cloud_upload</span>
                <h4 class="fs-18 mb-2">Drop Excel or CSV here</h4>
                <p class="fs-14 text-body mb-3">
                    Expected columns: name, address, tp_1, tp_2, price, qty, item_code, delivery
                </p>
                <label class="btn btn-primary text-white mb-0">
                    Choose file
                    <input type="file" id="orderImportFile" class="d-none" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                </label>
                <p class="fs-13 text-body mt-3 mb-0" id="orderImportFileName">No file selected.</p>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="fs-13 text-body">item_code</div>
                    <div class="fw-medium">Product variant ID</div>
                </div>
                <div class="col-md-6">
                    <div class="fs-13 text-body">delivery</div>
                    <div class="fw-medium">Courier fee — not a delivery method or status</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-white rounded-10 border border-white mb-3">
        <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fs-18 mb-0">Preview</h4>
                <div class="results-meta mt-1" id="orderImportSummary">Upload a file to preview validation results</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="order-ui-import-state is-valid">Valid</span>
                <span class="order-ui-import-state is-banned">Banned</span>
                <span class="order-ui-import-state is-invalid">Invalid</span>
                <span class="order-ui-import-state is-created">Created</span>
            </div>
        </div>
        <div class="p-20">
            <div class="table-responsive">
                <table class="orders-table align-middle">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Customer</th>
                            <th>Phones</th>
                            <th>Address</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Item code</th>
                            <th>Courier fee</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody id="orderImportPreviewBody">
                        <tr id="orderImportEmptyRow">
                            <td colspan="9" class="text-body">No import rows yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2 mt-3">
                <button type="button" class="btn btn-light border" id="orderImportClearBtn">Clear file</button>
                <button type="button" class="btn btn-primary text-white" id="orderImportSubmitBtn">Import orders</button>
            </div>
        </div>
    </div>

    @php
        $importAssignmentPayload = $importAssignment ?? [
            'can_assign' => false,
            'ccas' => [],
            'unassigned_count' => 0,
            'routes' => [],
            'csrf' => csrf_token(),
        ];
    @endphp

    @if (! empty($importAssignmentPayload['can_assign']))
        <div class="card bg-white rounded-10 border border-white mb-3 mt-3" id="orderImportAssignPanel">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-1">Assign imported orders</h4>
                <div class="results-meta" id="orderImportAssignMeta">
                    Create orders first, then assign selected rows, send them to the pool, or assign a random quantity of unassigned company orders.
                </div>
            </div>
            <div class="p-20">
                <div class="table-responsive mb-3">
                    <table class="orders-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 2.5rem;">
                                    <input type="checkbox" class="form-check-input" id="orderImportAssignSelectAll" disabled>
                                </th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Items</th>
                                <th>Supplier</th>
                                <th>Existing stock</th>
                            </tr>
                        </thead>
                        <tbody id="orderImportAssignBody">
                            <tr id="orderImportAssignEmptyRow">
                                <td colspan="7" class="text-body">No created orders ready to assign yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-4">
                        <label class="label fs-14 mb-2" for="orderImportAssignCca">Select CCA</label>
                        <select id="orderImportAssignCca" class="form-select form-control">
                            <option value="">Choose CCA</option>
                        </select>
                    </div>
                    <div class="col-lg-9 col-md-8">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-primary text-white" id="orderImportAssignSelectedBtn">Assign selected</button>
                            <button type="button" class="btn btn-outline-primary" id="orderImportPoolSelectedBtn">Send selected to pool</button>
                            <button type="button" class="btn btn-light border" id="orderImportAssignRandomBtn">Assign random quantity</button>
                        </div>
                        <div class="fs-13 text-body mt-2 mb-0" id="orderImportUnassignedHint"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="orderImportRandomAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="orderImportRandomAssignModalTitle">
            <div class="modal-panel-proto" style="width: min(560px, 100%);">
                <div class="modal-head">
                    <h5 class="mb-0 fs-16" id="orderImportRandomAssignModalTitle">Assign random quantity</h5>
                </div>
                <div class="modal-body">
                    <div class="fs-14 text-body mb-3" id="orderImportRandomAssignRemaining">
                        Remaining unassigned orders: 0
                    </div>
                    <div class="table-responsive">
                        <table class="orders-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Call center agent</th>
                                    <th style="width: 8rem;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="orderImportRandomAssignCcaBody">
                                <tr>
                                    <td colspan="2" class="text-body">No eligible call center agents available.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="fs-13 text-body mt-3 mb-0" id="orderImportRandomAssignTotalHint">
                        Total selected: 0
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-light border" data-close-modal="orderImportRandomAssignModal">Cancel</button>
                    <button type="button" class="btn btn-primary text-white" id="orderImportRandomAssignConfirmBtn">Assign</button>
                </div>
            </div>
        </div>
    @endif
</div>

<div id="orderImportToast" class="alert alert-success prototype-toast hidden" role="status"></div>

<script type="application/json" id="orderImportBootstrap">
{!! json_encode([
    'urls' => [
        'template' => route('orders.import.template'),
        'upload' => route('orders.import.upload'),
        'process' => url('/orders/import/:batch/process'),
        'show' => url('/orders/import/:batch'),
    ],
    'csrf' => csrf_token(),
    'assignment' => $importAssignmentPayload,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
