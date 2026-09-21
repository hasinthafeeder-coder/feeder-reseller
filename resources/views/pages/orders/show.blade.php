@extends('layout_main.app')

@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

@php
    use Feeder\Core\Enums\OrderAssignmentState;
    use Feeder\Core\Enums\OrderSource;
    use Feeder\Core\Enums\OrderStatus;
    use Feeder\Core\Support\CurrencyDisplay;
    use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;

    $order = $order;
    $currency = $order->currency ?? $order->market?->currency;
    $statusOptions = $statusOptions ?? [];
    $reactivationStatusOptions = $reactivationStatusOptions ?? [];
    $commentContextOptions = $commentContextOptions ?? [];
    $eligibleCcas = $eligibleCcas ?? collect();
    $canReactivate = $canReactivate ?? false;

    $statusLabel = $order->status instanceof OrderStatus
        ? $order->status->label()
        : (string) $order->status;

    $assignmentState = $order->assignmentState();
    $ccaName = trim(($order->cca?->profile?->first_name ?? '').' '.($order->cca?->profile?->last_name ?? ''));
    if ($ccaName === '') {
        $ccaName = $order->cca?->phone ?? '—';
    }
    $ccaDisplay = match ($assignmentState) {
        OrderAssignmentState::POOL => 'Order Pool',
        OrderAssignmentState::UNASSIGNED => 'Unassigned',
        default => $ccaName,
    };

    $sourceEnum = $order->source instanceof OrderSource
        ? $order->source
        : OrderSource::tryFrom((string) $order->source);
    $sourceLabel = $sourceEnum?->label() ?? (string) ($order->source ?? '—');
    $sourceIsMeta = $sourceEnum === OrderSource::META_IMPORT;

    $createdAtDisplay = optional($order->created_at)->format('M j, Y · g:i A') ?? '—';
    $marketDisplay = $order->market?->name ?? ($order->market_code_snapshot ?: '—');
    $supplierDisplay = $order->supplier?->company?->name ?? '—';

    $displayActor = static function ($user): string {
        if ($user === null) {
            return '—';
        }

        $name = trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? ''));

        return $name !== '' ? $name : ($user->phone ?? $user->email ?? 'User #'.$user->id);
    };

    $authUser = auth()->user();
    $isCcaActor = $authUser !== null
        && app(CallCenterAgentEligibilityService::class)->isEligible($authUser, (int) $authUser->company_id);
    $isAssignedToActor = $authUser !== null && (int) $order->cca_id === (int) $authUser->id;
    $orderInPool = $order->isInOrderPool();

    $canUpdateStatus = $authUser?->hasPermission('orders.status.update')
        && (! $isCcaActor || $isAssignedToActor);
    $canAssignCca = ! $isCcaActor && $authUser?->hasPermission('orders.cca.assign');
    $canComment = $authUser?->hasPermission('orders.comments.create')
        && (! $isCcaActor || $isAssignedToActor);
    $canUpdateDiscount = ! $isCcaActor && $authUser?->hasPermission('orders.discount.update');
    $canClaimFromPool = $isCcaActor
        && $orderInPool
        && $authUser?->hasPermission('orders.update');
    $canBookShipment = ($canBookShipment ?? false) && ! $isCcaActor;
    $eligibleCouriers = $eligibleCouriers ?? [];
    $canEditOrder = (bool) ($canEditOrder ?? false);
    $discountLocked = $order->isDiscountLocked();
    $isCancelled = $order->isCancelled();
    $shipment = $order->shipment;
    $shipmentBooked = $shipment !== null;
    $shipmentStatusLabel = $shipment?->status?->label() ?? ($shipment?->status ?? '—');
    $isHoldStatus = $order->status instanceof OrderStatus
        ? $order->status === OrderStatus::HOLD
        : (string) $order->status === 'HOLD';
    $uiState = request('ui_state');
    $isUiExpiring = $uiState === 'expiring';
    $isUiExpired = $uiState === 'expired';
    $singleAddress = collect([
        $order->address?->line1,
        $order->address?->line2,
    ])->filter()->implode(', ');
    if ($singleAddress === '' && $order->address?->full_address_text) {
        $singleAddress = $order->address->full_address_text;
    }
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
    @if ($canEditOrder ?? false)
        @include('pages.orders.partials.ui-manual-order-styles')
    @endif
    <style>
        .order-show-meta {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem 1.5rem;
            align-items: start;
        }

        .order-show-meta-item {
            min-width: 0;
        }

        .order-show-meta-label {
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.35rem;
            line-height: 1.2;
        }

        .order-show-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.35;
            word-break: break-word;
        }

        .order-show-meta-value .badge-source {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.28rem 0.55rem;
            border-radius: 4px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .order-show-meta-value .badge-source.is-manual {
            background: #e9ecef;
            color: #41464b;
        }

        .order-show-meta-value .badge-source.is-meta {
            background: rgba(239, 73, 35, 0.1);
            color: #c0391a;
        }

        .order-show-meta-value.is-muted {
            color: #64748b;
            font-weight: 500;
        }

        .order-status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .order-status-action-btn {
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
            line-height: 1.2;
            padding: 0.45rem 0.75rem;
        }

        .order-status-action-btn:hover:not(:disabled) {
            background: #fff;
            border-color: rgba(239, 73, 35, 0.45);
            color: #ef4923;
        }

        .order-status-action-btn.is-current,
        .order-status-action-btn:disabled.is-current {
            background: #ef4923;
            border-color: #ef4923;
            color: #fff;
            opacity: 1;
        }

        .order-payment-bank-fields.hidden {
            display: none !important;
        }

        .order-payment-bank-fields {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        @media (max-width: 991.98px) {
            .order-show-meta {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .order-show-meta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
@endpush

@section('content')
    <div class="main-content-container overflow-hidden {{ ($canEditOrder ?? false) ? 'order-create-prototype' : '' }}">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
            <div>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <h3 class="mb-0">Order {{ $order->order_number }}</h3>
                    <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 fs-13">
                        {{ $statusLabel }}
                    </span>
                    @if ($isCancelled)
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-10 fs-13">
                            Cancelled
                        </span>
                    @endif
                </div>
                @unless ($canEditOrder ?? false)
                    <p class="fs-15 text-body mb-0">
                        Operational order workspace for call-center and reseller actions.
                    </p>
                @endunless
            </div>
            <a href="{{ route('orders.index', ['workspace' => 'call-center']) }}" class="btn btn-light border">Back to Call Center</a>
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
                    <a href="{{ route('orders.index', ['workspace' => 'call-center']) }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">Call Center Orders</span>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <span class="text-secondary">{{ $order->order_number }}</span>
                </li>
            </ol>
        </nav>

        @if ($isUiExpiring)
            <div class="order-ui-state-banner is-expiring">
                <strong>Expiring</strong>
                This order is approaching expiration. Reactivate is visual only on this pass.
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-primary text-white" data-order-ui-toast="Reactivate is visual only. No expiration job runs from this screen.">Reactivate order</button>
                </div>
            </div>
        @endif

        @if ($isUiExpired)
            <div class="order-ui-state-banner is-expired">
                <strong>Expired</strong>
                This order is view-only. Confirmation and assignment actions should not run.
            </div>
        @endif

        @if ($isHoldStatus)
            <div class="order-ui-state-banner is-hold">
                <strong>Hold</strong>
                Reminder date and hold notes are shown below. Reminder scheduling is not connected yet.
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success mb-4" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header meta --}}
        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20">
                <div class="order-show-meta" aria-label="Order details">
                    <div class="order-show-meta-item">
                        <div class="order-show-meta-label">Source</div>
                        <div class="order-show-meta-value">
                            <span class="badge-source {{ $sourceIsMeta ? 'is-meta' : 'is-manual' }}">
                                {{ $sourceLabel }}
                            </span>
                        </div>
                    </div>
                    <div class="order-show-meta-item">
                        <div class="order-show-meta-label">Created</div>
                        <div class="order-show-meta-value">{{ $createdAtDisplay }}</div>
                    </div>
                    <div class="order-show-meta-item">
                        <div class="order-show-meta-label">Market</div>
                        <div class="order-show-meta-value">{{ $marketDisplay }}</div>
                    </div>
                    <div class="order-show-meta-item">
                        <div class="order-show-meta-label">Supplier</div>
                        <div class="order-show-meta-value">{{ $supplierDisplay }}</div>
                    </div>
                    <div class="order-show-meta-item">
                        <div class="order-show-meta-label">CCA</div>
                        <div class="order-show-meta-value {{ $assignmentState !== OrderAssignmentState::ASSIGNED ? 'is-muted' : '' }}">
                            {{ $ccaDisplay }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($canEditOrder)
            @include('pages.orders.partials.ui-manual-order-form', [
                'orderFormMode' => 'edit',
                'formDefaults' => $editFormDefaults ?? [],
                'formAction' => $catalogRoutes['update'] ?? route('orders.update', $order),
                'catalogRoutes' => $catalogRoutes ?? [],
                'canBanCustomer' => $canBanCustomer ?? false,
                'canAssignCourier' => $canAssignCourier ?? false,
                'shipmentBootstrap' => $shipmentBootstrap ?? null,
                'canSubmitBankTransfer' => $authUser?->hasPermission('orders.update')
                    && (! $isCcaActor || $isAssignedToActor)
                    && ! $isCancelled,
            ])

        @else
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                {{-- Customer --}}
                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="fs-18 mb-0">Customer</h4>
                        @if ($order->customer?->is_banned)
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-10 fs-13">Banned</span>
                        @elseif ($canBanCustomer ?? false)
                            <button type="button" class="btn btn-sm btn-outline-danger" data-open-order-ui-modal="banUserModal">
                                Ban this Customer
                            </button>
                        @endif
                    </div>
                    <div class="p-20">
                        @if ($order->customer?->is_banned)
                            <div class="alert alert-danger mb-3" role="alert">
                                This customer is globally banned. The order remains visible for operational handling.
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="fs-13 text-body mb-1">Name</div>
                                <div class="fw-medium">{{ $order->customer_name_snapshot }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="fs-13 text-body mb-1">Phone 1</div>
                                <div class="fw-medium">{{ $order->primary_phone_snapshot }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="fs-13 text-body mb-1">Phone 2</div>
                                <div class="fw-medium">{{ $order->secondary_phone_snapshot ?: '—' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="fs-13 text-body mb-1">Ban state</div>
                                <div class="fw-medium">
                                    @if ($order->customer?->is_banned)
                                        <span class="text-danger">Banned</span>
                                    @else
                                        Not banned
                                    @endif
                                </div>
                            </div>
                            @if ($order->address)
                                <div class="col-12">
                                    <div class="fs-13 text-body mb-1">Address</div>
                                    <div class="fw-medium">
                                        {{ $singleAddress !== '' ? $singleAddress : '—' }}
                                        @if ($order->address->city_name || $order->address->district_name)
                                            <br>
                                            {{ collect([$order->address->city_name, $order->address->district_name])->filter()->implode(', ') }}
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @include('pages.orders.partials.ui-order-status-actions')

                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom py-3">
                        <h4 class="fs-16 mb-0">Customer Order History</h4>
                    </div>
                    <div class="p-20">
                        <p class="fs-13 text-body mb-0">
                            Phone-based customer history, previous orders, and CRIB risk use the same layout as Create Order. Live lookup on this view is unchanged until Stage 2.
                        </p>
                    </div>
                </div>
                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Order Items</h4>
                    </div>
                    <div class="default-table-area style-two">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Variant</th>
                                        <th>Item code</th>
                                        <th>Barcode</th>
                                        <th>Qty</th>
                                        <th>Unit price</th>
                                        <th>Line total</th>
                                        <th>Weight</th>
                                        <th>Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($order->items as $item)
                                        <tr>
                                            <td>{{ $item->product_name_snapshot }}</td>
                                            <td>{{ $item->variant_name_snapshot }}</td>
                                            <td>{{ $item->product_variant_id ?: '—' }}</td>
                                            <td>{{ $item->barcode_snapshot ?: '—' }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ CurrencyDisplay::formatAmount($currency, $item->unit_selling_price) }}</td>
                                            <td>{{ CurrencyDisplay::formatAmount($currency, $item->line_selling_total) }}</td>
                                            <td>{{ number_format((float) $item->line_weight_total, 3) }} kg</td>
                                            <td>{{ $order->supplier?->company?->name ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-body">No items on this order.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Order Timeline</h4>
                    </div>
                    <div class="p-20">
                        <ul class="order-ui-timeline">
                            <li class="order-timeline-item">
                                <span class="order-timeline-dot type-created"></span>
                                <div class="order-timeline-time">{{ optional($order->created_at)->format('Y-m-d H:i') ?? '—' }} · {{ $displayActor($order->creator) }}</div>
                                <div class="order-timeline-title">Order created</div>
                                <p class="order-timeline-desc">{{ $order->source?->label() ?? 'Order' }} opened in the reseller workspace.</p>
                            </li>
                            @foreach ($order->ccaAssignments as $assignment)
                                <li class="order-timeline-item">
                                    <span class="order-timeline-dot type-customer"></span>
                                    <div class="order-timeline-time">{{ optional($assignment->assigned_at)->format('Y-m-d H:i') ?? '—' }} · {{ $displayActor($assignment->assignedByUser) }}</div>
                                    <div class="order-timeline-title">Assignment change</div>
                                    <p class="order-timeline-desc">Assigned to {{ $displayActor($assignment->cca) }}</p>
                                </li>
                            @endforeach
                            @foreach ($order->statusHistories as $history)
                                <li class="order-timeline-item">
                                    <span class="order-timeline-dot type-created"></span>
                                    <div class="order-timeline-time">{{ optional($history->created_at)->format('Y-m-d H:i') }} · {{ $displayActor($history->changedByUser) }}</div>
                                    <div class="order-timeline-title">Status change</div>
                                    <p class="order-timeline-desc">
                                        {{ $history->from_status?->label() ?? '—' }} → {{ $history->to_status?->label() ?? $history->to_status }}
                                        @if ($history->reason)
                                            · {{ $history->reason }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Status history --}}
                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Status History</h4>
                    </div>
                    <div class="default-table-area style-two">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Actor</th>
                                        <th>When</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($order->statusHistories as $history)
                                        <tr>
                                            <td>{{ $history->from_status?->label() ?? '—' }}</td>
                                            <td>{{ $history->to_status?->label() ?? $history->to_status }}</td>
                                            <td>{{ $displayActor($history->changedByUser) }}</td>
                                            <td>{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $history->reason ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-body">No status history.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                {{-- Financial summary --}}
                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Financial Summary</h4>
                    </div>
                    <div class="p-20">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Items subtotal</span>
                            <span class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $order->items_subtotal) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Discount</span>
                            <span class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $order->discount_amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Courier fee</span>
                            <span class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $order->courier_fee_amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Customer payable</span>
                            <span class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $order->customer_payable_amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body">Weight</span>
                            <span class="fw-medium">{{ number_format((float) $order->total_weight, 3) }} kg</span>
                        </div>

                        @if ($discountLocked)
                            <div class="alert alert-warning mt-3 mb-0" role="alert">
                                Discount locked
                                @if ($order->discount_locked_at)
                                    <div class="fs-13 mt-1">
                                        Locked at {{ $order->discount_locked_at->format('Y-m-d H:i') }}
                                    </div>
                                @endif
                            </div>
                        @elseif ($canUpdateDiscount)
                            <form method="POST" action="{{ route('orders.discount.update', $order) }}" class="mt-3">
                                @csrf
                                <label for="discountAmount" class="label fs-14 mb-2">Update discount</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control"
                                        id="discountAmount" name="discount_amount"
                                        value="{{ old('discount_amount', $order->discount_amount) }}" required>
                                    <button type="submit" class="btn btn-outline-primary">Save</button>
                                </div>
                            </form>
                        @endif

                        @if ($order->after_hours)
                            <div class="alert alert-warning mt-3 mb-0" role="alert">
                                After-hours order. Applicable penalty snapshot:
                                {{ CurrencyDisplay::formatAmount($currency, $order->after_hours_penalty_amount) }}
                                (applied if rejected/returned per business rules).
                            </div>
                        @endif
                    </div>
                </div>

                @include('pages.orders.partials.ui-order-payment', [
                    'canSubmitBankTransfer' => $authUser?->hasPermission('orders.update')
                        && (! $isCcaActor || $isAssignedToActor)
                        && ! $isCancelled,
                ])

                @include('pages.orders.partials.ui-order-cca-assignment', [
                    'fieldIdPrefix' => 'showCca',
                    'ccaName' => $ccaDisplay,
                ])

                @if ($isHoldStatus)
                    <div class="card bg-white rounded-10 border border-white mb-4">
                        <div class="p-20 border-bottom">
                            <h4 class="fs-18 mb-0">Hold reminder</h4>
                        </div>
                        <div class="p-20">
                            <div class="mb-3">
                                <label class="label fs-14 mb-2" for="showHoldDate">Reminder date</label>
                                <input type="date" class="form-control" id="showHoldDate">
                            </div>
                            <div class="mb-3">
                                <label class="label fs-14 mb-2" for="showHoldInfo">Reminder information</label>
                                <input type="text" class="form-control" id="showHoldInfo" placeholder="When and why to follow up">
                            </div>
                            <div class="mb-3">
                                <label class="label fs-14 mb-2" for="showHoldNote">Add note</label>
                                <input type="text" class="form-control" id="showHoldNote" placeholder="Hold note for the timeline">
                            </div>
                            <button type="button" class="btn btn-light border w-100" data-order-ui-toast="Hold reminder is visual only. No schedule or auto-cancel was created.">
                                Save hold note
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Shipment / Courier --}}
                <div class="card bg-white rounded-10 border border-white mb-4" id="shipmentPanel">
                    <div class="p-20 border-bottom">
                        <h4 class="fs-18 mb-0">Shipment / Courier</h4>
                    </div>
                    <div class="p-20">
                        @if ($shipmentBooked)
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Courier</div>
                                    <div class="fw-medium">{{ $shipment->courier?->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Courier service</div>
                                    <div class="fw-medium">{{ $shipment->service?->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">District</div>
                                    <div class="fw-medium">{{ $shipment->city?->district_name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">City</div>
                                    <div class="fw-medium">{{ $shipment->city?->city_name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Tracking ID</div>
                                    <div class="fw-medium">{{ $shipment->tracking_number ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Shipment status</div>
                                    <div class="fw-medium">{{ $shipmentStatusLabel }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Booked at</div>
                                    <div class="fw-medium">{{ optional($shipment->booked_at)->format('Y-m-d H:i') ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Courier fee</div>
                                    <div class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $shipment->courier_fee_snapshot) }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fs-13 text-body mb-1">Customer payable</div>
                                    <div class="fw-medium">{{ CurrencyDisplay::formatAmount($currency, $order->customer_payable_amount) }}</div>
                                </div>
                            </div>

                            <div class="fs-14 fw-medium mb-2">Shipment events</div>
                            @forelse ($shipment->events as $event)
                                <div class="@if (! $loop->last) border-bottom pb-2 mb-2 @endif fs-13">
                                    <div class="fw-medium">
                                        {{ $event->normalized_status ?: ($event->external_status ?: 'Event') }}
                                    </div>
                                    <div class="text-body">
                                        {{ $event->description ?: '—' }}
                                        · {{ optional($event->event_at)->format('Y-m-d H:i') ?? '—' }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-body fs-13">No shipment events.</div>
                            @endforelse
                        @elseif ($canBookShipment)
                            <form method="POST" action="{{ route('orders.shipment.book', $order) }}" id="shipmentBookingForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="shipmentCourier" class="label fs-14 mb-2">Courier</label>
                                    <select class="form-select form-control" id="shipmentCourier" name="courier_id" required>
                                        <option value="">Select courier</option>
                                        @foreach ($eligibleCouriers as $courier)
                                            <option value="{{ $courier['id'] }}"
                                                @selected((string) old('courier_id') === (string) $courier['id'])>
                                                {{ $courier['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="shipmentService" class="label fs-14 mb-2">Courier service</label>
                                    <select class="form-select form-control" id="shipmentService" name="courier_service_id" required disabled>
                                        <option value="">Select service</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="shipmentDistrict" class="label fs-14 mb-2">Destination State / District</label>
                                    <select class="form-select form-control" id="shipmentDistrict" name="district" required disabled>
                                        <option value="">Select district</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="shipmentCity" class="label fs-14 mb-2">City</label>
                                    <select class="form-select form-control" id="shipmentCity" name="courier_city_id" required disabled>
                                        <option value="">Select city</option>
                                    </select>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-body">Courier fee</span>
                                    <span class="fw-medium" id="shipmentFeePreview">—</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-body">Customer payable</span>
                                    <span class="fw-medium" id="shipmentPayablePreview">—</span>
                                </div>
                                <div class="text-danger fs-13 mb-3 d-none" id="shipmentLookupError"></div>
                                <button type="submit" class="btn btn-primary text-white w-100" id="bookShipmentBtn" disabled>
                                    Book Shipment
                                </button>
                            </form>
                            @if ($eligibleCouriers === [])
                                <div class="alert alert-warning mt-3 mb-0" role="alert">
                                    No eligible couriers for this order's supplier and market. Ensure supplier courier accounts and market pricing are configured.
                                </div>
                            @endif
                        @else
                            <div class="text-body">No shipment booked yet.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @unless ($canEditOrder)
        @include('pages.orders.partials.ui-ban-modal', [
            'banName' => $order->customer_name_snapshot,
            'banPhone1' => $order->primary_phone_snapshot,
            'banPhone2' => $order->secondary_phone_snapshot,
            'banUrl' => ($canBanCustomer ?? false) ? route('orders.customer.ban', $order) : '',
            'banMode' => 'order',
        ])
    @endunless
    @if ($canEditOrder && $canUpdateStatus && ! ($isCancelled && ! $canReactivate))
        @include('pages.orders.partials.ui-order-status-confirm-modal', [
            'order' => $order,
            'currentStatusLabel' => $order->status instanceof \Feeder\Core\Enums\OrderStatus
                ? $order->status->label()
                : (string) ($order->status ?? '—'),
        ])
    @endif
    @include('pages.orders.partials.ui-call-center-modal', [
        'poolConfirm' => [
            'orderNumber' => $order->order_number,
            'status' => $statusLabel,
            'currentCca' => $ccaDisplay,
            'customer' => $order->customer_name_snapshot,
            'phone' => $order->primary_phone_snapshot,
        ],
        'poolActionUrl' => $canAssignCca ? route('orders.cca.pool', $order) : null,
    ])
    <div id="orderUiToast" class="alert alert-success prototype-toast hidden" role="status"></div>
@endsection

@push('scripts')
    @if ($canEditOrder)
        @include('pages.orders.partials.ui-manual-order-scripts', [
            'orderFormMode' => 'edit',
            'formDefaults' => $editFormDefaults ?? [],
            'formEligibleCcas' => $formEligibleCcas ?? collect(),
            'markets' => $markets ?? collect(),
            'isCca' => $isCca ?? false,
            'catalogRoutes' => $catalogRoutes ?? [],
            'duplicateOrders' => $duplicateOrders ?? [],
            'oldItems' => $oldItems ?? [],
            'canAssignCourier' => $canAssignCourier ?? false,
            'shipmentBootstrap' => $shipmentBootstrap ?? null,
        ])
    @endif
    @include('pages.orders.partials.ui-scripts')
    <script>
    (function () {
        var fields = document.getElementById('orderBankTransferFields');
        if (!fields) return;

        document.querySelectorAll('[data-order-payment-method]').forEach(function (input) {
            input.addEventListener('change', function () {
                var isBank = input.value === 'bank' && input.checked;
                fields.classList.toggle('hidden', !isBank);
            });
        });
    })();
    </script>
@endpush

@if (! $shipmentBooked && $canBookShipment)
@push('scripts')
<script>
(function () {
    const currencyCode = @json($currency?->code ?? $order->currency_code_snapshot ?? '');
    const oldCourierId = @json(old('courier_id'));
    const oldServiceId = @json(old('courier_service_id'));
    const oldDistrict = @json(old('district'));
    const oldCityId = @json(old('courier_city_id'));

    const courierSelect = document.getElementById('shipmentCourier');
    const serviceSelect = document.getElementById('shipmentService');
    const districtSelect = document.getElementById('shipmentDistrict');
    const citySelect = document.getElementById('shipmentCity');
    const feeEl = document.getElementById('shipmentFeePreview');
    const payableEl = document.getElementById('shipmentPayablePreview');
    const errorEl = document.getElementById('shipmentLookupError');
    const bookBtn = document.getElementById('bookShipmentBtn');
    const form = document.getElementById('shipmentBookingForm');

    if (!courierSelect || !form) {
        return;
    }

    let bookingSubmitted = false;

    function formatMoney(amount) {
        const value = Number(amount);
        if (Number.isNaN(value)) {
            return '—';
        }
        const formatted = value.toFixed(2);
        return currencyCode ? (currencyCode + ' ' + formatted) : formatted;
    }

    function showError(message) {
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message || '';
        errorEl.classList.toggle('d-none', !message);
    }

    function resetSelect(select, placeholder, enabled) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
        select.disabled = !enabled;
        select.value = '';
    }

    function updateBookButton() {
        bookBtn.disabled = !(
            courierSelect.value
            && serviceSelect.value
            && districtSelect.value
            && citySelect.value
            && !bookingSubmitted
        );
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : null;
            throw new Error(firstError || payload.message || 'Lookup failed.');
        }

        return payload.data || [];
    }

    async function loadFeePreview(courierId) {
        if (!courierId) {
            feeEl.textContent = '—';
            payableEl.textContent = '—';
            return;
        }

        try {
            const data = await fetchJson(
                @json(route('orders.courier-fee-preview', $order))
                + '?courier_id=' + encodeURIComponent(courierId)
            );
            feeEl.textContent = formatMoney(data.courier_fee_amount);
            payableEl.textContent = formatMoney(data.customer_payable_amount);
            showError('');
        } catch (error) {
            feeEl.textContent = '—';
            payableEl.textContent = '—';
            showError(error.message || 'Unable to calculate courier fee.');
        }
    }

    async function loadServices(courierId, selectedId) {
        resetSelect(serviceSelect, 'Select service', false);
        resetSelect(districtSelect, 'Select district', false);
        resetSelect(citySelect, 'Select city', false);
        updateBookButton();

        if (!courierId) {
            return;
        }

        const services = await fetchJson(
            @json(route('orders.courier-services', $order))
            + '?courier_id=' + encodeURIComponent(courierId)
        );

        resetSelect(serviceSelect, 'Select service', true);
        services.forEach((service) => {
            const option = document.createElement('option');
            option.value = String(service.id);
            option.textContent = service.name;
            if (selectedId && String(selectedId) === String(service.id)) {
                option.selected = true;
            }
            serviceSelect.appendChild(option);
        });
        updateBookButton();
    }

    async function loadDistricts(serviceId, selectedDistrict) {
        resetSelect(districtSelect, 'Select district', false);
        resetSelect(citySelect, 'Select city', false);
        updateBookButton();

        if (!serviceId) {
            return;
        }

        const districts = await fetchJson(
            @json(route('orders.courier-districts', $order))
            + '?courier_service_id=' + encodeURIComponent(serviceId)
        );

        resetSelect(districtSelect, 'Select district', true);
        districts.forEach((row) => {
            const option = document.createElement('option');
            const stateId = row.id && Number(row.id) > 0 ? String(row.id) : '';
            const name = row.name || row.district || '';
            option.value = stateId || name;
            option.textContent = name;
            option.dataset.name = name;
            if (selectedDistrict && (selectedDistrict === name || selectedDistrict === stateId)) {
                option.selected = true;
            }
            districtSelect.appendChild(option);
        });
        updateBookButton();
    }

    async function loadCities(serviceId, stateIdOrDistrict, selectedCityId) {
        resetSelect(citySelect, 'Select city', false);
        updateBookButton();

        if (!serviceId || !stateIdOrDistrict) {
            return;
        }

        let url = @json(route('orders.courier-cities', $order))
            + '?courier_service_id=' + encodeURIComponent(serviceId);
        const numericStateId = Number(stateIdOrDistrict);
        if (Number.isInteger(numericStateId) && numericStateId > 0) {
            url += '&courier_state_id=' + encodeURIComponent(numericStateId);
        } else {
            url += '&district=' + encodeURIComponent(stateIdOrDistrict);
        }

        const cities = await fetchJson(url);

        resetSelect(citySelect, 'Select city', true);
        cities.forEach((city) => {
            const option = document.createElement('option');
            option.value = String(city.id);
            option.textContent = city.city_name;
            if (selectedCityId && String(selectedCityId) === String(city.id)) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });
        updateBookButton();
    }

    courierSelect.addEventListener('change', async () => {
        showError('');
        try {
            await loadServices(courierSelect.value);
            await loadFeePreview(courierSelect.value);
        } catch (error) {
            showError(error.message || 'Unable to load courier services.');
        }
        updateBookButton();
    });

    serviceSelect.addEventListener('change', async () => {
        showError('');
        try {
            await loadDistricts(serviceSelect.value);
        } catch (error) {
            showError(error.message || 'Unable to load districts.');
        }
        updateBookButton();
    });

    districtSelect.addEventListener('change', async () => {
        showError('');
        try {
            await loadCities(serviceSelect.value, districtSelect.value);
        } catch (error) {
            showError(error.message || 'Unable to load cities.');
        }
        updateBookButton();
    });

    citySelect.addEventListener('change', updateBookButton);

    form.addEventListener('submit', () => {
        if (bookingSubmitted) {
            return false;
        }
        bookingSubmitted = true;
        bookBtn.disabled = true;
        bookBtn.textContent = 'Booking…';
    });

    (async function restoreOldSelection() {
        if (!oldCourierId) {
            updateBookButton();
            return;
        }

        courierSelect.value = String(oldCourierId);
        try {
            await loadFeePreview(oldCourierId);
            await loadServices(oldCourierId, oldServiceId);
            if (oldServiceId) {
                await loadDistricts(oldServiceId, oldDistrict);
                if (oldDistrict) {
                    await loadCities(oldServiceId, oldDistrict, oldCityId);
                }
            }
        } catch (error) {
            showError(error.message || 'Unable to restore courier selection.');
        }
        updateBookButton();
    })();
})();
</script>
@endpush
@endif
