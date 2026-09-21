@php
    $currentStatusLabel = $currentStatusLabel ?? '—';
    $oldStatus = old('status');
@endphp

<div
    id="orderStatusConfirmModal"
    class="orders-proto-modal modal-backdrop-proto hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="orderStatusConfirmTitle"
    data-current-status-label="{{ $currentStatusLabel }}"
    @if ($oldStatus) data-reopen-status="{{ $oldStatus }}" @endif
>
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="orderStatusConfirmTitle">Confirm status change</h5>
        </div>
        <form method="POST" action="{{ route('orders.status.update', $order) }}" id="orderStatusConfirmForm">
            @csrf
            <input type="hidden" name="status" id="orderStatusConfirmValue" value="{{ $oldStatus }}">
            <div class="modal-body">
                <p class="fs-14 text-body mb-3" id="orderStatusConfirmCopy">
                    Move this order from <strong>{{ $currentStatusLabel }}</strong> to the selected status.
                </p>
                <div class="mb-0">
                    <label class="label fs-14 mb-2" for="orderStatusConfirmNote">Note (optional)</label>
                    <textarea
                        class="form-control"
                        id="orderStatusConfirmNote"
                        name="reason"
                        rows="3"
                        maxlength="1000"
                        placeholder="Add a note for this status change"
                    >{{ old('reason') }}</textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light border" data-close-modal="orderStatusConfirmModal">Cancel</button>
                <button type="submit" class="btn btn-primary text-white" id="orderStatusConfirmSubmit">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>
