@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

<div class="main-content-container overflow-hidden">
    @include('pages.orders.partials.ui-page-header', [
        'orderUiPageTitle' => 'New Orders',
        'orderUiPageCopy' => 'Create a single order or import a spreadsheet. Both continue into the same Feeder order form.',
        'orderUiCrumbs' => [
            ['label' => 'New Orders'],
        ],
        'orderUiPrimaryHref' => $orderUiUrls['create'],
        'orderUiPrimaryLabel' => 'Create Order',
        'orderUiSecondaryHref' => $orderUiUrls['ongoing'],
        'orderUiSecondaryLabel' => 'Ongoing Orders',
    ])

    <div class="order-ui-banner">
        <strong>UI architecture preview</strong>
        These entry points open the live Create Order and Import Orders workspaces.
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card bg-white rounded-10 border border-white order-ui-new-card">
                <div class="p-20">
                    <span class="material-symbols-outlined d-block mb-3">add_shopping_cart</span>
                    <h4 class="fs-18 mb-2">Create Order</h4>
                    <p class="fs-14 text-body mb-4">
                        Open the existing single-order form for customer, phone, address, products, courier, and call-center assignment.
                    </p>
                    <a href="{{ $orderUiUrls['create'] }}" class="btn btn-primary text-white">Create Order</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-white rounded-10 border border-white order-ui-new-card">
                <div class="p-20">
                    <span class="material-symbols-outlined d-block mb-3">upload_file</span>
                    <h4 class="fs-18 mb-2">Import Orders</h4>
                    <p class="fs-14 text-body mb-4">
                        Upload Excel/CSV with name, address, phones, item code, quantity, price, and courier fee (delivery).
                    </p>
                    <a href="{{ $orderUiUrls['import'] }}" class="btn btn-light border">Open Import</a>
                </div>
            </div>
        </div>
    </div>
</div>
