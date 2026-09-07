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



        @if (! empty($permissionFormId))
            <form id="{{ $permissionFormId }}" action="{{ route('ui.call-center.agents.permissions.update', $agent['slug']) }}" method="POST" class="d-none" aria-hidden="true">
                @csrf
            </form>
        @endif

        <form action="{{ route('ui.call-center.agents.update', $agent['slug']) }}" method="POST" novalidate>

            @csrf

            @method('PUT')

            @include('pages.call-center.agents.partials.form-fields', [

                'mode' => 'edit',

                'agent' => $agent,

                'permissionCatalog' => $permissionCatalog,

                'assignedPermissions' => $assignedPermissions,

                'canManagePermissions' => $canManagePermissions ?? false,

                'permissionFormId' => $permissionFormId ?? null,

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

                <div class="modal-body">

                    <p class="fs-14 text-body mb-20">

                        Set a new password for {{ $agent['full_name'] ?? 'this agent' }}. This does not change the system-generated username.

                        The new password is applied when you save changes on the Edit Agent form.

                    </p>

                    <div class="mb-20" id="password-show-hide-modal">

                        <label for="newAgentPassword" class="label fs-16 mb-2">New Password</label>

                        <div class="password-wrapper position-relative password-container mb-20">

                            <input type="password" class="form-control text-secondary password" id="newAgentPassword"

                                placeholder="Enter new password" autocomplete="new-password"

                                data-password-input data-edit-password-source="password">

                            <i class="ri-eye-off-line password-toggle-icon translate-middle-y top-50 position-absolute cursor text-secondary"

                                style="color: #A9A9C8; font-size: 22px; right: 15px;" data-password-toggle

                                role="button" tabindex="0" aria-label="Show password"></i>

                        </div>

                        <label for="newAgentPasswordConfirmation" class="label fs-16 mb-2">Confirm Password</label>

                        <div class="password-wrapper position-relative password-container">

                            <input type="password" class="form-control text-secondary password" id="newAgentPasswordConfirmation"

                                placeholder="Confirm new password" autocomplete="new-password"

                                data-password-input data-edit-password-source="password_confirmation">

                            <i class="ri-eye-off-line password-toggle-icon translate-middle-y top-50 position-absolute cursor text-secondary"

                                style="color: #A9A9C8; font-size: 22px; right: 15px;" data-password-toggle

                                role="button" tabindex="0" aria-label="Show confirm password"></i>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>

                    <button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal" data-edit-password-apply>

                        Update Password

                    </button>

                </div>

            </div>

        </div>

    </div>

@endsection



@push('scripts')

    <script>

        @include('pages.call-center.agents.partials.form-scripts')

    </script>

@endpush


