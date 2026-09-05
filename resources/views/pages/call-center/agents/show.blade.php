@extends('layout_main.app')

@php
    $agent = $agent ?? [];
    $permissionCatalog = $permissionCatalog ?? [];
    $assignedPermissions = $assignedPermissions ?? [];
    $isActive = ($agent['status'] ?? '') === 'active';
    $currentOrders = $agent['current_orders'] ?? [];
    $overallPerformance = $agent['overall_performance'] ?? [];
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
                                <span class="text-muted">Username:</span>
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
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#agentCommissionModal">
                        Edit Commission
                    </button>
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

        {{-- Current Orders --}}
        <div class="row">
            <div class="col-xxl-12 col-xxxxxl-12">
                <div class="card bg-white p-40 rounded-10 border-0 mb-4 position-relative z-1 quick-view-bg"
                    style="padding-top: 29px;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                        <h3 class="text-white fs-26">Call Center Overview</h3>

                        <div class="dropdown action-opt text-center">
                            <button class="btn bg-transparent p-0" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="material-symbols-outlined fs-20 text-white">more_vert</i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow">
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Day
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Week
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Month
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Year
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card bg-white rounded-10 border-0"
                        style="box-shadow: 0px 0px 10px 3px rgba(195, 195, 195, 0.5);">
                        <div class="row g-0">
                            <div class="col-6 col-lg-3 border-bottom border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Pending</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">2554</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-time-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-bottom border-start border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Confirm</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">5517</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-shopping-cart-2-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-bottom border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">First Attempt
                                            </h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">5466</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-printer-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-top border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Second Attempt</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-archive-stack-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-top border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Third Attempt</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">0</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-repeat-2-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-top border-end border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Cancel
                                            </h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">212</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-truck-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-top border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Hold</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-close-circle-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #FDE5E0; background-color: #EF4923;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 border-top border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Dispatch</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">0</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-truck-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overall Performance --}}
        <div class="card bg-white border border-white rounded-10 p-20 mb-4">
            <div class="mb-20">
                <h3 class="mb-1">Overall Performance</h3>
                <p class="fs-14 text-body mb-0">
                    Lifetime order handling and commission settlement summary for this agent.
                </p>
            </div>

            <div class="row g-3">
                <div class="col-6 col-md-4 col-xl">
                    @include('pages.call-center.agents.partials.stat-card', [
                        'title' => 'Total Orders',
                        'value' => $overallPerformance['total_orders_label'] ?? '0',
                        'tone' => 'default',
                    ])
                </div>
                <div class="col-6 col-md-4 col-xl">
                    @include('pages.call-center.agents.partials.stat-card', [
                        'title' => 'Success Rate',
                        'value' => $overallPerformance['success_rate_label'] ?? '0.0%',
                        'tone' => 'info',
                    ])
                </div>
                <div class="col-6 col-md-4 col-xl">
                    @include('pages.call-center.agents.partials.stat-card', [
                        'title' => 'Total Commissions Withdrawn',
                        'value' => $overallPerformance['commissions_withdrawn_label'] ?? 'LKR 0.00',
                        'tone' => 'success',
                    ])
                </div>
                <div class="col-6 col-md-6 col-xl">
                    @include('pages.call-center.agents.partials.stat-card', [
                        'title' => 'Pending Commissions',
                        'value' => $overallPerformance['pending_commissions_label'] ?? 'LKR 0.00',
                        'tone' => 'warning',
                    ])
                </div>
                <div class="col-12 col-md-6 col-xl">
                    @include('pages.call-center.agents.partials.stat-card', [
                        'title' => 'Pending Clearance Orders',
                        'value' => $overallPerformance['pending_clearance_orders_label'] ?? '0',
                        'tone' => 'muted',
                    ])
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card bg-white border border-white rounded-10 p-20 mb-4">
                    <h3 class="mb-20">Account information</h3>
                    <ul class="p-0 mb-0 list-unstyled last-child-none">
                        <li class="mb-10 fs-16 d-flex justify-content-between gap-2">
                            Username
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
                    <p class="fs-13 text-body mb-0 mt-3">The username is system-generated and is not edited from this
                        profile.</p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card bg-white border border-white rounded-10 p-20 mb-4">
                    <h3 class="mb-2">Permissions</h3>
                    <p class="fs-14 text-body mb-20">
                        Permissions currently assigned to this call center agent. This display is a visual preview and is
                        not connected to the permission engine.
                    </p>
                    @include('pages.call-center.agents.partials.permission-display', [
                        'permissionCatalog' => $permissionCatalog,
                        'assignedPermissions' => $assignedPermissions,
                    ])
                </div>
            </div>
        </div>
    </div>

    @include('pages.call-center.agents.partials.status-toggle-modal', [
        'modalId' => 'agentProfileStatusModal',
        'title' => $isActive ? 'Deactivate agent' : 'Activate agent',
        'message' => $isActive
            ? 'Are you sure you want to deactivate ' . $agent['full_name'] . '?'
            : 'Are you sure you want to activate ' . $agent['full_name'] . '?',
        'confirmLabel' => $isActive ? 'Deactivate' : 'Activate',
        'confirmClass' => $isActive ? 'btn-danger text-white' : 'btn-primary text-white',
    ])

    @include('pages.call-center.agents.partials.commission-rate-modal', [
        'commissionRate' => $agent['commission_rate'] ?? 0,
        'commissionLabel' => $agent['commission_label'] ?? 'LKR 0.00',
    ])
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modalElement = document.getElementById('agentCommissionModal');
            if (!modalElement) {
                return;
            }

            var rateInput = document.getElementById('agentCommissionRateInput');
            var rateDisplay = document.querySelector('[data-commission-rate-display]');
            var feedback = document.getElementById('agentCommissionRateFeedback');
            var saveButton = document.getElementById('agentCommissionRateSave');
            var successAlert = document.getElementById('agentCommissionRateSuccess');

            function formatCommission(amount) {
                return 'LKR ' + amount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function resetFeedback() {
                if (feedback) {
                    feedback.classList.add('d-none');
                    feedback.textContent = '';
                }
                if (rateInput) {
                    rateInput.classList.remove('is-invalid');
                }
                if (successAlert) {
                    successAlert.classList.add('d-none');
                }
            }

            modalElement.addEventListener('show.bs.modal', function() {
                resetFeedback();
                if (rateInput && rateDisplay) {
                    var currentText = rateDisplay.textContent || '';
                    var numeric = parseFloat(currentText.replace(/[^0-9.]/g, ''));
                    if (!Number.isNaN(numeric)) {
                        rateInput.value = numeric.toFixed(2);
                    }
                }
            });

            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    resetFeedback();

                    var rawValue = rateInput ? rateInput.value.trim() : '';
                    var amount = parseFloat(rawValue);

                    if (rawValue === '' || Number.isNaN(amount) || amount < 0) {
                        if (rateInput) {
                            rateInput.classList.add('is-invalid');
                        }
                        if (feedback) {
                            feedback.textContent = 'Enter a commission rate of 0 or greater.';
                            feedback.classList.remove('d-none');
                        }
                        return;
                    }

                    if (rateDisplay) {
                        rateDisplay.textContent = formatCommission(amount);
                    }

                    if (successAlert) {
                        successAlert.classList.remove('d-none');
                    }

                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        window.setTimeout(function() {
                            modalInstance.hide();
                        }, 450);
                    }
                });
            }
        });
    </script>
@endpush
