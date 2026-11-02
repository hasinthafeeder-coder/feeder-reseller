@extends('layout_main.app')

@php
    use Feeder\Core\Models\ResellerSettlementInvoice;

    $filters = $filters ?? ['date_from' => '', 'date_to' => ''];
    $invoices = $invoices ?? null;
    $marketSummaries = $marketSummaries ?? [];
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="resellerFinancialOverviewPage" data-party-financial-overview>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Reseller Financial Overview</h3>
                <p class="fs-15 text-body mb-0">Your settlement invoices. LKR and MYR stay on their own rows. Generated batches and invoices are not payments.</p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Financial Overview</span>
                    </li>
                </ol>
            </nav>
        </div>

        @include('pages.finance.partials.market-summary', [
            'overviewMarkets' => $overviewMarkets ?? collect(),
            'overviewMarketsUnavailable' => $overviewMarketsUnavailable ?? false,
            'marketSummaries' => $marketSummaries ?? [],
            'cards' => [
                [
                    'id' => 'reseller-total-earnings-card',
                    'amount_key' => 'total_earnings',
                    'title' => 'Total earnings',
                    'icon' => 'payments',
                    'caption' => 'Stored gross earnings total for this market.',
                    'foot' => 'Your account',
                ],
                [
                    'id' => 'reseller-total-deductions-card',
                    'amount_key' => 'total_deductions',
                    'title' => 'Total deductions',
                    'icon' => 'receipt_long',
                    'caption' => 'Stored debt deductions plus applied transfer fees for this market.',
                    'foot' => 'By currency',
                ],
                [
                    'id' => 'reseller-total-paid-card',
                    'amount_key' => 'total_paid',
                    'title' => 'Total paid',
                    'icon' => 'paid',
                    'caption' => 'Stored amount paid. An invoice offset is not cash.',
                    'foot' => 'Cash payments only',
                ],
                [
                    'id' => 'reseller-outstanding-card',
                    'amount_key' => 'outstanding',
                    'title' => 'Outstanding balance',
                    'icon' => 'account_balance',
                    'caption' => 'Stored outstanding balance for this market.',
                    'foot' => 'By currency',
                ],
            ],
        ])

        <div class="card bg-white rounded-10 border border-white mb-4" id="resellerFinancialBatchReport">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="fs-16 mb-1">Batch-wise report</h4>
                    <p class="fs-14 text-body mb-0">One row per invoice on your account. Each row keeps its own currency.</p>
                </div>
                <a href="{{ route('settlements.invoices.index') }}" class="btn btn-light border">Settlement invoices</a>
            </div>
            <div class="p-20 border-bottom">
                <form method="GET" action="{{ route('finance.overview') }}" id="resellerFinancialOverviewFilters" class="row g-3 align-items-end">
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="resellerOverviewDateFrom" class="label fs-14 mb-2">From</label>
                        <input type="date" class="form-control" id="resellerOverviewDateFrom" name="date_from" value="{{ $filters['date_from'] }}">
                        @error('date_from')
                            <div class="text-danger fs-14 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="resellerOverviewDateTo" class="label fs-14 mb-2">To</label>
                        <input type="date" class="form-control" id="resellerOverviewDateTo" name="date_to" value="{{ $filters['date_to'] }}">
                        @error('date_to')
                            <div class="text-danger fs-14 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-lg-6 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary text-white">Apply</button>
                        <a href="{{ route('finance.overview') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
                @error('page')
                    <div class="text-danger fs-14 mt-3">{{ $message }}</div>
                @enderror
                <p class="fs-12 text-body mb-0 mt-3">Dates limit this table to your invoices by settlement date. Summary cards are lifetime totals for each active market and ignore this date filter.</p>
            </div>
            <div class="default-table-area mx-minus-1" id="resellerFinancialOverviewTable">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Batch</th>
                                <th scope="col" class="fw-medium">Business Date</th>
                                <th scope="col" class="fw-medium">Order Count</th>
                                <th scope="col" class="fw-medium" title="Persisted gross_earnings_total.">Eligible Earnings</th>
                                <th scope="col" class="fw-medium" title="Non-fine debt allocation snapshots. RETURN_FINE amounts are under Fines.">Debts</th>
                                <th scope="col" class="fw-medium" title="RETURN_FINE allocation snapshots, or debt_deduction_total when allocations are unavailable.">Fines</th>
                                <th scope="col" class="fw-medium" title="transfer_fee_amount in transfer_fee_currency_code_snapshot. Not a payment.">Transfer Fee</th>
                                <th scope="col" class="fw-medium">Net Payable</th>
                                <th scope="col" class="fw-medium" title="amount_paid. Cash only.">Amount Paid</th>
                                <th scope="col" class="fw-medium">Outstanding Balance</th>
                                <th scope="col" class="fw-medium">Status</th>
                                <th scope="col" class="fw-medium text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices ?? [] as $invoice)
                                @php
                                    $currency = $invoice->currency_code_snapshot ?: null;
                                    $rawFeeCurrency = trim((string) ($invoice->transfer_fee_currency_code_snapshot ?? ''));
                                    $feeStatus = (string) $invoice->transfer_fee_status;
                                    $feeCurrencyDiffers = $rawFeeCurrency !== ''
                                        && strtoupper($rawFeeCurrency) !== strtoupper((string) $currency);
                                    $feeExcluded = $feeStatus === ResellerSettlementInvoice::TRANSFER_FEE_STATUS_APPLIED
                                        && ($rawFeeCurrency === '' || $feeCurrencyDiffers);
                                    $feeCurrency = $feeExcluded
                                        ? ($rawFeeCurrency !== '' ? $rawFeeCurrency : null)
                                        : ($rawFeeCurrency !== '' ? $rawFeeCurrency : $currency);
                                    $batch = $invoice->batch;
                                    $batchDate = optional($batch?->settlement_date)->format('Y-m-d') ?: '—';
                                    $batchNumber = $batch?->batch_number ?: '—';
                                    $orderCount = $invoice->overview_order_count ?? null;
                                    $debtsTotal = $invoice->overview_debts_total ?? null;
                                    $finesTotal = $invoice->overview_fines_total ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium text-body">
                                            <a href="{{ route('settlements.invoices.show', $invoice) }}">{{ $batchNumber }}</a>
                                        </div>
                                    </td>
                                    <td class="text-body">{{ $batchDate }}</td>
                                    <td class="text-body">{{ $orderCount !== null ? (int) $orderCount : '—' }}</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $invoice->gross_earnings_total, 'currency' => $currency])</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $debtsTotal, 'currency' => $currency])</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $finesTotal, 'currency' => $currency])</td>
                                    <td>
                                        @include('pages.finance.partials.stored-amount', ['amount' => $invoice->transfer_fee_amount, 'currency' => $feeCurrency])
                                        @if ((string) $invoice->transfer_fee_status === ResellerSettlementInvoice::TRANSFER_FEE_STATUS_NOT_CONFIGURED)
                                            <div class="fs-12 text-body">transfer_fee_not_configured</div>
                                        @elseif (filled($invoice->transfer_fee_status))
                                            <div class="fs-12 text-body">{{ $invoice->transfer_fee_status }}</div>
                                        @endif
                                        @if ($feeExcluded)
                                            <div class="fs-12 text-body">Not included in the {{ $currency ?: 'market' }} deduction total. No exchange rate is applied.</div>
                                        @endif
                                    </td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $invoice->net_payable, 'currency' => $currency])</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $invoice->amount_paid, 'currency' => $currency])</td>
                                    <td>@include('pages.finance.partials.stored-amount', ['amount' => $invoice->outstanding_balance, 'currency' => $currency])</td>
                                    <td class="text-body">
                                        @include('partials.status-badge', ['status' => $invoice->payment_status])
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('settlements.invoices.show', $invoice) }}" class="btn btn-sm btn-light border">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-5">No settlement invoices found for your account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($invoices instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    @include('partials.pagination', [
                        'paginator' => $invoices,
                        'ariaLabel' => 'Reseller financial overview pagination',
                    ])
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.badge-status-styles')
    <style>
        #resellerFinancialOverviewPage .finance-today-card h3 { font-size: 16px; line-height: 1.35; }
        #resellerFinancialOverviewPage .finance-today-foot { margin-top: 21px; }
        #resellerFinancialOverviewPage .finance-today-chip { padding: 3px 5px; }
    </style>
@endpush
