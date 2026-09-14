@php
    $orderUiPageTitle = $orderUiPageTitle ?? 'Orders';
    $orderUiPageCopy = $orderUiPageCopy ?? '';
    $orderUiCrumbs = $orderUiCrumbs ?? [];
    $orderUiPrimaryHref = $orderUiPrimaryHref ?? null;
    $orderUiPrimaryLabel = $orderUiPrimaryLabel ?? null;
    $orderUiSecondaryHref = $orderUiSecondaryHref ?? route('orders.index');
    $orderUiSecondaryLabel = $orderUiSecondaryLabel ?? 'Back to Orders';
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
    <div>
        <h3 class="mb-1">{{ $orderUiPageTitle }}</h3>
        @if ($orderUiPageCopy !== '')
            <p class="fs-15 text-body mb-0">{{ $orderUiPageCopy }}</p>
        @endif
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        @if ($orderUiPrimaryHref && $orderUiPrimaryLabel)
            <a href="{{ $orderUiPrimaryHref }}" class="btn btn-primary text-white">{{ $orderUiPrimaryLabel }}</a>
        @endif
        <a href="{{ $orderUiSecondaryHref }}" class="btn btn-light border">{{ $orderUiSecondaryLabel }}</a>
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
        <li class="breadcrumb-item">
            <a href="{{ route('orders.index') }}" class="text-decoration-none">
                <span class="text-body fs-14 hover">Orders</span>
            </a>
        </li>
        @foreach ($orderUiCrumbs as $crumb)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if ($loop->last) aria-current="page" @endif>
                @if (! $loop->last && ! empty($crumb['href']))
                    <a href="{{ $crumb['href'] }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">{{ $crumb['label'] }}</span>
                    </a>
                @else
                    <span class="text-secondary">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
