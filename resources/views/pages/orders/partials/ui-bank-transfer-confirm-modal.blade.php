<div
    id="bankTransferConfirmModal"
    class="orders-proto-modal modal-backdrop-proto hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="bankTransferConfirmTitle"
>
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="bankTransferConfirmTitle">Request payment approval</h5>
        </div>
        <div class="modal-body">
            <p class="fs-14 text-body mb-2">
                Please check all order information before continuing.
            </p>
            <p class="fs-14 text-body mb-2">
                Once Admin approves the payment, this order will automatically become
                <strong>Confirmed</strong>.
            </p>
            <p class="fs-14 text-body mb-0">
                After you submit, you will not be able to edit or cancel the order while it is
                under review.
            </p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="bankTransferConfirmModal">
                Cancel
            </button>
            <button type="button" class="btn btn-primary text-white" id="confirmBankTransferApprovalBtn">
                Request Approval
            </button>
        </div>
    </div>
</div>
