@php
    $poolConfirm = $poolConfirm ?? null;
    $poolActionUrl = $poolActionUrl ?? null;
@endphp

@if ($poolActionUrl)
    <form id="orderSendToPoolForm" method="POST" action="{{ $poolActionUrl }}" class="d-none">
        @csrf
    </form>
@endif

<div id="callCenterAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="callCenterAssignModalTitle">
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="callCenterAssignModalTitle">Send to Order Pool</h5>
        </div>
        <div class="modal-body">
            <p class="fs-14 text-body mb-3">
                This order will become available for any eligible CCA to claim.
            </p>

            @if (is_array($poolConfirm))
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fs-13 text-body mb-1">Order</div>
                        <div class="fw-medium">{{ $poolConfirm['orderNumber'] ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fs-13 text-body mb-1">Status</div>
                        <div class="fw-medium">{{ $poolConfirm['status'] ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fs-13 text-body mb-1">Current CCA</div>
                        <div class="fw-medium">{{ $poolConfirm['currentCca'] ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fs-13 text-body mb-1">Customer</div>
                        <div class="fw-medium">{{ $poolConfirm['customer'] ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fs-13 text-body mb-1">Phone</div>
                        <div class="fw-medium">{{ $poolConfirm['phone'] ?? '—' }}</div>
                    </div>
                </div>
            @else
                <p class="fs-14 text-body mb-0">Confirm sending this order to the Order Pool.</p>
            @endif
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="callCenterAssignModal">Cancel</button>
            @if ($poolActionUrl)
                <button type="submit" class="btn btn-primary text-white" form="orderSendToPoolForm" id="confirmSendToOrderPoolBtn">
                    Confirm
                </button>
            @else
                <button type="button" class="btn btn-primary text-white" id="confirmSendToCallCenterBtn">
                    Confirm
                </button>
            @endif
        </div>
    </div>
</div>
