{{-- Shared Create / Edit Order form styles --}}
<style>
        .order-create-prototype .order-create-section + .order-create-section {
            margin-top: 1.25rem;
        }

        .order-create-prototype .order-line-row {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            background: #fff;
        }

        .order-create-prototype .order-line-row .line-field-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.35rem;
            line-height: 1.2;
            min-height: 1.2em;
        }

        .order-create-prototype .order-line-row .line-control-height {
            height: 38px;
            min-height: 38px;
        }

        .order-create-prototype .order-line-row .form-control.line-control-height,
        .order-create-prototype .order-line-row .form-select.line-control-height {
            padding-top: 0.375rem;
            padding-bottom: 0.375rem;
        }

        .order-create-prototype .order-line-row .remove-line-btn.line-control-height {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding-top: 0;
            padding-bottom: 0;
            line-height: 1;
        }

        .order-create-prototype .order-line-row .line-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 1.25rem;
            margin-top: 0.65rem;
            padding-top: 0.55rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
            font-size: 12px;
            color: #64748b;
            line-height: 1.35;
        }

        .order-create-prototype .order-line-row .line-meta-item strong {
            font-weight: 600;
            color: #334155;
        }

        .order-create-prototype .order-line-row .line-selected-price {
            display: inline-block;
            width: 7.5rem;
            height: 28px;
            padding: 0.15rem 0.45rem;
            font-size: 12px;
            vertical-align: middle;
        }

        .order-create-prototype .order-line-row .line-selected-price-wrap.hidden {
            display: none;
        }

        .order-create-prototype #customerRiskPanel.hidden,
        .order-create-prototype #customerExtraPanel.hidden,
        .order-create-prototype #afterHoursPanel.hidden,
        .order-create-prototype #duplicatePanel.hidden,
        .order-create-prototype #assignedCourierPanel.hidden,
        .order-create-prototype #assignCourierError.hidden,
        .order-create-prototype #assignCourierDebug.hidden,
        .order-create-prototype .product-search-results.hidden,
        .order-create-prototype .orders-proto-modal.hidden {
            display: none;
        }

        .order-create-prototype .customer-banned-warning {
            margin-top: 0.75rem;
            padding: 0.65rem 0.85rem;
            border: 1px solid rgba(220, 53, 69, 0.35);
            border-radius: 8px;
            background: #f8d7da;
            color: #842029;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
        }

        .order-create-prototype .customer-banned-warning .banned-warning-title {
            display: block;
            margin-bottom: 0.15rem;
        }

        .order-create-prototype .customer-banned-warning .banned-warning-detail {
            display: block;
            font-weight: 500;
            color: #a71d2a;
        }

        .order-create-prototype .assign-courier-debug-pre {
            max-height: 240px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            background: rgba(255, 255, 255, 0.75);
            padding: 0.5rem 0.65rem;
            border-radius: 6px;
            font-size: 12px;
            margin: 0.35rem 0 0;
        }

        .order-create-prototype .market-checks {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }

        .order-create-prototype .product-search-wrap {
            position: relative;
        }

        .order-create-prototype .product-search-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            z-index: 15;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .product-search-option {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 0.65rem 0.85rem;
            font-size: 14px;
            color: inherit;
        }

        .order-create-prototype .product-search-option:hover,
        .order-create-prototype .product-search-option:focus {
            background: rgba(13, 110, 253, 0.06);
            outline: none;
        }

        .order-create-prototype .product-search-empty {
            padding: 0.75rem 0.85rem;
            font-size: 13px;
            color: #64748b;
        }

        .order-create-prototype .section-divider {
            border: 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin: 1.25rem 0;
        }

        .order-create-prototype .order-summary-review {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .order-create-prototype .order-summary-review-block + .order-summary-review-block {
            margin-top: 0.85rem;
        }

        .order-create-prototype .order-summary-review-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .order-create-prototype .order-summary-review-value {
            font-size: 14px;
            color: inherit;
            margin-bottom: 0;
            white-space: pre-line;
        }

        .order-create-prototype .order-summary-review-value.is-placeholder {
            color: #94a3b8;
        }

        .order-create-prototype .order-create-actions {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 0.5rem;
            padding-top: 1.25rem;
            padding-bottom: 0.5rem;
        }

        .order-create-prototype .order-create-actions .btn {
            width: 100%;
        }

        @media (min-width: 576px) {
            .order-create-prototype .order-create-actions .btn {
                width: auto;
            }
        }

        .order-create-prototype .sidebar-info-card + .sidebar-info-card {
            margin-top: 1rem;
        }

        .order-create-prototype .sidebar-info-card .card-body-compact {
            padding: 0.85rem 1rem;
        }

        .order-create-prototype .history-table {
            width: 100%;
            margin: 0;
            font-size: 12px;
        }

        .order-create-prototype .history-table th {
            font-weight: 600;
            color: #64748b;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.35rem 0.25rem;
            white-space: nowrap;
        }

        .order-create-prototype .history-table td {
            padding: 0.45rem 0.25rem;
            vertical-align: top;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .order-create-prototype .history-table tr:last-child td {
            border-bottom: 0;
        }

        .order-create-prototype .history-order-no {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-items {
            color: #64748b;
            max-width: 9rem;
        }

        .order-create-prototype .history-items-name {
            color: inherit;
        }

        .order-create-prototype .history-items-amount {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            margin-top: 0.15rem;
        }

        .order-create-prototype .history-reseller {
            min-width: 7.5rem;
            max-width: 11rem;
        }

        .order-create-prototype .history-reseller-company {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-reseller-name {
            font-size: 11px;
            color: #64748b;
        }

        .order-create-prototype .history-courier {
            min-width: 7rem;
            max-width: 10rem;
        }

        .order-create-prototype .history-courier-name {
            font-weight: 600;
            font-size: 12px;
        }

        .order-create-prototype .history-courier-tracking {
            font-size: 11px;
            color: #64748b;
        }

        .order-create-prototype .history-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem 0.55rem;
            margin-bottom: 0.85rem;
        }

        .order-create-prototype .history-summary-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.55rem;
            border-radius: 6px;
            background: #f1f5f9;
            font-size: 12px;
            color: #334155;
            line-height: 1.2;
        }

        .order-create-prototype .history-summary-chip strong {
            font-weight: 700;
            color: #0f172a;
        }

        .order-create-prototype .history-summary-chip.is-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }

        .order-create-prototype .history-summary-chip.is-returned {
            background: #fff3cd;
            color: #664d03;
        }

        .order-create-prototype .history-summary-chip.is-cancelled,
        .order-create-prototype .history-summary-chip.is-failed {
            background: #f8d7da;
            color: #842029;
        }

        .order-create-prototype .history-summary-chip.is-pending,
        .order-create-prototype .history-summary-chip.is-processing {
            background: #cfe2ff;
            color: #084298;
        }

        .order-create-prototype .history-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .history-pagination-meta {
            font-size: 12px;
            color: #64748b;
        }

        .order-create-prototype .history-pagination-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .order-create-prototype .history-pagination-actions .btn {
            min-width: 4.5rem;
        }

        .order-create-prototype .sidebar-empty {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }

        .order-create-prototype .order-timeline {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .order-create-prototype .order-timeline-item {
            position: relative;
            padding-left: 1.35rem;
            padding-bottom: 0.95rem;
        }

        .order-create-prototype .order-timeline-item:last-child {
            padding-bottom: 0;
        }

        .order-create-prototype .order-timeline-item::before {
            content: '';
            position: absolute;
            left: 0.28rem;
            top: 0.55rem;
            bottom: -0.2rem;
            width: 2px;
            background: rgba(15, 23, 42, 0.1);
        }

        .order-create-prototype .order-timeline-item:last-child::before {
            display: none;
        }

        .order-create-prototype .order-timeline-dot {
            position: absolute;
            left: 0;
            top: 0.28rem;
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.35);
            z-index: 1;
        }

        .order-create-prototype .order-timeline-dot.type-customer { background: #198754; box-shadow: 0 0 0 1px rgba(25, 135, 84, 0.35); }
        .order-create-prototype .order-timeline-dot.type-product { background: #0dcaf0; box-shadow: 0 0 0 1px rgba(13, 202, 240, 0.35); }
        .order-create-prototype .order-timeline-dot.type-courier { background: #6f42c1; box-shadow: 0 0 0 1px rgba(111, 66, 193, 0.35); }
        .order-create-prototype .order-timeline-dot.type-market { background: #fd7e14; box-shadow: 0 0 0 1px rgba(253, 126, 20, 0.35); }
        .order-create-prototype .order-timeline-dot.type-discount { background: #dc3545; box-shadow: 0 0 0 1px rgba(220, 53, 69, 0.35); }
        .order-create-prototype .order-timeline-dot.type-created { background: #0d6efd; }

        .order-create-prototype .order-timeline-time {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0.1rem;
        }

        .order-create-prototype .order-timeline-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 0.1rem;
        }

        .order-create-prototype .order-timeline-desc {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .order-create-prototype .badge-status {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }

        .order-create-prototype .bg-success-subtle { background: #d1e7dd; color: #0f5132; }
        .order-create-prototype .text-success { color: #198754; }
        .order-create-prototype .bg-warning-subtle { background: #fff3cd; color: #664d03; }
        .order-create-prototype .text-warning { color: #997404; }
        .order-create-prototype .bg-danger-subtle { background: #f8d7da; color: #842029; }
        .order-create-prototype .text-danger { color: #dc3545; }
        .order-create-prototype .bg-primary-subtle { background: #cfe2ff; color: #084298; }
        .order-create-prototype .text-primary { color: #0d6efd; }
        .order-create-prototype .bg-secondary-subtle { background: #e9ecef; color: #41464b; }
        .order-create-prototype .text-secondary { color: #6c757d; }

        .order-create-prototype .order-payment-bank-fields.hidden {
            display: none !important;
        }

        .order-create-prototype .order-payment-bank-fields {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .orders-proto-modal.modal-backdrop-proto {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-head {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-body {
            padding: 1.1rem 1.15rem;
        }

        .order-create-prototype .orders-proto-modal .modal-panel-proto .modal-foot {
            padding: 0.9rem 1.15rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
</style>
