@php
    $currency = $invoice->currency_code_snapshot ?: '—';
    $adjustmentTotal = (float) $invoice->adjustment_total;
    $transferFeeAmount = (float) ($invoice->transfer_fee_amount ?? 0);
    $transferFeeStatus = (string) ($invoice->transfer_fee_status ?? '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print · {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Georgia, "Times New Roman", serif;
            color: #111;
            margin: 24px;
            font-size: 13px;
        }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 20px 0 8px; }
        .meta { color: #444; margin-bottom: 18px; line-height: 1.5; }
        .totals { margin-top: 16px; font-size: 14px; }
        .totals div { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-size: 12px; }
        .note { margin-top: 16px; font-size: 12px; color: #555; }
        .numeric { text-align: right; white-space: nowrap; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            tr { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body id="resellerSettlementInvoicePrintPage">
    <div class="no-print" style="margin-bottom: 16px;">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('settlements.invoices.show', $invoice) }}">Back to invoice</a>
    </div>

    <h1>Settlement Invoice {{ $invoice->invoice_number }}</h1>
    <div class="meta">
        Batch: {{ $batch?->batch_number ?: '—' }}
        · Settlement date: {{ optional($batch?->settlement_date)->format('Y-m-d') ?? '—' }}
        · Market: {{ $batch?->market_code_snapshot ?: '—' }}<br>
        Currency: {{ $currency }}
        · Payment status: {{ $invoice->payment_status ?: '—' }}
    </div>

    <h2>Earning lines</h2>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Obligation</th>
                <th>Business date</th>
                <th class="numeric">Gross profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->earningLines as $line)
                <tr>
                    <td>{{ $line->order_number_snapshot ?: '—' }}</td>
                    <td>{{ $line->obligation_number_snapshot ?: '—' }}</td>
                    <td>{{ optional($line->business_date_snapshot)->format('Y-m-d') ?? '—' }}</td>
                    <td class="numeric">{{ number_format((float) $line->gross_reseller_profit, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No earning lines on this invoice.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Debt deductions</h2>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Debt obligation</th>
                <th>Type</th>
                <th class="numeric">Allocated</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->debtAllocations as $allocation)
                <tr>
                    <td>{{ $allocation->order_number_snapshot ?: '—' }}</td>
                    <td>{{ $allocation->debt_obligation_number_snapshot ?: '—' }}</td>
                    <td>{{ $allocation->obligation_type_snapshot ?: '—' }}</td>
                    <td class="numeric">{{ number_format((float) $allocation->allocated_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No debt deductions on this invoice.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div>Gross earnings: <strong>{{ $currency }} {{ number_format((float) $invoice->gross_earnings_total, 2) }}</strong></div>
        <div>Debt deduction: <strong>{{ $currency }} {{ number_format((float) $invoice->debt_deduction_total, 2) }}</strong></div>
        <div>Adjustment: <strong>{{ $currency }} {{ number_format($adjustmentTotal, 2) }}</strong></div>
        <div>Transfer fee: <strong>{{ $currency }} {{ number_format($transferFeeAmount, 2) }}</strong>@if ($transferFeeStatus === \Feeder\Core\Models\ResellerSettlementInvoice::TRANSFER_FEE_STATUS_NOT_CONFIGURED) <span>(transfer_fee_not_configured)</span>@endif</div>
        <div>Net payable (persisted): <strong>{{ $currency }} {{ number_format((float) $invoice->net_payable, 2) }}</strong></div>
        <div>Amount paid: <strong>{{ $currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</strong></div>
        <div>Outstanding: <strong>{{ $currency }} {{ number_format((float) $invoice->outstanding_balance, 2) }}</strong></div>
    </div>

    <p class="note">
        Printer-friendly view only. Opening this page does not create payments or mutate settlement data.
        Totals are the persisted invoice values only. An UNPAID invoice is not treated as paid.
    </p>
</body>
</html>
