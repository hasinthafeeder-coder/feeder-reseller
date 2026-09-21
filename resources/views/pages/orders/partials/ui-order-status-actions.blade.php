@php
    use Feeder\Core\Enums\OrderStatus;

    $canUpdateStatus = $canUpdateStatus ?? false;
    $isCancelled = $isCancelled ?? false;
    $canReactivate = $canReactivate ?? false;
    $statusOptions = $statusOptions ?? [];
    $reactivationStatusOptions = $reactivationStatusOptions ?? [];
    $showStatusModal = $showStatusModal ?? true;
    $currentStatusValue = isset($order) && $order->status instanceof OrderStatus
        ? $order->status->value
        : (string) ($order->status ?? '');
    $actionOptions = $isCancelled ? $reactivationStatusOptions : $statusOptions;
    $currentStatusLabel = isset($order) && $order->status instanceof OrderStatus
        ? $order->status->label()
        : (string) ($order->status ?? '—');
@endphp

@if ($canUpdateStatus && isset($order))
    <div class="card bg-white rounded-10 border border-white {{ $showStatusModal ? 'mb-4' : 'order-create-section' }}" id="orderStatusActionsCard">
        <div class="p-20 border-bottom">
            <h4 class="fs-18 mb-0">
                @if ($isCancelled)
                    Reactivate / Order Status
                @else
                    Order Status
                @endif
            </h4>
        </div>
        <div class="p-20">
            @if ($isCancelled && ! $canReactivate)
                <div class="alert alert-danger mb-0" role="alert">
                    This cancelled order is outside the operational window and cannot be reactivated.
                </div>
            @else
                <div class="order-status-actions" role="group" aria-label="Available order statuses">
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
            @endif
        </div>
    </div>

    @if ($showStatusModal && ! ($isCancelled && ! $canReactivate))
        @include('pages.orders.partials.ui-order-status-confirm-modal', [
            'order' => $order,
            'currentStatusLabel' => $currentStatusLabel,
        ])
    @endif
@endif
