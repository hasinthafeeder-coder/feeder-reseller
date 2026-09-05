@extends('layout_main.app')

@php
    $agent = $agent ?? [];
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
                <h3 class="mb-1">Edit Agent</h3>
                <p class="fs-15 text-body mb-0">
                    Update {{ $agent['full_name'] ?? 'this call center agent' }}’s details, commission, permissions, and account status.
                </p>
            </div>
            <a href="{{ route('ui.call-center.agents.show', $agent['slug']) }}" class="btn btn-outline-secondary">
                View profile
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
                <li class="breadcrumb-item">
                    <a href="{{ route('ui.call-center.agents.index') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Call Center</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Edit Agent</span>
                </li>
            </ol>
        </nav>

        <div class="alert alert-info d-none mb-4" role="alert" data-ui-preview-notice>
            This is a UI preview. Changes were not saved.
        </div>

        <form action="#" method="POST" data-ui-only-form novalidate>
            @include('pages.call-center.agents.partials.form-fields', [
                'mode' => 'edit',
                'agent' => $agent,
                'permissionCatalog' => $permissionCatalog,
                'assignedPermissions' => $assignedPermissions,
            ])
        </form>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="changePasswordModalLabel">Change Password</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="#" method="POST" data-ui-only-form novalidate>
                    <div class="modal-body">
                        <p class="fs-14 text-body mb-20">
                            Set a new password for {{ $agent['full_name'] ?? 'this agent' }}. This does not change the system-generated username.
                        </p>
                        <div class="mb-20" id="password-show-hide">
                            <label for="newAgentPassword" class="label fs-16 mb-2">New Password</label>
                            <div class="password-wrapper position-relative password-container mb-20">
                                <input type="password" class="form-control text-secondary password" id="newAgentPassword"
                                    name="password" placeholder="Enter new password" autocomplete="new-password"
                                    data-password-input>
                                <i class="ri-eye-off-line password-toggle-icon translate-middle-y top-50 position-absolute cursor text-secondary"
                                    style="color: #A9A9C8; font-size: 22px; right: 15px;" data-password-toggle
                                    role="button" tabindex="0" aria-label="Show password"></i>
                            </div>
                            <label for="newAgentPasswordConfirmation" class="label fs-16 mb-2">Confirm Password</label>
                            <div class="password-wrapper position-relative password-container">
                                <input type="password" class="form-control text-secondary password" id="newAgentPasswordConfirmation"
                                    name="password_confirmation" placeholder="Confirm new password" autocomplete="new-password"
                                    data-password-input>
                                <i class="ri-eye-off-line password-toggle-icon translate-middle-y top-50 position-absolute cursor text-secondary"
                                    style="color: #A9A9C8; font-size: 22px; right: 15px;" data-password-toggle
                                    role="button" tabindex="0" aria-label="Show confirm password"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-white" data-bs-dismiss="modal">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        @include('pages.call-center.agents.partials.form-scripts')
    </script>
@endpush
