@extends('layout_main.app')

@php extract(require resource_path('views/pages/orders/partials/ui-urls-data.php')); @endphp

@push('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .orders-list-prototype .pool-lock-banner {
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.75rem 0.95rem;
            margin-bottom: 1rem;
            font-size: 13px;
            line-height: 1.45;
        }

        .orders-list-prototype .pool-lock-banner strong {
            display: block;
            margin-bottom: 0.15rem;
        }

        .orders-list-prototype .prototype-banner {
            border: 1px dashed rgba(180, 83, 9, 0.45);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.25rem;
        }

        .orders-list-prototype .prototype-toast {
            position: sticky;
            top: 0.75rem;
            z-index: 30;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .prototype-toast.hidden,
        .orders-list-prototype .hidden {
            display: none !important;
        }

        .orders-list-prototype .role-switcher {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.08);
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .role-switcher select {
            font-size: 12px;
            padding: 0.15rem 0.45rem;
            height: auto;
            min-height: 0;
            border-radius: 6px;
        }

        .orders-list-prototype .workspace-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .workspace-tab {
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #fff;
            color: #334155;
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
        }

        .orders-list-prototype .workspace-tab:hover {
            background: #f8fafc;
        }

        .orders-list-prototype .workspace-tab.is-active {
            background: #ef4923;
            border-color: #ef4923;
            color: #fff;
        }

        .orders-list-prototype .workspace-tab .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.35rem;
            height: 1.2rem;
            padding: 0 0.35rem;
            margin-left: 0.35rem;
            border-radius: 999px;
            font-size: 11px;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
        }

        .orders-list-prototype .workspace-tab.is-active .tab-count {
            background: rgba(255, 255, 255, 0.22);
        }

        .orders-list-prototype .filter-chip .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.2rem;
            height: 1.1rem;
            padding: 0 0.3rem;
            margin-left: 0.35rem;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
            vertical-align: middle;
        }

        .orders-list-prototype .filter-chip.is-active .tab-count {
            background: rgba(255, 255, 255, 0.22);
        }

        .orders-list-prototype .pool-lock-banner {
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.75rem 0.95rem;
            margin-bottom: 1rem;
            font-size: 13px;
            line-height: 1.45;
        }

        .orders-list-prototype .pool-lock-banner strong {
            display: block;
            margin-bottom: 0.15rem;
        }

        .orders-list-prototype .search-filter-card .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: stretch;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap {
            flex: 1 1 280px;
            position: relative;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap .material-symbols-outlined {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .orders-list-prototype .search-filter-card .search-input-wrap input {
            padding-left: 2.35rem;
        }

        .orders-list-prototype .filter-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.85rem;
        }

        .orders-list-prototype .filter-chip {
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #f8fafc;
            color: #334155;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
        }

        .orders-list-prototype .filter-chip.is-active {
            background: #ef4923;
            border-color: #ef4923;
            color: #fff;
        }

        .orders-list-prototype .advanced-filters {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 0.95rem;
            padding-top: 0.95rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
        }

        .orders-list-prototype .advanced-filters .label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.3rem;
        }

        .orders-list-prototype .bulk-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(239, 73, 35, 0.22);
            background: rgba(239, 73, 35, 0.06);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .orders-list-prototype .bulk-toolbar .bulk-count {
            font-size: 13px;
            font-weight: 600;
            color: #ef4923;
        }

        .orders-list-prototype .bulk-toolbar .bulk-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .orders-list-prototype .orders-table {
            width: 100%;
            margin: 0;
            font-size: 13px;
        }

        .orders-list-prototype .orders-table thead th {
            font-size: 11px;
            font-weight: 650;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
            white-space: nowrap;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.7rem 0.65rem;
            background-color: #fde5e0;
        }

        .orders-list-prototype .orders-table tbody td {
            padding: 0.8rem 0.65rem;
            vertical-align: top;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            background-color: #fff;
        }

        .orders-list-prototype .orders-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .orders-list-prototype .orders-table tbody tr.is-selected td {
            background-color: rgba(239, 73, 35, 0.06);
        }

        .orders-list-prototype .orders-table tbody tr.is-global-order td {
            background-color: #f8fafc;
        }

        .orders-list-prototype .order-action-link {
            color: #ef4923;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
        }

        .orders-list-prototype .order-action-link:hover,
        .orders-list-prototype .order-action-link:focus-visible {
            color: #c53716;
            font-weight: 700;
            text-decoration: underline;
        }

        .orders-list-prototype .order-number {
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.25;
        }

        .orders-list-prototype .order-sub,
        .orders-proto-modal .order-sub {
            font-size: 12px;
            color: #64748b;
            margin-top: 0.15rem;
            line-height: 1.35;
        }

        .orders-list-prototype .customer-name {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.25;
        }

        .orders-list-prototype .amount-value {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .orders-list-prototype .badge-status,
        .orders-list-prototype .badge-source,
        .orders-list-prototype .badge-assign,
        .orders-proto-modal .badge-status,
        .orders-proto-modal .badge-source {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.28rem 0.55rem;
            border-radius: 4px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .orders-list-prototype .badge-status.is-pending,
        .orders-proto-modal .badge-status.is-pending { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
        .orders-list-prototype .badge-status.is-confirmed,
        .orders-proto-modal .badge-status.is-confirmed { background: #cff4fc; color: #055160; }
        .orders-list-prototype .badge-status.is-processing,
        .orders-proto-modal .badge-status.is-processing { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-delivered,
        .orders-proto-modal .badge-status.is-delivered { background: #d1e7dd; color: #0f5132; }
        .orders-list-prototype .badge-status.is-returned,
        .orders-proto-modal .badge-status.is-returned { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-cancelled,
        .orders-proto-modal .badge-status.is-cancelled { background: #f8d7da; color: #842029; }

        .orders-list-prototype .badge-source.is-manual { background: #e9ecef; color: #41464b; }
        .orders-list-prototype .badge-source.is-meta { background: rgba(239, 73, 35, 0.1); color: #c0391a; }

        .orders-list-prototype .badge-assign.is-pool { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-assign.is-unassigned { background: #f1f5f9; color: #475569; }
        .orders-list-prototype .badge-assign.is-me { background: #d1e7dd; color: #0f5132; }
        .orders-list-prototype .badge-assign.is-other { background: rgba(239, 73, 35, 0.12); color: #ef4923; }

        .orders-list-prototype .assign-block .assign-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.25;
        }

        .orders-list-prototype .assign-block .assign-role {
            font-size: 11px;
            color: #64748b;
        }

        .orders-list-prototype .status-select {
            font-size: 12px;
            padding: 0.25rem 0.45rem;
            min-height: 0;
            height: auto;
            border-radius: 6px;
            max-width: 8.5rem;
        }

        .orders-list-prototype .orders-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 0;
            padding: 1.25rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .orders-list-prototype .orders-pagination-meta {
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .orders-pagination-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .orders-list-prototype .orders-pagination-actions .btn {
            min-width: 4.5rem;
        }

        .orders-list-prototype .empty-state {
            text-align: center;
            padding: 2.75rem 1.25rem;
        }

        .orders-list-prototype .empty-state .material-symbols-outlined {
            font-size: 42px;
            color: #ef4923;
            margin-bottom: 0.75rem;
        }

        .orders-list-prototype .empty-state h4 {
            font-size: 17px;
            font-weight: 650;
            margin-bottom: 0.35rem;
        }

        .orders-list-prototype .empty-state p {
            font-size: 14px;
            color: #64748b;
            margin: 0 auto;
            max-width: 420px;
        }

        .orders-proto-modal.modal-backdrop-proto {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .orders-proto-modal.hidden {
            display: none !important;
        }

        .orders-proto-modal .modal-panel-proto {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .orders-proto-modal .modal-panel-proto .modal-head {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .orders-proto-modal .modal-panel-proto .modal-body {
            padding: 1.1rem 1.15rem;
        }

        .orders-proto-modal .modal-panel-proto .modal-foot {
            padding: 0.9rem 1.15rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .orders-proto-modal .selected-order-list {
            margin: 0.75rem 0 0;
            padding-left: 1.1rem;
            font-size: 13px;
            color: #334155;
            max-height: 160px;
            overflow: auto;
        }

        .orders-list-prototype .results-meta {
            font-size: 12px;
            color: #64748b;
        }

        .orders-list-prototype .custom-date-row {
            display: none;
            grid-column: 1 / -1;
            grid-template-columns: repeat(2, minmax(0, 180px));
            gap: 0.75rem;
        }

        .orders-list-prototype .custom-date-row.is-visible {
            display: grid;
        }

        @media (max-width: 1199.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .orders-list-prototype .advanced-filters {
                grid-template-columns: 1fr;
            }
        }

        .orders-list-prototype .badge-status.is-1st-attempt { background: #cfe2ff; color: #084298; }
        .orders-list-prototype .badge-status.is-2nd-attempt { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-3rd-attempt { background: rgba(239, 73, 35, 0.12); color: #c0391a; }
        .orders-list-prototype .badge-status.is-hold { background: #fff3cd; color: #664d03; }
        .orders-list-prototype .badge-status.is-expiring { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
        .orders-list-prototype .badge-status.is-expired { background: #e9ecef; color: #41464b; }
        .orders-list-prototype .badge-status.is-pending-approval,
        .orders-proto-modal .badge-status.is-pending-approval {
            background: #fff3cd;
            color: #664d03;
        }
    </style>
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
@if ($orderUiIsArchitectureList)
    @if ($orderUiWorkspace === 'new')
        @include('pages.orders.partials.ui-new-orders')
    @elseif ($orderUiWorkspace === 'import')
        @include('pages.orders.partials.ui-import')
    @else
        @include('pages.orders.partials.ui-list-workspace', ['bootstrap' => $bootstrap ?? []])
    @endif
    @include('pages.orders.partials.ui-scripts')
@elseif ($orderUiIsArchivedList ?? false)
    @include('pages.orders.partials.ui-archived-orders')
@elseif ($orderUiIsCompletedList ?? false)
    @include('pages.orders.partials.ui-completed-orders')
@else
    @include('pages.orders.partials.ui-ongoing-orders')
@endif
@endsection

@if ($orderUiIsArchivedList ?? false)
@push('scripts')
    @include('pages.orders.partials.ui-archived-orders-scripts')
@endpush
@elseif ($orderUiIsCompletedList ?? false)
@push('scripts')
    @include('pages.orders.partials.ui-completed-orders-scripts')
@endpush
@elseif (! $orderUiIsArchitectureList)
@push('scripts')
    @include('pages.orders.partials.ui-ongoing-orders-scripts')
@endpush
@endif
