@php
    use Feeder\Core\Enums\OrderPaymentReviewStatus;
    use Feeder\Core\Support\CurrencyDisplay;

    $latestPayment = $latestBankTransfer ?? ($order->paymentSubmissions->first() ?? null);
    $reviewStatus = $latestPayment?->review_status instanceof OrderPaymentReviewStatus
        ? $latestPayment->review_status
        : OrderPaymentReviewStatus::tryFrom((string) ($latestPayment?->review_status ?? ''));
    $isPendingApproval = $isPendingApproval ?? ($reviewStatus === OrderPaymentReviewStatus::PENDING_REVIEW);
    $isRejected = $reviewStatus === OrderPaymentReviewStatus::REJECTED;
    $isApproved = $reviewStatus === OrderPaymentReviewStatus::APPROVED;
    $canSubmitBankTransfer = $canSubmitBankTransfer ?? false;
    $currency = $currency ?? ($order->currency ?? $order->market?->currency);
    $selectedPaymentMethod = old(
        'payment_method',
        ($latestPayment && ($isPendingApproval || $isRejected || $isApproved)) ? 'bank' : 'cod'
    );
    if (old('payment_method_ui') === 'bank' || old('reference_number') || old('payment_slip')) {
        $selectedPaymentMethod = 'bank';
    }
@endphp

<div class="card bg-white rounded-10 border border-white mb-4 mt-4" data-order-payment-card>
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
                @disabled(! $canSubmitBankTransfer && ! $isPendingApproval && ! $isRejected && ! $isApproved)
                data-order-payment-method>
            <label class="form-check-label fs-14" for="showPayBank">Bank transfer</label>
        </div>

        <div id="orderBankTransferFields" class="order-payment-bank-fields {{ $selectedPaymentMethod === 'bank' || $isPendingApproval || $isRejected || $isApproved ? '' : 'hidden' }}">
            @if ($latestPayment && $latestPayment->method?->value === 'BANK_TRANSFER')
                @php
                    $alertClass = match ($reviewStatus) {
                        OrderPaymentReviewStatus::APPROVED => 'success',
                        OrderPaymentReviewStatus::REJECTED => 'danger',
                        OrderPaymentReviewStatus::PENDING_REVIEW => 'warning',
                        default => 'secondary',
                    };
                    $statusHeading = match ($reviewStatus) {
                        OrderPaymentReviewStatus::PENDING_REVIEW => 'Pending Approval',
                        OrderPaymentReviewStatus::APPROVED => 'Payment approved',
                        OrderPaymentReviewStatus::REJECTED => 'Payment rejected',
                        default => 'Bank transfer submitted',
                    };
                @endphp
                <div class="alert alert-{{ $alertClass }} mb-3" role="status">
                    <strong>{{ $statusHeading }}</strong>
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
                    @if ($isRejected && $latestPayment->review_note)
                        <div class="fs-13 mt-2 fw-medium">Rejection reason: {{ $latestPayment->review_note }}</div>
                    @endif
                    @if ($latestPayment->slip_file_uuid)
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <a href="{{ route('files.view', $latestPayment->slip_file_uuid) }}"
                                class="btn btn-sm btn-light border" target="_blank" rel="noopener">
                                View proof
                            </a>
                            <a href="{{ route('files.download', $latestPayment->slip_file_uuid) }}"
                                class="btn btn-sm btn-light border">
                                Download proof
                            </a>
                        </div>
                    @endif
                    @if ($isPendingApproval)
                        <div class="fs-12 mt-2 mb-0">
                            This order is locked while Admin reviews the payment. You cannot edit or cancel until the review is complete.
                        </div>
                    @elseif ($isRejected)
                        <div class="fs-12 mt-2 mb-0">
                            You can submit a new bank transfer proof below. The previous submission is kept for audit history.
                        </div>
                    @endif
                </div>
            @endif

            @if ($canSubmitBankTransfer)
                <div id="orderBankTransferForm"
                    data-action="{{ route('orders.payment.bank-transfer', $order) }}"
                    data-csrf="{{ csrf_token() }}">
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferSlip">Payment proof</label>
                        <input type="file" class="form-control @error('payment_slip') is-invalid @enderror"
                            id="bankTransferSlip" name="payment_slip"
                            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                        @error('payment_slip')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="fs-12 text-body mt-1">PDF, JPG, PNG, or WEBP. Max 10 MB.</div>
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferReference">Reference number</label>
                        <input type="text" class="form-control @error('reference_number') is-invalid @enderror"
                            id="bankTransferReference" name="reference_number"
                            value="{{ old('reference_number') }}" maxlength="100" required>
                        @error('reference_number')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferAmount">Amount</label>
                        <input type="number" step="0.01" min="0.01"
                            class="form-control @error('amount') is-invalid @enderror"
                            id="bankTransferAmount" name="amount"
                            value="{{ old('amount', $order->customer_payable_amount) }}" required>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="label fs-14 mb-2" for="bankTransferDescription">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                            id="bankTransferDescription" name="description" rows="3" maxlength="2000"
                            placeholder="Transfer details for admin review" required>{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="button" class="btn btn-primary text-white w-100" id="requestBankTransferApprovalBtn"
                        data-open-modal="bankTransferConfirmModal">
                        {{ $isRejected ? 'Request Approval again' : 'Request Approval' }}
                    </button>
                    <p class="fs-12 text-body mb-0 mt-2">
                        Courier must already be assigned. Request Approval does not book the shipment.
                    </p>
                    @error('courier_id')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                    @error('courier_service_id')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                    @error('courier_city_id')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                    @error('supplier_courier_account')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                    @error('order')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                    @error('payment')
                        <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                    @enderror
                </div>
            @endif
        </div>

        @if ($selectedPaymentMethod !== 'bank' && ! $isPendingApproval && ! $isRejected && ! $isApproved)
            <p class="fs-13 text-body mb-0 mt-3">
                COD remains the default. Choose Bank transfer to request payment approval.
            </p>
        @endif
    </div>
</div>
