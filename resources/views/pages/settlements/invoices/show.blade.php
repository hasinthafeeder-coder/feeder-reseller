@extends('layout_main.app')

@php
    $batch = $invoice->batch;
    $currency = $invoice->currency_code_snapshot ?: '—';
    $debtAllocations = $invoice->debtAllocations ?? collect();
    $orderRows = $orderRows ?? collect();
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="resellerSettlementInvoiceDetailPage">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Invoice {{ $invoice->invoice_number }}</h3>
                <p class="fs-15 text-body mb-0">
                    {{ $batch?->batch_number ?: '—' }}
                    · {{ optional($batch?->settlement_date)->format('Y-m-d') ?? '—' }}
                    · {{ $currency }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a
                    href="{{ route('settlements.invoices.print', $invoice) }}"
                    class="btn btn-light border"
                    target="_blank"
                    rel="noopener"
                >
                    Print
                </a>
                <a href="{{ route('settlements.invoices.index') }}" class="btn btn-light border">Back to invoices</a>
            </div>
        </div>

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                        <span class="text-body fs-14 hover">Dashboard</span>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('settlements.invoices.index') }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Settlement Invoices</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">{{ $invoice->invoice_number }}</span>
                </li>
            </ol>
        </nav>

        <div class="alert alert-light border mb-3" role="note">
            <div class="fw-semibold fs-14 mb-1">Persisted invoice totals only</div>
            <p class="fs-13 text-body mb-0">
                Figures below are stored on the invoice at settlement time. Print uses the same totals and never
                recalculates from live orders. Payment is recorded by Feeder Admin after the bank transfer.
            </p>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-0">Summary</h4>
            </div>
            <div class="p-20">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Currency</div>
                        <div class="fw-semibold">{{ $currency }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Payment status</div>
                        <div class="fw-semibold">{{ $invoice->payment_status ?: '—' }}</div>
                        @if (method_exists($invoice, 'paymentStatusCaption') && $invoice->paymentStatusCaption() !== '')
                            <div class="fs-12 text-muted mt-1">{{ $invoice->paymentStatusCaption() }}</div>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Gross earnings</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->gross_earnings_total, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Debt deduction</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->debt_deduction_total, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Adjustment</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->adjustment_total, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Transfer fee</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) ($invoice->transfer_fee_amount ?? 0), 2) }}</div>
                        @if (($invoice->transfer_fee_status ?? '') === \Feeder\Core\Models\ResellerSettlementInvoice::TRANSFER_FEE_STATUS_NOT_CONFIGURED)
                            <div class="fs-12 text-warning mt-1">transfer_fee_not_configured</div>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Previous invoice offset</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) ($invoice->previous_invoice_offset_total ?? 0), 2) }}</div>
                        <div class="fs-12 text-muted mt-1">Internal offset, not a bank transfer</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Net payable</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->net_payable, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Amount paid</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</div>
                        <div class="fs-12 text-muted mt-1">Cash only</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Offset applied</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) ($invoice->offset_applied_total ?? 0), 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-body mb-1">Outstanding</div>
                        <div class="fw-semibold">{{ $currency }} {{ number_format((float) $invoice->outstanding_balance, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4" id="resellerSettlementOrderBreakdown">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-1">Order-wise breakdown</h4>
                <p class="fs-13 text-body mb-0">
                    PLUS and MINUS report orders included on this invoice. Amounts come from settlement line and earning snapshots.
                    Invoice-level transfer fee, adjustment, previous offset, amount paid, and outstanding stay in the summary above.
                </p>
            </div>
            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Order / Waybill</th>
                                <th scope="col" class="fw-medium">Business Date</th>
                                <th scope="col" class="fw-medium">Order Total</th>
                                <th scope="col" class="fw-medium">Supplier Cost</th>
                                <th scope="col" class="fw-medium">Courier Charge</th>
                                <th scope="col" class="fw-medium">Company Commission</th>
                                <th scope="col" class="fw-medium">Service Charge</th>
                                <th scope="col" class="fw-medium">Eligible Earning</th>
                                <th scope="col" class="fw-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orderRows as $row)
                                @php
                                    $rowCurrency = $row['currency_code'] ?? $currency;
                                @endphp
                                <tr>
                                    <td class="text-body">
                                        <div class="fw-medium">{{ $row['order_number'] ?: '—' }}</div>
                                        @if (! empty($row['waybill']))
                                            <div class="fs-12">{{ $row['waybill'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-body">{{ $row['business_date'] ?: '—' }}</td>
                                    <td class="text-body">
                                        @if ($row['order_total'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['order_total'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @if ($row['supplier_cost'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['supplier_cost'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @if ($row['courier_charge'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['courier_charge'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @if ($row['company_commission'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['company_commission'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @if ($row['service_charge'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['service_charge'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @if ($row['eligible_earning'] !== null)
                                            {{ $rowCurrency }} {{ number_format((float) $row['eligible_earning'], 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-body">
                                        @include('partials.status-badge', [
                                            'status' => $row['order_status'] ?? null,
                                            'label' => $row['order_status_label'] ?? null,
                                        ])
                                        @if (! empty($row['delivered_at']))
                                            <div class="fs-12">Delivered {{ $row['delivered_at'] }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No order lines on this invoice.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($orderRows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    @include('partials.pagination', [
                        'paginator' => $orderRows,
                        'ariaLabel' => 'Reseller settlement order breakdown pagination',
                    ])
                @endif
            </div>
        </div>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-1">Debt deductions</h4>
            </div>
            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Order</th>
                                <th scope="col" class="fw-medium">Debt obligation</th>
                                <th scope="col" class="fw-medium">Type</th>
                                <th scope="col" class="fw-medium">Return reason</th>
                                <th scope="col" class="fw-medium">Allocated</th>
                                <th scope="col" class="fw-medium">Outstanding after</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($debtAllocations as $allocation)
                                <tr>
                                    <td class="text-body">{{ $allocation->order_number_snapshot ?: '—' }}</td>
                                    <td class="text-body">{{ $allocation->debt_obligation_number_snapshot ?: '—' }}</td>
                                    <td class="text-body">{{ $allocation->obligation_type_snapshot ?: '—' }}</td>
                                    <td class="text-body">{{ $allocation->return_reason_snapshot ?: '—' }}</td>
                                    <td class="text-body">
                                        {{ $allocation->currency_code_snapshot }}
                                        {{ number_format((float) $allocation->allocated_amount, 2) }}
                                    </td>
                                    <td class="text-body">
                                        {{ $allocation->currency_code_snapshot }}
                                        {{ number_format((float) $allocation->debt_outstanding_after, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No debt deductions on this invoice.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.badge-status-styles')
@endpush
