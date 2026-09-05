@extends('layout_main.app')

@php
    $agents = $agents ?? [];
    $filters = $filters ?? ['search' => '', 'status' => ''];
@endphp

@push('styles')
    <style>
        .call-center-agent-avatar {
            letter-spacing: 0.02em;
        }

        .call-center-mobile-agent-card + .call-center-mobile-agent-card {
            margin-top: 12px;
        }
    </style>
@endpush

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Call Center Agents</h3>
                <p class="fs-15 text-body mb-0">
                    Manage call center agents for this reseller company, including login, commission, and assigned permissions.
                </p>
            </div>
            <a href="{{ route('ui.call-center.agents.create') }}" class="btn btn-primary text-white">
                Add Agent
            </a>
        </div>

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                        <span class="text-body fs-14 hover">Dashboard</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span>Call Center</span>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Agents</span>
                </li>
            </ol>
        </nav>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <form method="GET" action="{{ route('ui.call-center.agents.index') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="agentSearch" class="label fs-14 mb-2">Search agent</label>
                        <div class="table-src-form position-relative mx-0">
                            <input type="text" class="form-control w-100" id="agentSearch" name="search"
                                value="{{ $filters['search'] }}" placeholder="Search by name, username or phone"
                                style="height: 40px;">
                            <div class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                <span class="material-symbols-outlined">search</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 col-lg-3">
                        <label for="agentStatusFilter" class="label fs-14 mb-2">Status</label>
                        <select class="form-select form-control" id="agentStatusFilter" name="status">
                            <option value="">All statuses</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-lg-5 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary text-white">Search</button>
                        <a href="{{ route('ui.call-center.agents.index') }}" class="btn btn-light border">Clear filters</a>
                    </div>
                </form>
            </div>

            @if ($agents === [])
                <div class="p-20">
                    <div class="text-center py-5 px-3">
                        <span class="material-symbols-outlined text-primary mb-3" style="font-size: 42px;">headset_mic</span>
                        <h4 class="fs-18 fw-medium mb-2">No call center agents yet</h4>
                        <p class="fs-15 text-body mb-4 mx-auto" style="max-width: 420px;">
                            Add the first call center agent for this reseller company to assign work, set commission per completed order, and control permissions.
                        </p>
                        <a href="{{ route('ui.call-center.agents.create') }}" class="btn btn-primary text-white">
                            Add Agent
                        </a>
                    </div>
                </div>
            @else
                <div class="d-none d-lg-block default-table-area mx-minus-1">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="fw-medium">Agent</th>
                                    <th scope="col" class="fw-medium">Username</th>
                                    <th scope="col" class="fw-medium">Phone</th>
                                    <th scope="col" class="fw-medium">Commission / Order</th>
                                    <th scope="col" class="fw-medium">Status</th>
                                    <th scope="col" class="fw-medium">Joined</th>
                                    <th scope="col" class="fw-medium text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($agents as $agent)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}"
                                                    class="flex-shrink-0 text-decoration-none call-center-agent-avatar">
                                                    @include('pages.call-center.agents.partials.agent-avatar', ['agent' => $agent, 'size' => 40])
                                                </a>
                                                <div class="flex-grow-1 ms-12">
                                                    <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}"
                                                        class="fs-16 text-secondary text-decoration-none hover-text fw-medium">
                                                        {{ $agent['full_name'] }}
                                                    </a>
                                                    <div class="fs-13 text-body">Call Center Agent</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-body">{{ $agent['username'] }}</td>
                                        <td class="text-body">{{ $agent['phone'] }}</td>
                                        <td class="text-body">
                                            <span class="fw-medium text-secondary">{{ $agent['commission_label'] }}</span>
                                            <div class="fs-13 text-body">per completed order</div>
                                        </td>
                                        <td>
                                            @include('pages.call-center.agents.partials.status-badge', ['status' => $agent['status']])
                                        </td>
                                        <td class="text-body">{{ $agent['joined_label'] }}</td>
                                        <td>
                                            <div class="d-flex justify-content-end" style="gap: 12px;">
                                                <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}"
                                                    class="bg-transparent p-0 border-0 text-decoration-none"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View">
                                                    <i class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                </a>
                                                <a href="{{ route('ui.call-center.agents.edit', $agent['slug']) }}"
                                                    class="bg-transparent p-0 border-0 text-decoration-none"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Edit">
                                                    <i class="material-symbols-outlined fs-16 fw-normal text-body">edit</i>
                                                </a>
                                                <button type="button"
                                                    class="bg-transparent p-0 border-0"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#agentStatusToggleModal"
                                                    data-agent-name="{{ $agent['full_name'] }}"
                                                    data-agent-status="{{ $agent['status'] }}"
                                                    data-bs-title="{{ $agent['status'] === 'active' ? 'Deactivate' : 'Activate' }}"
                                                    aria-label="{{ $agent['status'] === 'active' ? 'Deactivate' : 'Activate' }}">
                                                    <i class="material-symbols-outlined fs-16 fw-normal text-body">
                                                        {{ $agent['status'] === 'active' ? 'person_off' : 'person' }}
                                                    </i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-lg-none px-20 pb-20 pt-3">
                    @foreach ($agents as $agent)
                        <div class="call-center-mobile-agent-card border rounded-10 p-3">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-12">
                                <div class="d-flex align-items-center">
                                    @include('pages.call-center.agents.partials.agent-avatar', ['agent' => $agent, 'size' => 44])
                                    <div class="ms-12">
                                        <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}"
                                            class="fs-16 text-secondary text-decoration-none fw-medium">
                                            {{ $agent['full_name'] }}
                                        </a>
                                        <div class="fs-13 text-body">{{ $agent['username'] }}</div>
                                    </div>
                                </div>
                                @include('pages.call-center.agents.partials.status-badge', ['status' => $agent['status']])
                            </div>
                            <ul class="list-unstyled mb-12">
                                <li class="d-flex justify-content-between gap-2 fs-14 mb-2">
                                    <span class="text-body">Phone</span>
                                    <span class="text-secondary">{{ $agent['phone'] }}</span>
                                </li>
                                <li class="d-flex justify-content-between gap-2 fs-14 mb-2">
                                    <span class="text-body">Commission / Order</span>
                                    <span class="text-secondary">{{ $agent['commission_label'] }}</span>
                                </li>
                                <li class="d-flex justify-content-between gap-2 fs-14 mb-0">
                                    <span class="text-body">Joined</span>
                                    <span class="text-secondary">{{ $agent['joined_label'] }}</span>
                                </li>
                            </ul>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}"
                                    class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('ui.call-center.agents.edit', $agent['slug']) }}"
                                    class="btn btn-sm btn-outline-secondary">Edit</a>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#agentStatusToggleModal"
                                    data-agent-name="{{ $agent['full_name'] }}"
                                    data-agent-status="{{ $agent['status'] }}">
                                    {{ $agent['status'] === 'active' ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-20">
                    <div class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap mb-4">
                        <span class="fs-15">Showing 1 to {{ count($agents) }} of 24 entries</span>
                        <nav class="custom-pagination" aria-label="Call Center Agents pagination">
                            <ul class="pagination mb-0 justify-content-center">
                                <li class="page-item disabled">
                                    <span class="page-link icon" aria-hidden="true">
                                        <i class="material-symbols-outlined">west</i>
                                    </span>
                                </li>
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link">1</span>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#">2</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#">3</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link icon" href="#" aria-label="Next page">
                                        <i class="material-symbols-outlined">east</i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('pages.call-center.agents.partials.status-toggle-modal', [
        'modalId' => 'agentStatusToggleModal',
        'title' => 'Change agent status',
        'message' => 'Are you sure you want to update this call center agent’s status?',
        'confirmLabel' => 'Confirm',
        'confirmClass' => 'btn-primary text-white',
    ])
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });

            var modal = document.getElementById('agentStatusToggleModal');
            if (!modal) {
                return;
            }

            modal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                var name = trigger.getAttribute('data-agent-name') || 'this agent';
                var status = trigger.getAttribute('data-agent-status') || 'active';
                var isActive = status === 'active';
                var actionLabel = isActive ? 'deactivate' : 'activate';
                var message = modal.querySelector('[data-status-toggle-message]');
                var confirm = modal.querySelector('[data-status-toggle-confirm]');
                var title = modal.querySelector('.modal-title');

                if (title) {
                    title.textContent = isActive ? 'Deactivate agent' : 'Activate agent';
                }
                if (message) {
                    message.textContent = 'Are you sure you want to ' + actionLabel + ' ' + name + '?';
                }
                if (confirm) {
                    confirm.textContent = isActive ? 'Deactivate' : 'Activate';
                    confirm.classList.toggle('btn-danger', isActive);
                    confirm.classList.toggle('text-white', true);
                    confirm.classList.toggle('btn-primary', !isActive);
                }
            });
        });
    </script>
@endpush
