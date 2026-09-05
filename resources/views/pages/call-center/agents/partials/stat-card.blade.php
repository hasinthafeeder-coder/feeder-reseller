@php
    $title = $title ?? '';
    $value = $value ?? '—';
    $helper = $helper ?? '';
    $tone = $tone ?? 'default';
    $note = $note ?? null;

    $toneClass = match ($tone) {
        'success' => 'call-center-stat-card--success',
        'warning' => 'call-center-stat-card--warning',
        'muted' => 'call-center-stat-card--muted',
        'info' => 'call-center-stat-card--info',
        default => 'call-center-stat-card--default',
    };
@endphp

<div class="call-center-stat-card {{ $toneClass }} h-100">
    <span class="fs-14 text-body d-block mb-2">{{ $title }}</span>
    <h3 class="mb-1 call-center-stat-value">{{ $value }}</h3>
    @if ($helper !== '')
        <span class="fs-13 text-body d-block">{{ $helper }}</span>
    @endif
    @if ($note)
        <span class="fs-12 text-muted d-block mt-2">{{ $note }}</span>
    @endif
</div>
