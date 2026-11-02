<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\ResellerSettlementInvoiceLine;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CompletedOrderArchiveWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Live rows for the reseller Ongoing, Completed, and Archived workspaces.
 *
 * Status mapping uses Feeder\Core\Enums\OrderStatus only.
 *
 * Ongoing (orders.index, no workspace):
 *   PRINT, PRINTED, PACKING, DISPATCHED
 *
 * Completed (workspace=archived) and Archived (workspace=archived-orders):
 *   DELIVERED, RETURNED
 *
 * The completed window is CompletedOrderArchiveWindow. It is not a status.
 *
 * Settlement labels are read from persisted reseller settlement invoice lines.
 * A non-void invoice line with line_payment_status PAID is Settled.
 * Every other completed or archived order is Settlement Pending.
 * This service does not calculate settlement amounts.
 */
class ResellerOrderWorkspaceService
{
    public const WORKSPACE_ONGOING = 'ongoing';

    public const WORKSPACE_COMPLETED = 'completed';

    public const WORKSPACE_ARCHIVED = 'archived';

    public const COMPLETED_WINDOW_DAYS = CompletedOrderArchiveWindow::DAYS;

    public const SETTLEMENT_PENDING = 'Settlement Pending';

    public const SETTLEMENT_SETTLED = 'Settled';

    /**
     * @return list<OrderStatus>
     */
    public static function ongoingStatuses(): array
    {
        return [
            OrderStatus::PRINT,
            OrderStatus::PRINTED,
            OrderStatus::PACKING,
            OrderStatus::DISPATCHED,
        ];
    }

    /**
     * @return list<OrderStatus>
     */
    public static function completedOutcomeStatuses(): array
    {
        return [
            OrderStatus::DELIVERED,
            OrderStatus::RETURNED,
        ];
    }

    /**
     * @return array{
     *     workspace: string,
     *     orders: list<array<string, mixed>>,
     *     summary: array<string, int>,
     *     couriers: list<array{id: string, name: string}>,
     *     products: list<array{id: string, name: string}>
     * }
     */
    public function payload(User $actor, string $workspaceQuery): array
    {
        $workspace = $this->resolveWorkspace($workspaceQuery);
        $orders = $this->ordersFor($actor, $workspace);

        return [
            'workspace' => $workspace,
            'orders' => $orders
                ->map(fn (Order $order) => $this->serialize($order, $workspace))
                ->values()
                ->all(),
            'summary' => $this->summary($orders, $workspace),
            'couriers' => $this->courierOptions($orders),
            'products' => $this->productOptions($orders),
        ];
    }

    public function resolveWorkspace(string $workspaceQuery): string
    {
        return match (trim($workspaceQuery)) {
            'archived' => self::WORKSPACE_COMPLETED,
            'archived-orders' => self::WORKSPACE_ARCHIVED,
            default => self::WORKSPACE_ONGOING,
        };
    }

    /**
     * @return Collection<int, Order>
     */
    public function ordersFor(User $actor, string $workspace): Collection
    {
        $companyId = (int) ($actor->company_id ?? 0);

        if ($companyId <= 0) {
            return collect();
        }

        $query = Order::query()
            ->where('reseller_company_id', $companyId);

        if ($workspace === self::WORKSPACE_ONGOING) {
            $query->whereIn('status', $this->statusValues(self::ongoingStatuses()))
                ->with([
                    'items',
                    'shipment.courier',
                    'draftCourier',
                ])
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        } else {
            $this->applyCompletedWindow($query, $workspace === self::WORKSPACE_COMPLETED);
            $query->with([
                'items',
                'shipment.courier',
                'draftCourier',
                'resellerSettlementInvoiceLines.invoice',
            ])
                ->orderByDesc('delivered_at')
                ->orderByDesc('returned_at')
                ->orderByDesc('id');
        }

        return $query->get();
    }

    private function applyCompletedWindow(Builder $query, bool $withinWindow): void
    {
        CompletedOrderArchiveWindow::apply($query, $withinWindow);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, int>
     */
    private function summary(Collection $orders, string $workspace): array
    {
        if ($workspace === self::WORKSPACE_ONGOING) {
            $counts = [];
            foreach (self::ongoingStatuses() as $status) {
                $counts[$status->value] = $orders
                    ->filter(fn (Order $order) => $order->status === $status)
                    ->count();
            }

            return $counts;
        }

        $settled = 0;
        $pending = 0;

        foreach ($orders as $order) {
            if ($this->isSettled($order)) {
                $settled++;
            } else {
                $pending++;
            }
        }

        return [
            'delivered' => $orders->filter(fn (Order $order) => $order->status === OrderStatus::DELIVERED)->count(),
            'returned' => $orders->filter(fn (Order $order) => $order->status === OrderStatus::RETURNED)->count(),
            'settlement_pending' => $pending,
            'settled' => $settled,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: string, name: string}>
     */
    private function courierOptions(Collection $orders): array
    {
        $options = [];

        foreach ($orders as $order) {
            $courier = $order->shipment?->courier ?? $order->draftCourier;
            if ($courier === null) {
                continue;
            }

            $options[(string) $courier->id] = [
                'id' => (string) $courier->id,
                'name' => (string) $courier->name,
            ];
        }

        $rows = array_values($options);
        usort($rows, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: string, name: string}>
     */
    private function productOptions(Collection $orders): array
    {
        $options = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $id = (string) $item->product_id;
                $options[$id] = [
                    'id' => $id,
                    'name' => $this->productLabel($item),
                ];
            }
        }

        $rows = array_values($options);
        usort($rows, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order, string $workspace): array
    {
        $items = $order->items;
        $courier = $order->shipment?->courier ?? $order->draftCourier;
        $productIds = $items
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $row = [
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) $order->customer_name_snapshot,
            'primary_phone' => (string) $order->primary_phone_snapshot,
            'product' => $items->map(fn (OrderItem $item) => $this->productLabel($item))->filter()->implode(', '),
            'product_id' => $productIds[0] ?? '',
            'product_ids' => $productIds,
            'courier' => $courier?->name,
            'courier_id' => $courier !== null ? (string) $courier->id : '',
            'waybill' => $order->shipment?->tracking_number,
            'cod_amount' => $order->customer_payable_amount === null
                ? null
                : number_format((float) $order->customer_payable_amount, 2),
            'currency_code' => (string) ($order->currency_code_snapshot ?? ''),
            'detail_url' => route('orders.show', ['order' => $order->uuid]),
        ];

        if ($workspace === self::WORKSPACE_ONGOING) {
            $updated = $order->updated_at;

            $row['status'] = $order->status instanceof OrderStatus
                ? $order->status->value
                : (string) $order->status;
            $row['updated_at'] = $updated?->timezone(config('app.timezone'))->format('d M Y, H:i');
            $row['updated_date'] = $updated?->timezone(config('app.timezone'))->toDateString();

            return $row;
        }

        $completedAt = $order->status === OrderStatus::RETURNED
            ? $order->returned_at
            : $order->delivered_at;
        $settlement = $this->settlementPresentation($order);

        $row['outcome'] = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status;
        $row['settlement_status'] = $settlement['settlement_status'];
        $row['invoice_number'] = $settlement['invoice_number'];
        $row['completed_at'] = $completedAt?->timezone(config('app.timezone'))->format('d M Y, h:i A');
        $row['completed_date'] = $completedAt?->timezone(config('app.timezone'))->toDateString() ?? '';

        return $row;
    }

    /**
     * @return array{settlement_status: string, invoice_number: ?string}
     */
    private function settlementPresentation(Order $order): array
    {
        $active = $order->resellerSettlementInvoiceLines
            ->filter(function (ResellerSettlementInvoiceLine $line): bool {
                $invoice = $line->invoice;

                return $invoice !== null
                    && (string) $invoice->instruction_status !== ResellerSettlementInvoice::INSTRUCTION_STATUS_VOIDED;
            })
            ->sortByDesc('id')
            ->values();

        $settled = $active->contains(
            fn (ResellerSettlementInvoiceLine $line) => (string) $line->line_payment_status === ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID
        );

        return [
            'settlement_status' => $settled ? self::SETTLEMENT_SETTLED : self::SETTLEMENT_PENDING,
            'invoice_number' => $active->first()?->invoice?->invoice_number,
        ];
    }

    private function isSettled(Order $order): bool
    {
        return $this->settlementPresentation($order)['settlement_status'] === self::SETTLEMENT_SETTLED;
    }

    private function productLabel(OrderItem $item): string
    {
        $name = trim((string) $item->product_name_snapshot);
        $variant = trim((string) $item->variant_name_snapshot);

        if ($name === '') {
            return $variant;
        }

        if ($variant === '' || strcasecmp($variant, 'Default') === 0 || str_contains($name, $variant)) {
            return $name;
        }

        return $name.' / '.$variant;
    }

    /**
     * @param  list<OrderStatus>  $statuses
     * @return list<string>
     */
    private function statusValues(array $statuses): array
    {
        return array_map(static fn (OrderStatus $status) => $status->value, $statuses);
    }
}
