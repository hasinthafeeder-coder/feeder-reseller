@extends('layout_main.app')

@php
    $agent = $agent ?? null;
    $permissionCatalog = $permissionCatalog ?? [];
    $assignedPermissions = $assignedPermissions ?? [];
@endphp

@push('styles')
    <style>
        @include('pages.call-center.agents.partials.styles')
    </style>
@endpush

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Add Agent</h3>
                <p class="fs-15 text-body mb-0">
                    Create a call center agent for this reseller company. The username is generated automatically.
                </p>
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
                    <a href="{{ route('ui.call-center.agents.index') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Call Center</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Add Agent</span>
                </li>
            </ol>
        </nav>

        <div class="alert alert-info d-none mb-4" role="alert" data-ui-preview-notice>
            This is a UI preview. The call center agent was not created.
        </div>

        <form action="#" method="POST" data-ui-only-form novalidate>
            @include('pages.call-center.agents.partials.form-fields', [
                'mode' => 'create',
                'agent' => null,
                'permissionCatalog' => $permissionCatalog,
                'assignedPermissions' => [],
            ])
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        @include('pages.call-center.agents.partials.form-scripts')
    </script>
@endpush
