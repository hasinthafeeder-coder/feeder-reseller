<div id="callCenterAssignModal" class="orders-proto-modal modal-backdrop-proto hidden" role="dialog" aria-modal="true" aria-labelledby="callCenterAssignModalTitle">
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="callCenterAssignModalTitle">Send to Call Center</h5>
        </div>
        <div class="modal-body">
            <p class="fs-14 text-body mb-3">Choose how this order should be assigned. These choices are visual only on this pass.</p>

            <label class="order-ui-choice-card is-selected" for="assignTargetCca">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetCca" value="cca" checked>
                    <span class="choice-title">Select CCA</span>
                    <p class="choice-copy">Assign this order to a specific call-center agent now.</p>
                </div>
            </label>
            <div class="mt-2 mb-3">
                <label class="label fs-14 mb-2" for="modalAssignCcaSelect">Select CCA</label>
                <select id="modalAssignCcaSelect" class="form-select form-control">
                    <option value="cca-1">Nimali Perera</option>
                    <option value="cca-2">Kasun Jayawardena</option>
                    <option value="cca-3">Ishara Fernando</option>
                </select>
            </div>

            <label class="order-ui-choice-card" for="assignTargetPool">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetPool" value="pool">
                    <span class="choice-title">Send to Order Pool</span>
                    <p class="choice-copy">Make the order available for any eligible CCA to claim.</p>
                </div>
            </label>

            <label class="order-ui-choice-card" for="assignTargetUnassigned">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="radio" name="modalAssignmentTarget" id="assignTargetUnassigned" value="unassigned">
                    <span class="choice-title">Leave Unassigned</span>
                    <p class="choice-copy">Keep the order in the unassigned queue without a CCA or pool claim.</p>
                </div>
            </label>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="callCenterAssignModal">Cancel</button>
            <button type="button" class="btn btn-primary text-white" id="confirmSendToCallCenterBtn">Send to Call Center</button>
        </div>
    </div>
</div>
