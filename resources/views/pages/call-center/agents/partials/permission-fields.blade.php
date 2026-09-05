@php
    $assignedPermissions = $assignedPermissions ?? [];
@endphp

<div class="row g-3">
    @foreach ($permissionCatalog as $groupKey => $group)
        @php
            $groupPermissionKeys = array_keys($group['permissions']);
            $checkedCount = count(array_intersect($groupPermissionKeys, $assignedPermissions));
            $allChecked = $checkedCount === count($groupPermissionKeys) && $checkedCount > 0;
        @endphp
        <div class="col-12 col-lg-4">
            <div class="call-center-permission-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-12">
                    <div>
                        <h5 class="fs-16 fw-medium mb-1">{{ $group['label'] }}</h5>
                        <p class="fs-13 text-body mb-0">{{ $group['description'] }}</p>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox"
                            id="permission-group-{{ $groupKey }}"
                            data-permission-group-toggle="{{ $groupKey }}"
                            @checked($allChecked)
                            aria-label="Select all {{ $group['label'] }} permissions">
                    </div>
                </div>
                <ul class="list-unstyled mb-0 last-child-none">
                    @foreach ($group['permissions'] as $key => $label)
                        <li class="mb-10">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                    name="permissions[]" value="{{ $key }}"
                                    id="permission-{{ $key }}"
                                    data-permission-group="{{ $groupKey }}"
                                    @checked(in_array($key, $assignedPermissions, true))>
                                <label class="form-check-label fs-14 text-secondary" for="permission-{{ $key }}">
                                    {{ $label }}
                                </label>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endforeach
</div>
