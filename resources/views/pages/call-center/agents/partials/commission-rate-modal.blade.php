{{-- UI-only commission rate modal. Does not persist changes. --}}
<div class="modal fade" id="agentCommissionModal" tabindex="-1" aria-labelledby="agentCommissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="agentCommissionModalLabel">Change Commission Rate</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="agentCommissionRateSuccess" class="alert alert-success py-2 px-3 fs-14 d-none mb-3" role="status">
                    Commission rate updated for preview only. Nothing was saved.
                </div>
                <label for="agentCommissionRateInput" class="label fs-16 mb-2">Commission Rate</label>
                <div class="input-group">
                    <span class="input-group-text">LKR</span>
                    <input type="number"
                        class="form-control"
                        id="agentCommissionRateInput"
                        name="commission_rate"
                        value="{{ number_format((float) ($commissionRate ?? 0), 2, '.', '') }}"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        aria-describedby="agentCommissionRateHelp agentCommissionRateFeedback">
                    <span class="input-group-text">/ order</span>
                </div>
                <div id="agentCommissionRateFeedback" class="invalid-feedback d-none"></div>
                <div id="agentCommissionRateHelp" class="form-text mt-2">
                    Changes apply to future orders. Historical commissions are not affected.
                </div>
                <p class="text-muted mb-0 fs-14 mt-3">
                    This action is shown for UI review only and does not update the commission rate yet.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary text-white" id="agentCommissionRateSave">
                    Save Commission Rate
                </button>
            </div>
        </div>
    </div>
</div>
