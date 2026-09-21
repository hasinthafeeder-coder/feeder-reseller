@extends('layout_main.app')

@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

@push('styles')
    @include('pages.orders.partials.ui-manual-order-styles')
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

        @include('pages.orders.partials.ui-manual-order-form', [
            'orderFormMode' => 'create',
            'formDefaults' => [],
            'formAction' => $catalogRoutes['store'],
            'catalogRoutes' => $catalogRoutes,
            'canBanCustomer' => $canBanCustomer ?? false,
            'canAssignCourier' => $canAssignCourier ?? false,
            'shipmentBootstrap' => $shipmentBootstrap ?? null,
        ])
        <div id="orderUiToast" class="alert alert-success prototype-toast hidden" role="status"></div>
    </div>
@endif
@endsection

@unless ($orderUiScreen)
@push('scripts')
    @include('pages.orders.partials.ui-manual-order-scripts', [
        'orderFormMode' => 'create',
        'formDefaults' => [],
        'markets' => $markets,
        'eligibleCcas' => $eligibleCcas,
        'isCca' => $isCca,
        'catalogRoutes' => $catalogRoutes,
        'duplicateOrders' => $duplicateOrders ?? [],
        'oldItems' => $oldItems ?? [],
        'canAssignCourier' => $canAssignCourier ?? false,
        'shipmentBootstrap' => $shipmentBootstrap ?? null,
    ])
@endpush
@endunless
