<div
    id="duplicateOrderConfirmModal"
    class="orders-proto-modal modal-backdrop-proto hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="duplicateOrderConfirmTitle"
>
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="duplicateOrderConfirmTitle">Duplicate orders found</h5>
        </div>
        <div class="modal-body">
            <p class="fs-14 text-body mb-2">
                Potential incomplete duplicate orders were found for this customer and variant(s).
            </p>
            <p class="fs-14 text-body mb-0">
                Please review the duplicate orders and confirm Continue before submitting.
            </p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="duplicateOrderConfirmModal" id="closeDuplicateOrderConfirmModal">
                Cancel / Go Back
            </button>
            <button type="button" class="btn btn-primary text-white" id="confirmDuplicateOrderContinueBtn">
                Continue
            </button>
        </div>
    </div>
</div>
