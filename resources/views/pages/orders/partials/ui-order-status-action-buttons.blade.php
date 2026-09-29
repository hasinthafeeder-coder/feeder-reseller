<div class="order-status-actions {{ $statusActionsClass ?? '' }}" role="group" aria-label="{{ $statusActionsLabel ?? 'Available order statuses' }}">
    @foreach ($actionOptions as $option)
        @php
            $isCurrent = $option['value'] === $currentStatusValue;
        @endphp
        <button
            type="button"
            class="btn btn-sm order-status-action-btn {{ $isCurrent ? 'is-current' : '' }}"
            data-status-value="{{ $option['value'] }}"
            data-status-label="{{ $option['label'] }}"
            @disabled($isCurrent)
            @if ($isCurrent) aria-current="true" @endif
        >
            {{ $option['label'] }}
        </button>
    @endforeach
</div>
