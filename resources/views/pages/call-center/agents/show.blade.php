@extends('layout_main.app')

@php
    $agent = $agent ?? [];
    $permissionCatalog = $permissionCatalog ?? [];
    $assignedPermissions = $assignedPermissions ?? [];
    $permissionStates = $permissionStates ?? [];
    $isActive = ($agent['status'] ?? '') === 'active';
    $currentOrders = $agent['current_orders'] ?? [];
    $overallPerformance = $agent['overall_performance'] ?? [];
    $statisticsAvailable = (bool) ($statisticsAvailable ?? ($agent['statistics_available'] ?? false));
@endphp

@push('styles')
    <style>
        @include('pages.call-center.agents.partials.styles')
    </style>
@endpush

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Agent Profile</h3>
            <a href="{{ route('ui.call-center.agents.index') }}" class="btn btn-outline-secondary">Back to Agents</a>
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
                    <a href="{{ route('ui.call-center.agents.index') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Call Center</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">{{ $agent['full_name'] ?? 'Agent Profile' }}</span>
                </li>
            </ol>
        </nav>

        @if (session('success'))
            <div class="alert alert-success mb-4" role="alert">
                {{ session('success') }}
            </div>
        @endif

        {{-- Agent Header --}}
        <div class="card bg-white border border-white rounded-10 p-20 mb-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        @include('pages.call-center.agents.partials.agent-avatar', [
                            'agent' => $agent,
                            'size' => 75,
                        ])
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="fs-18 mb-1">{{ $agent['full_name'] }}</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10">
                                Call Center Agent
                            </span>
                            @include('pages.call-center.agents.partials.status-badge', [
                                'status' => $agent['status'],
                            ])
                        </div>
                        <div class="d-flex flex-wrap gap-3 fs-15 text-body mb-3">
                            <span>
                                <span class="text-muted">Display ID:</span>
                                <span class="text-secondary">{{ $agent['username'] }}</span>
                            </span>
                            <span>
                                <span class="text-muted">Phone:</span>
                                <span class="text-secondary">{{ $agent['phone'] }}</span>
                            </span>
                        </div>
                        <div class="call-center-commission-rate-block">
                            <span class="fs-14 text-body d-block mb-1">Current Commission Rate</span>
                            <div class="d-flex align-items-end flex-wrap gap-2">
                                <h3 class="mb-0" data-commission-rate-display>{{ $agent['commission_label'] }}</h3>
                                <span class="fs-16 text-body mb-1">/ order</span>
                            </div>
                            <p class="fs-13 text-body mb-0 mt-2">
                                Changes apply to future orders. Historical commissions are not affected.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('call_center.agents.commission.update')
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#agentCommissionModal">
                            Edit Commission
                        </button>
                    @endcan
                    <a href="{{ route('ui.call-center.agents.edit', $agent['slug']) }}" class="btn btn-primary text-white">
                        Edit Agent
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                        data-bs-target="#agentProfileStatusModal">
                        {{ $isActive ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Call Center Overview — order counts unavailable until Order domain exists --}}
        <div class="row">
            <div class="col-xxl-12 col-xxxxxl-12">
                <div class="card bg-white p-40 rounded-10 border-0 mb-4 position-relative z-1 quick-view-bg"
                    style="padding-top: 29px;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                        <div>
                            <h3 class="text-white fs-26 mb-1">Call Center Overview</h3>
                            @unless ($statisticsAvailable)
                                <p class="fs-13 text-white text-opacity-75 mb-0">
                                    Order workflow statistics will appear here when the Order domain is connected.
                                </p>
                            @endunless
                        </div>

                        <div class="dropdown action-opt text-center">
                            <button class="btn bg-transparent p-0" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false" @disabled(! $statisticsAvailable)
                                title="{{ $statisticsAvailable ? 'Filter period' : 'Period filters unlock with Order data' }}">
                                <i class="material-symbols-outlined fs-20 text-white">more_vert</i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow">
                                <li><span class="dropdown-item text-muted">Today — unavailable</span></li>
                                <li><span class="dropdown-item text-muted">This Week — unavailable</span></li>
                                <li><span class="dropdown-item text-muted">This Month — unavailable</span></li>
                                <li><span class="dropdown-item text-muted">All Time — unavailable</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card bg-white rounded-10 border-0"
                        style="box-shadow: 0px 0px 10px 3px rgba(195, 195, 195, 0.5);">
                        <div class="row g-0">
                            @foreach ([
                                ['label' => 'Pending', 'key' => 'pending', 'icon' => 'ri-time-line', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'Confirm', 'key' => 'confirmed', 'icon' => 'ri-shopping-cart-2-line', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'First Attempt', 'key' => 'first_attempt', 'icon' => 'ri-printer-fill', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'Second Attempt', 'key' => 'second_attempt', 'icon' => 'ri-archive-stack-fill', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'Third Attempt', 'key' => 'third_attempt', 'icon' => 'ri-repeat-2-line', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'Cancel', 'key' => 'cancelled', 'icon' => 'ri-truck-line', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                                ['label' => 'Hold', 'key' => 'hold', 'icon' => 'ri-close-circle-line', 'icon_style' => 'color: #FDE5E0; background-color: #EF4923;'],
                                ['label' => 'Dispatch', 'key' => 'dispatch', 'icon' => 'ri-truck-line', 'icon_style' => 'color: #EF4923; background-color: rgba(239, 73, 35, 0.14);'],
                            ] as $metric)
                                <div class="col-6 col-lg-3 border-border-color-90 border-bottom border-end">
                                    <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                        <div class="d-flex">
                                            <div class="flex-grow-1">
                                                <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">{{ $metric['label'] }}</h3>
                                                <h2 class="fs-26 fw-bold mb-10 lh-1">
                                                    {{ $currentOrders[$metric['key'].'_label'] ?? '—' }}
                                                </h2>
                                            </div>
                                            <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                <div class="w-100 position-absolute top-50 translate-middle-y">
                                                    <i class="{{ $metric['icon'] }} d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                        style="width: 70px; height: 70px; {{ $metric['icon_style'] }}"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overall Performance --}}
        <div class="card bg-white p-20 rounded-10 border border-white mb-4 py-50">
            <div class="mb-20">
                <h3 class="mb-1">Overall Performance</h3>
                <p class="fs-14 text-body mb-0">
                    @if ($statisticsAvailable)
                        Lifetime order handling and commission settlement summary for this agent.
                    @else
                        Performance and earnings totals will appear here when Order and Commission ledger data is available.
                        Values shown as — are not calculated yet.
                    @endif
                </p>
            </div>

            <div class="row">
                <div class="col-xxl-2 col-md-4 col-sm-6">
                    <div class="position-relative border-end-custom pe-10">
                        <h3 class="mb-10">Total Orders</h3>
                        <h2 class="fs-26 fw-medium mb-0 lh-1">{{ $overallPerformance['total_orders_label'] ?? '0' }}</h2>
                    </div>
                </div>
                <div class="col-xxl-2 col-md-4 col-sm-6">
                    <div class="position-relative border-end-custom pe-10">
                        <h3 class="mb-10">Success Rate</h3>
                        <h2 class="fs-26 fw-medium mb-0 lh-1 text-info">
                            {{ $overallPerformance['success_rate_label'] ?? '0.0%' }}</h2>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-4 col-sm-6">
                    <div class="position-relative border-end-custom pe-10">
                        <h3 class="mb-10">Total Commissions Withdrawn</h3>
                        <h2 class="fs-26 fw-medium mb-0 lh-1 text-success">
                            {{ $overallPerformance['commissions_withdrawn_label'] ?? 'LKR 0.00' }}</h2>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6 col-sm-6">
                    <div class="position-relative border-end-custom pe-10">
                        <h3 class="mb-10">Pending Commissions</h3>
                        <h2 class="fs-26 fw-medium mb-0 lh-1 text-warning">
                            {{ $overallPerformance['pending_commissions_label'] ?? 'LKR 0.00' }}</h2>
                    </div>
                </div>
                <div class="col-xxl-2 col-md-6 col-sm-6">
                    <div class="position-relative pe-10">
                        <h3 class="mb-10">Pending Clearance Orders</h3>
                        <h2 class="fs-26 fw-medium mb-0 lh-1 text-body">
                            {{ $overallPerformance['pending_clearance_orders_label'] ?? '0' }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card bg-white border border-white rounded-10 p-20 mb-4">
                    <h3 class="mb-20">Account information</h3>
                    <ul class="p-0 mb-0 list-unstyled last-child-none">
                        <li class="mb-10 fs-16 d-flex justify-content-between gap-2">
                            Display ID
                            <span class="text-secondary text-end">{{ $agent['username'] }}</span>
                        </li>
                        <li class="mb-10 fs-16 d-flex justify-content-between gap-2">
                            Phone
                            <span class="text-secondary text-end">{{ $agent['phone'] }}</span>
                        </li>
                        <li class="mb-10 fs-16 d-flex justify-content-between gap-2">
                            Joined date
                            <span class="text-secondary text-end">{{ $agent['joined_label'] }}</span>
                        </li>
                        <li class="mb-0 fs-16 d-flex justify-content-between gap-2">
                            Status
                            <span>
                                @include('pages.call-center.agents.partials.status-badge', [
                                    'status' => $agent['status'],
                                ])
                            </span>
                        </li>
                    </ul>
                    <p class="fs-13 text-body mb-0 mt-3">
                        Display ID is derived for the UI and is not a database username. Agents sign in with phone and password.
                    </p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card bg-white border border-white rounded-10 p-20 mb-4">
                    <h3 class="mb-2">Permissions</h3>
                    <p class="fs-14 text-body mb-20">
                        Effective operational permissions for this call center agent. Agent management permissions are never shown here.
                    </p>
                    @include('pages.call-center.agents.partials.permission-display', [
                        'permissionCatalog' => $permissionCatalog,
                        'assignedPermissions' => $assignedPermissions,
                        'permissionStates' => $permissionStates ?? [],
                    ])
                </div>
            </div>
        </div>
    </div>

    @include('pages.call-center.agents.partials.status-toggle-modal', [
        'modalId' => 'agentProfileStatusModal',
        'actionUrl' => $isActive
            ? route('ui.call-center.agents.deactivate', $agent['slug'])
            : route('ui.call-center.agents.activate', $agent['slug']),
        'title' => $isActive ? 'Deactivate agent' : 'Activate agent',
        'message' => $isActive
            ? 'Are you sure you want to deactivate ' . $agent['full_name'] . '?'
            : 'Are you sure you want to activate ' . $agent['full_name'] . '?',
        'confirmLabel' => $isActive ? 'Deactivate' : 'Activate',
        'confirmClass' => $isActive ? 'btn-danger text-white' : 'btn-primary text-white',
    ])

    @can('call_center.agents.commission.update')
        @include('pages.call-center.agents.partials.commission-rate-modal', [
            'agentSlug' => $agent['slug'],
            'commissionRate' => $agent['commission_rate'] ?? 0,
            'commissionLabel' => $agent['commission_label'] ?? 'LKR 0.00',
        ])
    @endcan
@endsection
