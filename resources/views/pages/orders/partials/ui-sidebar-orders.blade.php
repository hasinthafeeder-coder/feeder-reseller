{{-- Reseller Orders navigation under the ORDERS section (no extra Orders wrapper). --}}
@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));

    $orderUiIsCreate = request()->routeIs('orders.create') && ! request()->filled('ui_screen');
    $orderUiIsOngoing = request()->routeIs('orders.index') && ! $orderUiIsArchitectureList;
    $orderUiIsCallCenter = $orderUiWorkspace === 'call-center';
    $orderUiIsImport = $orderUiWorkspace === 'import';
    $orderUiIsNew = $orderUiWorkspace === 'new';
    $orderUiIsArchived = $orderUiWorkspace === 'archived';
@endphp
<li class="menu-item after-sub-menu {{ $orderUiIsNew || $orderUiIsCreate || $orderUiIsImport ? 'open' : '' }}">
    <a href="javascript:void(0);" class="menu-link menu-toggle">
        <span class="material-symbols-outlined menu-icon">add_shopping_cart</span>
        <span class="title">New Orders</span>
    </a>
    <ul class="menu-sub">
        <li class="menu-item">
            <a href="{{ $orderUiUrls['create'] }}" class="menu-link {{ $orderUiIsCreate ? 'active' : '' }}">
                Create Order
            </a>
        </li>
        <li class="menu-item">
            <a href="{{ $orderUiUrls['import'] }}" class="menu-link {{ $orderUiIsImport ? 'active' : '' }}">
                Import Order
            </a>
        </li>
    </ul>
</li>
<li class="menu-item">
    <a href="{{ $orderUiUrls['call_center'] }}" class="menu-link {{ $orderUiIsCallCenter ? 'active' : '' }}">
        <span class="material-symbols-outlined menu-icon">headset_mic</span>
        <span class="title">Call Center Orders</span>
    </a>
</li>
<li class="menu-item">
    <a href="{{ $orderUiUrls['ongoing'] }}" class="menu-link {{ $orderUiIsOngoing || request()->routeIs('orders.show') ? 'active' : '' }}">
        <span class="material-symbols-outlined menu-icon">shopping_cart</span>
        <span class="title">Ongoing Orders</span>
    </a>
</li>
<li class="menu-item">
    <a href="{{ $orderUiUrls['archived'] }}" class="menu-link {{ $orderUiIsArchived ? 'active' : '' }}">
        <span class="material-symbols-outlined menu-icon">archive</span>
        <span class="title">Archived Orders</span>
    </a>
</li>
