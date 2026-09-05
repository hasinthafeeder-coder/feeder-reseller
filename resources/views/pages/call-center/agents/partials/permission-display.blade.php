@php
    $assignedPermissions = $assignedPermissions ?? [];
@endphp

<div class="row g-3">
    @foreach ($permissionCatalog as $group)
        @php
            $assignedInGroup = collect($group['permissions'])
                ->filter(fn ($label, $key) => in_array($key, $assignedPermissions, true));
        @endphp
        <div class="col-12 col-lg-4">
            <div class="call-center-permission-card h-100">
                <h5 class="fs-16 fw-medium mb-1">{{ $group['label'] }}</h5>
                <p class="fs-13 text-body mb-12">{{ $group['description'] }}</p>
                @if ($assignedInGroup->isEmpty())
                    <span class="fs-14 text-muted">No permissions assigned in this group.</span>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($assignedInGroup as $label)
                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
