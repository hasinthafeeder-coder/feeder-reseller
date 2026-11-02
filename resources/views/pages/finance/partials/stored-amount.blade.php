@php
    $formatted = \App\Support\StoredMoneyDisplay::format($amount ?? null);
    $code = is_string($currency ?? null) ? trim($currency) : '';
@endphp

@if ($formatted === null)
    <span class="text-body">Not shown</span>
@else
    <span class="text-body text-break">
        @if ($code !== '')
            <span class="fs-12 text-secondary me-1">{{ $code }}</span>
        @endif
        {{ $formatted }}
    </span>
@endif
