@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden" id="resellerSettlementInvoicesPage">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <h3 class="mb-1">Settlement Invoices</h3>
                <p class="fs-15 text-body mb-0">
                    Your settlement invoices, deductions, and payment status. Amounts are persisted values only.
                </p>
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
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">Settlement Invoices</span>
                </li>
            </ol>
        </nav>

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-0">Invoice history</h4>
            </div>
            <div class="p-20">
                <div class="table-responsive">
                    <table class="table align-middle w-100" id="resellerSettlementInvoicesTable">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Invoice</th>
                                <th scope="col" class="fw-medium">Settlement date</th>
                                <th scope="col" class="fw-medium">Batch</th>
                                <th scope="col" class="fw-medium">Currency</th>
                                <th scope="col" class="fw-medium">Gross</th>
                                <th scope="col" class="fw-medium">Debt</th>
                                <th scope="col" class="fw-medium">Net payable</th>
                                <th scope="col" class="fw-medium">Outstanding</th>
                                <th scope="col" class="fw-medium">Payment status</th>
                                <th scope="col" class="fw-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                @php
                                    $currency = $invoice->currency_code_snapshot ?: '—';
                                @endphp
                                <tr>
                                    <td class="text-body fw-semibold">{{ $invoice->invoice_number }}</td>
                                    <td class="text-body">
                                        {{ optional($invoice->batch?->settlement_date)->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="text-body">{{ $invoice->batch?->batch_number ?: '—' }}</td>
                                    <td class="text-body">{{ $currency }}</td>
                                    <td class="text-body">{{ $currency }} {{ number_format((float) $invoice->gross_earnings_total, 2) }}</td>
                                    <td class="text-body">{{ $currency }} {{ number_format((float) $invoice->debt_deduction_total, 2) }}</td>
                                    <td class="text-body">{{ $currency }} {{ number_format((float) $invoice->net_payable, 2) }}</td>
                                    <td class="text-body">{{ $currency }} {{ number_format((float) $invoice->outstanding_balance, 2) }}</td>
                                    <td class="text-body">{{ $invoice->payment_status ?: '—' }}</td>
                                    <td class="text-end">
                                        <a
                                            href="{{ route('settlements.invoices.show', $invoice) }}"
                                            class="btn btn-sm btn-light border"
                                        >
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr id="resellerSettlementInvoicesEmptyState">
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No settlement invoices found for your account.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($invoices->hasPages())
                    <div class="mt-3">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
