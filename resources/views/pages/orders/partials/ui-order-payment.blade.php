@php
    use Feeder\Core\Support\CurrencyDisplay;

    $latestPayment = $order->paymentSubmissions->first() ?? null;
    $selectedPaymentMethod = old('payment_method', $latestPayment?->method?->value === 'BANK_TRANSFER' ? 'bank' : 'cod');
    $canSubmitBankTransfer = $canSubmitBankTransfer ?? false;
    $currency = $currency ?? ($order->currency ?? $order->market?->currency);
@endphp

<div class="card bg-white rounded-10 border border-white mb-4 mt-4">
    <div class="p-20 border-bottom">
        <h4 class="fs-18 mb-0">Payment</h4>
    </div>
    <div class="p-20">
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method_ui" id="showPayCod" value="cod"
                @checked($selectedPaymentMethod !== 'bank')
                @disabled(! $canSubmitBankTransfer)
                data-order-payment-method>
            <label class="form-check-label fs-14" for="showPayCod">Cash on delivery</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method_ui" id="showPayBank" value="bank"
                @checked($selectedPaymentMethod === 'bank')
                @disabled(! $canSubmitBankTransfer)
                data-order-payment-method>
            <label class="form-check-label fs-14" for="showPayBank">Bank transfer</label>
        </div>

        <div id="orderBankTransferFields" class="order-payment-bank-fields {{ $selectedPaymentMethod === 'bank' ? '' : 'hidden' }}">
            @if ($latestPayment && $latestPayment->method?->value === 'BANK_TRANSFER')
                <div class="alert alert-{{ $latestPayment->review_status?->value === 'APPROVED' ? 'success' : ($latestPayment->review_status?->value === 'REJECTED' ? 'danger' : 'warning') }} mb-3" role="status">
                    <strong>Bank transfer {{ strtolower($latestPayment->review_status?->label() ?? 'submitted') }}</strong>
                    <div class="fs-13 mt-1">
                        Ref: {{ $latestPayment->reference_number ?: '—' }}
                        · Amount: {{ CurrencyDisplay::formatAmount($currency, $latestPayment->amount) }}
                        @if ($latestPayment->submitted_at)
                            · Submitted {{ $latestPayment->submitted_at->format('M j, Y · g:i A') }}
                        @endif
                    </div>
                    @if ($latestPayment->description)
                        <div class="fs-13 mt-1">{{ $latestPayment->description }}</div>
                    @endif
                    <div class="fs-12 mt-1 mb-0">Admin review and approval will be handled in the admin portal.</div>
                </div>
            @endif

            @if ($canSubmitBankTransfer)
                <form method="POST" action="{{ route('orders.payment.bank-transfer', $order) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferSlip">Payment slip</label>
                        <input type="file" class="form-control" id="bankTransferSlip" name="payment_slip"
                            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferReference">Reference number</label>
                        <input type="text" class="form-control" id="bankTransferReference" name="reference_number"
                            value="{{ old('reference_number', $latestPayment?->reference_number) }}" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferAmount">Amount</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="bankTransferAmount" name="amount"
                            value="{{ old('amount', $latestPayment?->amount ?? $order->customer_payable_amount) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferDescription">Description</label>
                        <textarea class="form-control" id="bankTransferDescription" name="description" rows="3" maxlength="2000"
                            placeholder="Transfer details for admin review" required>{{ old('description', $latestPayment?->description) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        {{ $latestPayment ? 'Resubmit bank transfer' : 'Submit bank transfer' }}
                    </button>
                    <p class="fs-12 text-body mb-0 mt-2">
                        Submit the transfer proof when confirming prepaid orders. Admin will review and approve later.
                    </p>
                </form>
            @endif
        </div>

        @if ($selectedPaymentMethod !== 'bank')
            <p class="fs-13 text-body mb-0 mt-3">
                COD remains the default. Choose Bank transfer to upload a payment slip for admin review.
            </p>
        @endif
    </div>
</div>
