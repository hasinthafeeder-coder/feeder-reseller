<?php

namespace App\Services\Settlement;

use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\ResellerDebtObligationType;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only reseller-portal queries for the authenticated reseller's invoices.
 *
 * Always scoped to the actor's own reseller_id. Never returns other resellers'
 * invoices. Totals/PDF consumers must use persisted invoice values only.
 */
class ResellerSettlementInvoiceReadService
{
    public function paginateForReseller(
        User $actor,
        int $perPage = 20,
        ?string $settlementDateFrom = null,
        ?string $settlementDateTo = null,
    ): LengthAwarePaginator {
        return $this->ownedInvoices($actor, $settlementDateFrom, $settlementDateTo)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Invoices for this reseller only. A caller cannot widen the reseller id.
     *
     * @return Builder<ResellerSettlementInvoice>
     */
    public function ownedInvoices(
        User $actor,
        ?string $settlementDateFrom = null,
        ?string $settlementDateTo = null,
    ): Builder {
        $query = ResellerSettlementInvoice::query()
            ->with([
                'batch:id,uuid,batch_number,settlement_date,status,market_code_snapshot',
            ])
            ->where('reseller_id', $actor->id)
            ->whereNull('voided_at');

        if ($settlementDateFrom !== null || $settlementDateTo !== null) {
            $query->whereHas('batch', function ($batch) use ($settlementDateFrom, $settlementDateTo): void {
                if ($settlementDateFrom !== null) {
                    $batch->whereDate('settlement_date', '>=', $settlementDateFrom);
                }

                if ($settlementDateTo !== null) {
                    $batch->whereDate('settlement_date', '<=', $settlementDateTo);
                }
            });
        }

        return $query;
    }

    /**
     * Financial overview rows. Same ownership and date filter as ownedInvoices(),
     * and the same void exclusions as the summary cards.
     */
    public function paginateForFinancialOverview(
        User $actor,
        int $perPage = 20,
        ?string $settlementDateFrom = null,
        ?string $settlementDateTo = null,
    ): LengthAwarePaginator {
        $paginator = $this->overviewInvoices($actor, $settlementDateFrom, $settlementDateTo)
            ->with([
                'earningLines:id,invoice_id,order_id,order_number_snapshot',
                'debtAllocations:id,invoice_id,order_id,order_number_snapshot,obligation_type_snapshot,allocated_amount',
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function (ResellerSettlementInvoice $invoice): ResellerSettlementInvoice {
            $presentation = $this->overviewRowPresentation($invoice);
            $invoice->setAttribute('overview_order_count', $presentation['order_count']);
            $invoice->setAttribute('overview_debts_total', $presentation['debts_total']);
            $invoice->setAttribute('overview_fines_total', $presentation['fines_total']);

            return $invoice;
        });

        return $paginator;
    }

    /**
     * Presentation-only overview columns. Does not change persisted invoice totals.
     *
     * Debts / fines are split from debt allocation snapshots. When an invoice has a
     * persisted debt_deduction_total but no allocation rows are available, the full
     * amount is shown under Fines (RETURN_FINE is the only debt obligation type today)
     * and Debts stays 0.00.
     *
     * @return array{order_count: int, debts_total: string, fines_total: string}
     */
    private function overviewRowPresentation(ResellerSettlementInvoice $invoice): array
    {
        $orderKeys = [];

        foreach ($invoice->earningLines ?? [] as $line) {
            $orderKeys[$this->orderRowKey($line->order_id, $line->order_number_snapshot ?? null)] = true;
        }

        foreach ($invoice->debtAllocations ?? [] as $allocation) {
            $orderKeys[$this->orderRowKey($allocation->order_id, $allocation->order_number_snapshot ?? null)] = true;
        }

        $debts = '0.00';
        $fines = '0.00';
        $allocationCount = 0;

        foreach ($invoice->debtAllocations ?? [] as $allocation) {
            $allocationCount++;
            $type = strtoupper(trim((string) ($allocation->obligation_type_snapshot ?? '')));
            $bucket = $type === ResellerDebtObligationType::RETURN_FINE->value ? 'fines' : 'debts';

            if ($bucket === 'fines') {
                $fines = $this->addStoredAmount($fines, $allocation->allocated_amount) ?? $fines;
            } else {
                $debts = $this->addStoredAmount($debts, $allocation->allocated_amount) ?? $debts;
            }
        }

        if ($allocationCount === 0) {
            $persistedDebt = $this->normalizeStoredAmount($invoice->debt_deduction_total);
            $fines = $persistedDebt ?? '0.00';
            $debts = '0.00';
        }

        return [
            'order_count' => count($orderKeys),
            'debts_total' => $debts,
            'fines_total' => $fines,
        ];
    }

    /**
     * Owned invoices that can be shown on the financial overview.
     *
     * The settlement invoice list keeps ownedInvoices(). Detail and print
     * authorization stay on assertOwned().
     *
     * @return Builder<ResellerSettlementInvoice>
     */
    private function overviewInvoices(
        User $actor,
        ?string $settlementDateFrom = null,
        ?string $settlementDateTo = null,
    ): Builder {
        return $this->ownedInvoices($actor, $settlementDateFrom, $settlementDateTo)
            ->where('instruction_status', '!=', ResellerSettlementInvoice::INSTRUCTION_STATUS_VOIDED)
            ->whereHas('batch', function (Builder $batch): void {
                $batch->whereNull('voided_at');
            });
    }

    /**
     * Lifetime summary for the authenticated reseller's own invoices.
     *
     * The reseller id is the actor id already used by ownedInvoices(). A caller
     * cannot pass another reseller id or a company id. Stored invoice totals are
     * summed. Earning formulas are not recalculated, and payment rows are not read.
     *
     * Voided invoices, voided instructions, and invoices on voided batches are
     * excluded. Date filters are not applied.
     *
     * @param  Collection<int, Market>  $markets
     * @return array<string, array{
     *     currency_code: string,
     *     total_earnings: ?string,
     *     total_deductions: ?string,
     *     total_paid: ?string,
     *     outstanding: ?string,
     *     notices: list<string>
     * }>
     */
    public function financialSummaries(User $actor, Collection $markets): array
    {
        $invoices = $this->overviewInvoices($actor)->get();

        $summaries = [];

        foreach ($markets as $market) {
            $marketCode = strtolower(trim((string) $market->code));
            $currency = strtoupper(trim((string) ($market->currency->iso_code ?? '')));

            if ($currency === '') {
                $summaries[$marketCode] = $this->emptyFinancialSummary('', false);

                continue;
            }

            $summary = $this->emptyFinancialSummary($currency, true);

            foreach ($invoices as $invoice) {
                $batchMarket = strtolower(trim((string) ($invoice->batch?->market_code_snapshot ?? '')));
                $invoiceCurrency = strtoupper(trim((string) ($invoice->currency_code_snapshot ?? '')));

                if ($batchMarket !== $marketCode) {
                    continue;
                }

                if ($invoiceCurrency !== $currency) {
                    $notice = $invoiceCurrency === ''
                        ? 'An invoice has no currency recorded. That invoice is not included in these totals.'
                        : 'An invoice is stored in '.$invoiceCurrency.', not '.$currency.'. That invoice is not included in these totals.';

                    if (! in_array($notice, $summary['notices'], true)) {
                        $summary['notices'][] = $notice;
                    }

                    continue;
                }

                $summary['total_earnings'] = $this->addStoredAmount(
                    $summary['total_earnings'],
                    $invoice->gross_earnings_total,
                );
                $summary['total_paid'] = $this->addStoredAmount(
                    $summary['total_paid'],
                    $invoice->amount_paid,
                );
                $summary['outstanding'] = $this->addStoredAmount(
                    $summary['outstanding'],
                    $invoice->outstanding_balance,
                );
                $summary['total_deductions'] = $this->addStoredAmount(
                    $summary['total_deductions'],
                    $invoice->debt_deduction_total,
                );
                $summary = $this->addAppliedTransferFee($summary, $invoice, $currency);
            }

            $summaries[$marketCode] = $summary;
        }

        return $summaries;
    }

    /**
     * @return array{
     *     currency_code: string,
     *     total_earnings: ?string,
     *     total_deductions: ?string,
     *     total_paid: ?string,
     *     outstanding: ?string,
     *     notices: list<string>
     * }
     */
    private function emptyFinancialSummary(string $currency, bool $supported): array
    {
        $amount = $supported ? '0.00' : null;

        return [
            'currency_code' => $currency,
            'total_earnings' => $amount,
            'total_deductions' => $amount,
            'total_paid' => $amount,
            'outstanding' => $amount,
            'notices' => $currency === ''
                ? ['This market has no currency code, so amounts are not totaled.']
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function addAppliedTransferFee(array $summary, ResellerSettlementInvoice $invoice, string $currency): array
    {
        if ((string) $invoice->transfer_fee_status !== ResellerSettlementInvoice::TRANSFER_FEE_STATUS_APPLIED) {
            return $summary;
        }

        $feeCurrency = strtoupper(trim((string) ($invoice->transfer_fee_currency_code_snapshot ?? '')));

        if ($feeCurrency === $currency) {
            $summary['total_deductions'] = $this->addStoredAmount($summary['total_deductions'], $invoice->transfer_fee_amount);

            return $summary;
        }

        $notice = $feeCurrency === ''
            ? 'An applied transfer fee has no currency recorded. That fee is not included in these totals.'
            : 'An applied transfer fee is stored in '.$feeCurrency.', not '.$currency.'. That fee is not included in these totals.';

        if (! in_array($notice, $summary['notices'], true)) {
            $summary['notices'][] = $notice;
        }

        return $summary;
    }

    private function addStoredAmount(?string $total, mixed $raw): ?string
    {
        if ($total === null) {
            return null;
        }

        $amount = $this->normalizeStoredAmount($raw);

        if ($amount === null) {
            return null;
        }

        return bcadd($total, $amount, 2);
    }

    private function normalizeStoredAmount(mixed $amount): ?string
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('The bcmath extension is required for financial overview totals.');
        }

        if (is_int($amount)) {
            $amount = (string) $amount;
        }

        if (is_float($amount) || ! is_string($amount)) {
            return null;
        }

        $trimmed = trim($amount);

        if (preg_match('/^-?\d+(\.\d+)?$/', $trimmed) !== 1) {
            return null;
        }

        $negative = str_starts_with($trimmed, '-');
        $absolute = $negative ? substr($trimmed, 1) : $trimmed;
        $scaled = bcadd($absolute, '0', 2);

        return $negative ? '-'.$scaled : $scaled;
    }

    /**
     * @return array{
     *     invoice: ResellerSettlementInvoice,
     *     orderRows: LengthAwarePaginator
     * }
     */
    public function loadOwnedInvoiceDetail(
        User $actor,
        ResellerSettlementInvoice $invoice,
        int $perPage = 25,
    ): array {
        $this->assertOwned($actor, $invoice);

        $invoice->load([
            'batch:id,uuid,batch_number,settlement_date,status,market_code_snapshot,timezone_snapshot',
            'earningLines.earningObligation:id,supplier_cost_snapshot',
            'earningLines.order:id,order_number,status,delivered_at',
            'earningLines.order.shipment:id,order_id,tracking_number',
            'debtAllocations',
            'debtAllocations.order:id,order_number,status,delivered_at',
            'debtAllocations.order.shipment:id,order_id,tracking_number',
            'offsetsIssued.targetInvoice:id,invoice_number',
            'offsetsReceived.sourceInvoice:id,invoice_number',
            'payments' => fn ($query) => $query->orderBy('id'),
            'effectivePayment',
        ]);

        $allRows = $this->orderBreakdown($invoice);
        $perPage = max(1, $perPage);
        $page = Paginator::resolveCurrentPage();
        $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();

        $orderRows = (new Paginator($slice, $allRows->count(), $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]))->withQueryString();

        return [
            'invoice' => $invoice,
            'orderRows' => $orderRows,
        ];
    }

    public function assertOwned(User $actor, ResellerSettlementInvoice $invoice): void
    {
        if ((int) $invoice->reseller_id !== (int) $actor->id || $invoice->voided_at !== null) {
            throw new NotFoundHttpException('Settlement invoice not found.');
        }
    }

    /**
     * Order-wise rows for one owned invoice, built only from persisted settlement
     * relationships. Includes PLUS (earning) and MINUS (debt allocation) orders
     * without duplicating the same order. Invoice-level transfer fee, adjustment,
     * offset, amount paid, and outstanding are not divided across orders.
     *
     * @return Collection<int, array{
     *     order_id: ?int,
     *     order_number: string,
     *     waybill: ?string,
     *     business_date: ?string,
     *     order_status: ?string,
     *     order_status_label: ?string,
     *     delivered_at: ?string,
     *     order_total: ?string,
     *     eligible_earning: ?string,
     *     supplier_cost: ?string,
     *     courier_charge: ?string,
     *     service_charge: ?string,
     *     company_commission: ?string,
     *     currency_code: ?string,
     *     deductions: list<array{type: string, amount: string, obligation_number: string, return_reason: ?string}>,
     *     deduction_total: ?string
     * }>
     */
    public function orderBreakdown(ResellerSettlementInvoice $invoice): Collection
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($invoice->earningLines as $line) {
            $key = $this->orderRowKey($line->order_id, $line->order_number_snapshot);
            $row = $rows[$key] ?? $this->emptyOrderRow(
                $line->order_id !== null ? (int) $line->order_id : null,
                (string) ($line->order_number_snapshot ?: ''),
                $line->currency_code_snapshot,
            );

            $row['business_date'] = optional($line->business_date_snapshot)?->format('Y-m-d') ?: $row['business_date'];
            $row['order_total'] = $this->addStoredAmount(
                $row['order_total'] ?? '0.00',
                $line->cod_amount_snapshot,
            );
            $row['eligible_earning'] = $this->addStoredAmount(
                $row['eligible_earning'] ?? '0.00',
                $line->gross_reseller_profit,
            );
            $row['supplier_cost'] = $this->addStoredAmount(
                $row['supplier_cost'] ?? '0.00',
                $line->earningObligation?->supplier_cost_snapshot,
            );
            $row['courier_charge'] = $this->addStoredAmount(
                $row['courier_charge'] ?? '0.00',
                $line->courier_charge_snapshot,
            );
            $row['service_charge'] = $this->addStoredAmount(
                $row['service_charge'] ?? '0.00',
                $line->service_charge_snapshot,
            );
            $row['company_commission'] = $this->addStoredAmount(
                $row['company_commission'] ?? '0.00',
                $line->company_commission_snapshot,
            );
            $row = $this->attachOrderIdentity($row, $line->order);

            $rows[$key] = $row;
        }

        foreach ($invoice->debtAllocations as $allocation) {
            $key = $this->orderRowKey($allocation->order_id, $allocation->order_number_snapshot);
            $row = $rows[$key] ?? $this->emptyOrderRow(
                $allocation->order_id !== null ? (int) $allocation->order_id : null,
                (string) ($allocation->order_number_snapshot ?: ''),
                $allocation->currency_code_snapshot,
            );

            $amount = $this->normalizeStoredAmount($allocation->allocated_amount);
            $row['deductions'][] = [
                'type' => (string) ($allocation->obligation_type_snapshot ?: '—'),
                'amount' => $amount ?? (string) $allocation->allocated_amount,
                'obligation_number' => (string) ($allocation->debt_obligation_number_snapshot ?: '—'),
                'return_reason' => filled($allocation->return_reason_snapshot)
                    ? (string) $allocation->return_reason_snapshot
                    : null,
            ];
            $row['deduction_total'] = $this->addStoredAmount(
                $row['deduction_total'] ?? '0.00',
                $allocation->allocated_amount,
            );
            $row = $this->attachOrderIdentity($row, $allocation->order);

            $rows[$key] = $row;
        }

        return collect(array_values($rows))
            ->sortBy([
                fn (array $row): string => (string) ($row['business_date'] ?? ''),
                fn (array $row): string => (string) ($row['order_number'] ?? ''),
            ])
            ->values();
    }

    /**
     * @return array{
     *     order_id: ?int,
     *     order_number: string,
     *     waybill: ?string,
     *     business_date: ?string,
     *     order_status: ?string,
     *     order_status_label: ?string,
     *     delivered_at: ?string,
     *     order_total: ?string,
     *     eligible_earning: ?string,
     *     supplier_cost: ?string,
     *     courier_charge: ?string,
     *     service_charge: ?string,
     *     company_commission: ?string,
     *     currency_code: ?string,
     *     deductions: list<array{type: string, amount: string, obligation_number: string, return_reason: ?string}>,
     *     deduction_total: ?string
     * }
     */
    private function emptyOrderRow(?int $orderId, string $orderNumber, mixed $currencyCode): array
    {
        return [
            'order_id' => $orderId,
            'order_number' => $orderNumber !== '' ? $orderNumber : '—',
            'waybill' => null,
            'business_date' => null,
            'order_status' => null,
            'order_status_label' => null,
            'delivered_at' => null,
            'order_total' => null,
            'eligible_earning' => null,
            'supplier_cost' => null,
            'courier_charge' => null,
            'service_charge' => null,
            'company_commission' => null,
            'currency_code' => filled($currencyCode) ? strtoupper(trim((string) $currencyCode)) : null,
            'deductions' => [],
            'deduction_total' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attachOrderIdentity(array $row, mixed $order): array
    {
        if ($order === null) {
            return $row;
        }

        if (($row['order_number'] ?? '—') === '—' && filled($order->order_number)) {
            $row['order_number'] = (string) $order->order_number;
        }

        $status = $order->status;
        if ($status instanceof OrderStatus) {
            $row['order_status'] = $status->value;
            $row['order_status_label'] = $status->label();
        } elseif ($status instanceof \BackedEnum) {
            $row['order_status'] = (string) $status->value;
            $resolved = OrderStatus::tryFrom((string) $status->value);
            $row['order_status_label'] = $resolved?->label() ?? (string) $status->value;
        } elseif (filled($status)) {
            $row['order_status'] = (string) $status;
            $resolved = OrderStatus::tryFrom((string) $status);
            $row['order_status_label'] = $resolved?->label() ?? (string) $status;
        }

        $deliveredAt = $order->delivered_at;
        $row['delivered_at'] = $deliveredAt !== null
            ? (string) optional($deliveredAt)->format('Y-m-d H:i')
            : $row['delivered_at'];

        $tracking = $order->shipment?->tracking_number;
        if (filled($tracking)) {
            $row['waybill'] = (string) $tracking;
        }

        return $row;
    }

    private function orderRowKey(mixed $orderId, mixed $orderNumber): string
    {
        if ($orderId !== null && $orderId !== '') {
            return 'id:'.(int) $orderId;
        }

        $number = trim((string) $orderNumber);

        return $number !== '' ? 'number:'.$number : 'unknown';
    }
}
