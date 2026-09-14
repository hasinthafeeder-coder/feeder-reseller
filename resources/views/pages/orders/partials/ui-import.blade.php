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

    <div class="order-ui-banner">
        <strong>UI architecture preview</strong>
        Upload, preview, and validation states are visual only. `delivery` is the courier fee. `item_code` is the product variant ID. No import tables or processing run from this screen.
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
                <div class="results-meta mt-1">Example rows showing future validation states</div>
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
                    <tbody>
                        @foreach ($orderUiMockImportRows as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td>
                                <td>
                                    <div class="customer-name">{{ $row['name'] !== '' ? $row['name'] : '—' }}</div>
                                </td>
                                <td>
                                    <div>{{ $row['tp_1'] !== '' ? $row['tp_1'] : '—' }}</div>
                                    <div class="order-sub">{{ $row['tp_2'] !== '' ? $row['tp_2'] : 'No secondary' }}</div>
                                </td>
                                <td>{{ $row['address'] !== '' ? $row['address'] : '—' }}</td>
                                <td class="amount-value">{{ $row['price'] !== '' ? 'LKR '.$row['price'] : '—' }}</td>
                                <td>{{ $row['qty'] ?: '—' }}</td>
                                <td>{{ $row['item_code'] !== '' ? $row['item_code'] : '—' }}</td>
                                <td>{{ $row['delivery'] !== '' ? 'LKR '.$row['delivery'] : '—' }}</td>
                                <td>
                                    <span class="order-ui-import-state is-{{ $row['state'] }}">{{ $row['stateLabel'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="order-create-actions">
        <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
            <button type="button" class="btn btn-light border" id="orderImportClearBtn">Clear file</button>
            <button type="button" class="btn btn-primary text-white" id="orderImportSubmitBtn">Import orders</button>
        </div>
    </div>
</div>

<div id="orderImportToast" class="alert alert-success prototype-toast hidden" role="status"></div>
