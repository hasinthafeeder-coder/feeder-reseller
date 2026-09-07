@php
    $assignedPermissions = $assignedPermissions ?? [];
    $permissionCatalog = $permissionCatalog ?? [];
    $permissionStates = $permissionStates ?? [];
@endphp

@if ($permissionCatalog === [] || $permissionCatalog === null)
    <p class="fs-14 text-muted mb-0">
        No operational permissions are defined for Call Center Agents yet.
        Order-specific permissions will appear here when the Order module defines them.
    </p>
@else
    <div class="row g-3">
        @foreach ($permissionCatalog as $group)
            @php
                $groupPermissions = $group['permissions'] ?? [];
                $grantedInGroup = collect($groupPermissions)
                    ->filter(function ($label, $key) use ($assignedPermissions, $permissionStates) {
                        $state = $permissionStates[$key] ?? null;

                        return $state === 'granted' || in_array($key, $assignedPermissions, true);
                    });
                $deniedInGroup = collect($groupPermissions)
                    ->filter(fn ($label, $key) => ($permissionStates[$key] ?? null) === 'denied');
            @endphp
            <div class="col-12 col-lg-4">
                <div class="call-center-permission-card h-100">
                    <h5 class="fs-16 fw-medium mb-1">{{ $group['label'] }}</h5>
                    <p class="fs-13 text-body mb-12">{{ $group['description'] }}</p>
                    @if ($grantedInGroup->isEmpty() && $deniedInGroup->isEmpty())
                        <span class="fs-14 text-muted">No permissions assigned in this group.</span>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($grantedInGroup as $label)
                                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10">
                                    {{ $label }}
                                </span>
                            @endforeach
                            @foreach ($deniedInGroup as $label)
                                <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-10">
                                    {{ $label }} (denied)
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
