{{-- Production commission rate modal (O1.4-D5-A). Updates current configuration only. --}}
@php
    $agentSlug = $agentSlug ?? ($agent['slug'] ?? null);
    $commissionField = 'agent_commission_per_order';
    $currentCommission = old(
        $commissionField,
        number_format((float) ($commissionRate ?? 0), 2, '.', '')
    );
    $hasCommissionError = $errors->has($commissionField);
@endphp
<div class="modal fade" id="agentCommissionModal" tabindex="-1" aria-labelledby="agentCommissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST"
                action="{{ route('ui.call-center.agents.commission.update', $agentSlug) }}"
                data-commission-form>
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="agentCommissionModalLabel">Change Commission Rate</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="agentCommissionRateInput" class="label fs-16 mb-2">Commission Rate</label>
                    <div class="input-group">
                        <span class="input-group-text">LKR</span>
                        <input type="number"
                            class="form-control @error($commissionField) is-invalid @enderror"
                            id="agentCommissionRateInput"
                            name="{{ $commissionField }}"
                            value="{{ $currentCommission }}"
                            min="0"
                            max="100000000"
                            step="0.01"
                            inputmode="decimal"
                            required
                            aria-describedby="agentCommissionRateHelp agentCommissionRateFeedback">
                        <span class="input-group-text">/ order</span>
                    </div>
                    @error($commissionField)
                        <div id="agentCommissionRateFeedback" class="invalid-feedback d-block">
                            {{ $message }}
                        </div>
                    @else
                        <div id="agentCommissionRateFeedback" class="invalid-feedback d-none"></div>
                    @enderror
                    <div id="agentCommissionRateHelp" class="form-text mt-2">
                        Changes apply to future orders. Historical commissions are not affected.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white" id="agentCommissionRateSave">
                        Save Commission Rate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($hasCommissionError)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalElement = document.getElementById('agentCommissionModal');
                if (modalElement && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endpush
@endif
