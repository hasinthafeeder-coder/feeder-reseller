@php
    $isActive = ($status ?? '') === 'active';
@endphp
<span class="badge {{ $isActive
    ? 'bg-success-subtle text-success border border-success border-opacity-10'
    : 'bg-secondary-subtle text-secondary border border-secondary border-opacity-10' }}">
    {{ $isActive ? 'Active' : 'Inactive' }}
</span>
