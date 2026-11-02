{{-- Reuses call-center / orders badge-status classes: is-{status-slug}. --}}
@php
    use Feeder\Core\Enums\OrderStatus;

    $rawStatus = trim((string) ($status ?? ''));
    $orderStatus = $rawStatus !== '' ? OrderStatus::tryFrom($rawStatus) : null;
    $badgeLabel = ($label ?? null)
        ?? ($orderStatus?->label()
            ?? ($rawStatus !== ''
                ? str_replace('_', ' ', $rawStatus)
                : '—'));
    $badgeClass = $rawStatus !== ''
        ? 'is-'.strtolower(str_replace('_', '-', $rawStatus))
        : '';
@endphp
@if ($rawStatus === '')
    <span class="text-muted">—</span>
@else
    <span class="badge-status {{ $badgeClass }}">{{ $badgeLabel }}</span>
@endif
