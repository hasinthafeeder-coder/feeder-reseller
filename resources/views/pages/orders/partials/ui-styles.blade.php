{{-- Extra visual states for the Stage 1 order UI architecture. Reuses Feeder order colors, radius, and type. --}}
<style>
    .order-ui-banner {
        border: 1px dashed rgba(180, 83, 9, 0.45);
        background: #fffbeb;
        color: #92400e;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        margin-bottom: 1.25rem;
        font-size: 13px;
        line-height: 1.45;
    }

    .order-ui-banner strong {
        display: block;
        margin-bottom: 0.15rem;
    }

    .order-ui-state-banner {
        border-radius: 10px;
        padding: 0.85rem 1rem;
        margin-bottom: 1.25rem;
        font-size: 13px;
        line-height: 1.45;
    }

    .order-ui-state-banner.is-hold {
        border: 1px solid rgba(180, 83, 9, 0.35);
        background: #fffbeb;
        color: #92400e;
    }

    .order-ui-state-banner.is-expiring {
        border: 1px solid rgba(239, 73, 35, 0.35);
        background: rgba(239, 73, 35, 0.08);
        color: #c0391a;
    }

    .order-ui-state-banner.is-expired {
        border: 1px solid rgba(132, 32, 41, 0.25);
        background: #f8d7da;
        color: #842029;
    }

    .order-ui-state-banner.is-confirm {
        border: 1px solid rgba(5, 81, 96, 0.25);
        background: #cff4fc;
        color: #055160;
    }

    .order-ui-preview-switch {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.65rem;
    }

    .order-ui-preview-switch .filter-chip,
    .order-create-prototype .filter-chip {
        border: 1px solid rgba(15, 23, 42, 0.1);
        background: #f8fafc;
        color: #334155;
        border-radius: 999px;
        padding: 0.28rem 0.7rem;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
    }

    .order-ui-preview-switch .filter-chip.is-active,
    .order-create-prototype .filter-chip.is-active {
        background: #ef4923;
        border-color: #ef4923;
        color: #fff;
    }

    .order-create-prototype .badge-source,
    .order-create-prototype .badge-assign,
    .order-create-prototype .badge-status {
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

    .order-create-prototype .badge-source.is-manual { background: #e9ecef; color: #41464b; }
    .order-create-prototype .badge-source.is-meta { background: rgba(239, 73, 35, 0.1); color: #c0391a; }
    .order-create-prototype .badge-assign.is-pool { background: #fff3cd; color: #664d03; }
    .order-create-prototype .badge-assign.is-unassigned { background: #f1f5f9; color: #475569; }
    .order-create-prototype .badge-assign.is-other { background: rgba(239, 73, 35, 0.12); color: #ef4923; }

    .prototype-toast.hidden,
    .order-create-prototype .hidden,
    .orders-list-prototype .hidden {
        display: none !important;
    }

    .prototype-toast {
        position: sticky;
        top: 0.75rem;
        z-index: 30;
        margin-bottom: 1rem;
    }

    .order-ui-choice-card {
        border: 1px solid rgba(15, 23, 42, 0.1);
        border-radius: 10px;
        padding: 0.85rem 1rem;
        background: #fff;
        cursor: pointer;
    }

    .order-ui-choice-card + .order-ui-choice-card {
        margin-top: 0.65rem;
    }

    .order-ui-choice-card.is-selected {
        border-color: #ef4923;
        background: rgba(239, 73, 35, 0.06);
    }

    .order-ui-choice-card .choice-title {
        font-size: 14px;
        font-weight: 650;
        color: #0f172a;
    }

    .order-ui-choice-card .choice-copy {
        font-size: 12px;
        color: #64748b;
        margin: 0.2rem 0 0;
    }

    .order-ui-upload {
        border: 1px dashed rgba(15, 23, 42, 0.18);
        border-radius: 10px;
        background: #f8fafc;
        padding: 2rem 1.25rem;
        text-align: center;
    }

    .order-ui-upload.is-dragover {
        border-color: #ef4923;
        background: rgba(239, 73, 35, 0.06);
    }

    .order-ui-upload .material-symbols-outlined {
        font-size: 42px;
        color: #ef4923;
        margin-bottom: 0.75rem;
    }

    .orders-list-prototype .badge-status.is-1st-attempt,
    .order-create-prototype .badge-status.is-1st-attempt,
    .orders-proto-modal .badge-status.is-1st-attempt { background: #cfe2ff; color: #084298; }

    .orders-list-prototype .badge-status.is-2nd-attempt,
    .order-create-prototype .badge-status.is-2nd-attempt,
    .orders-proto-modal .badge-status.is-2nd-attempt { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-3rd-attempt,
    .order-create-prototype .badge-status.is-3rd-attempt,
    .orders-proto-modal .badge-status.is-3rd-attempt { background: rgba(239, 73, 35, 0.12); color: #c0391a; }

    .orders-list-prototype .badge-status.is-hold,
    .order-create-prototype .badge-status.is-hold,
    .orders-proto-modal .badge-status.is-hold { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-pending-approval,
    .order-create-prototype .badge-status.is-pending-approval,
    .orders-proto-modal .badge-status.is-pending-approval { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-expiring,
    .order-create-prototype .badge-status.is-expiring,
    .orders-proto-modal .badge-status.is-expiring { background: rgba(239, 73, 35, 0.12); color: #ef4923; }

    .orders-list-prototype .badge-status.is-expired,
    .order-create-prototype .badge-status.is-expired,
    .orders-proto-modal .badge-status.is-expired { background: #e9ecef; color: #41464b; }

    .orders-list-prototype .badge-status.is-completed,
    .order-create-prototype .badge-status.is-completed { background: #d1e7dd; color: #0f5132; }

    .orders-list-prototype .badge-status.is-print,
    .order-create-prototype .badge-status.is-print,
    .orders-proto-modal .badge-status.is-print { background: #cfe2ff; color: #084298; }

    .orders-list-prototype .badge-status.is-printed,
    .order-create-prototype .badge-status.is-printed,
    .orders-proto-modal .badge-status.is-printed { background: #e2e3ff; color: #3d3d8f; }

    .orders-list-prototype .badge-status.is-packing,
    .order-create-prototype .badge-status.is-packing,
    .orders-proto-modal .badge-status.is-packing { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-dispatched,
    .order-create-prototype .badge-status.is-dispatched,
    .orders-proto-modal .badge-status.is-dispatched { background: #cff4fc; color: #055160; }

    .orders-list-prototype .badge-status.is-delivered,
    .orders-proto-modal .badge-status.is-delivered { background: #d1e7dd; color: #0f5132; }

    .orders-list-prototype .badge-status.is-returned,
    .orders-proto-modal .badge-status.is-returned { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-settlement-pending,
    .orders-proto-modal .badge-status.is-settlement-pending { background: #fff3cd; color: #664d03; }

    .orders-list-prototype .badge-status.is-settled,
    .orders-proto-modal .badge-status.is-settled { background: #d1e7dd; color: #0f5132; }

    .orders-list-prototype .badge-status.is-settlement-adjustment,
    .orders-proto-modal .badge-status.is-settlement-adjustment { background: #e9ecef; color: #41464b; }

    .orders-list-prototype .completed-settlement-note {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #f8fafc;
        color: #475569;
        border-radius: 8px;
        padding: 0.65rem 0.85rem;
        font-size: 12px;
        line-height: 1.45;
    }

    .orders-list-prototype .completed-settlement-note p:last-child {
        margin-bottom: 0;
    }

    .orders-list-prototype .settlement-component-label {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        border-radius: 4px;
        padding: 0.05rem 0.35rem;
        margin-right: 0.15rem;
    }

    .orders-list-prototype .settlement-component-label.is-plus {
        background: #d1e7dd;
        color: #0f5132;
    }

    .orders-list-prototype .settlement-component-label.is-minus {
        background: #e9ecef;
        color: #41464b;
    }

    .orders-list-prototype .completed-settlement-hint {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    .orders-list-prototype .completed-advanced-filters {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .orders-list-prototype .completed-detail-row {
        display: grid;
        grid-template-columns: 140px minmax(0, 1fr);
        gap: 0.5rem 1rem;
        padding: 0.55rem 0;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        font-size: 13px;
    }

    .orders-list-prototype .completed-detail-label {
        color: #64748b;
        font-weight: 600;
    }

    .orders-list-prototype .completed-detail-value {
        color: #0f172a;
    }

    .orders-list-prototype .completed-detail-note {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #f8fafc;
        color: #475569;
        border-radius: 8px;
        padding: 0.7rem 0.85rem;
        font-size: 12px;
        line-height: 1.45;
    }

    .orders-list-prototype .ongoing-summary-cards {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .orders-list-prototype .ongoing-summary-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #fff;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        text-align: left;
        width: 100%;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .orders-list-prototype .ongoing-summary-card:hover,
    .orders-list-prototype .ongoing-summary-card.is-active {
        border-color: rgba(239, 73, 35, 0.35);
        box-shadow: 0 0 0 1px rgba(239, 73, 35, 0.12);
    }

    .orders-list-prototype .ongoing-summary-card .summary-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.35rem;
    }

    .orders-list-prototype .ongoing-summary-card .summary-count {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.15rem;
        line-height: 1.2;
    }

    .orders-list-prototype .ongoing-summary-card .summary-supporting {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #94a3b8;
    }

    @media (max-width: 991.98px) {
        .orders-list-prototype .ongoing-summary-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .orders-list-prototype .completed-advanced-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .orders-list-prototype .ongoing-summary-cards {
            grid-template-columns: 1fr;
        }

        .orders-list-prototype .completed-advanced-filters {
            grid-template-columns: 1fr;
        }

        .orders-list-prototype .completed-detail-row {
            grid-template-columns: 1fr;
        }
    }

    .order-ui-import-state {
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

    .order-ui-import-state.is-valid { background: #d1e7dd; color: #0f5132; }
    .order-ui-import-state.is-created { background: #cff4fc; color: #055160; }
    .order-ui-import-state.is-banned { background: #f8d7da; color: #842029; }
    .order-ui-import-state.is-invalid { background: #fff3cd; color: #664d03; }

    .order-ui-confirm-action {
        background: #0f5132 !important;
        border-color: #0f5132 !important;
        color: #fff !important;
    }

    .order-ui-confirm-action:hover {
        background: #0c4128 !important;
        border-color: #0c4128 !important;
        color: #fff !important;
    }

    .order-ui-readonly .form-control,
    .order-ui-readonly .form-select {
        background: #f8fafc;
        pointer-events: none;
    }

    .order-ui-new-card {
        height: 100%;
    }

    .order-ui-new-card .material-symbols-outlined {
        font-size: 32px;
        color: #ef4923;
    }

    .order-create-prototype .order-timeline,
    .order-ui-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .order-ui-timeline .order-timeline-item {
        position: relative;
        padding-left: 1.35rem;
        padding-bottom: 0.95rem;
    }

    .order-ui-timeline .order-timeline-item:last-child {
        padding-bottom: 0;
    }

    .order-ui-timeline .order-timeline-item::before {
        content: '';
        position: absolute;
        left: 0.28rem;
        top: 0.55rem;
        bottom: -0.2rem;
        width: 2px;
        background: rgba(15, 23, 42, 0.1);
    }

    .order-ui-timeline .order-timeline-item:last-child::before {
        display: none;
    }

    .order-ui-timeline .order-timeline-dot {
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

    .order-ui-timeline .order-timeline-dot.type-customer { background: #198754; box-shadow: 0 0 0 1px rgba(25, 135, 84, 0.35); }
    .order-ui-timeline .order-timeline-dot.type-courier { background: #6f42c1; box-shadow: 0 0 0 1px rgba(111, 66, 193, 0.35); }
    .order-ui-timeline .order-timeline-dot.type-created { background: #0d6efd; }
    .order-ui-timeline .order-timeline-time { font-size: 11px; color: #64748b; margin-bottom: 0.1rem; }
    .order-ui-timeline .order-timeline-title { font-size: 13px; font-weight: 600; margin-bottom: 0.1rem; }
    .order-ui-timeline .order-timeline-desc { font-size: 12px; color: #64748b; margin: 0; }

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

    .order-ui-global-search {
        padding: 0.55rem 0.75rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 8px;
        background: #f8fafc;
    }

    .order-ui-global-search .form-check-label {
        color: #334155;
        font-weight: 600;
    }

    .order-ui-global-search .order-sub {
        font-weight: 400;
        margin-left: 0.35rem;
    }

    .orders-list-prototype .advanced-filters {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .order-ui-autocomplete {
        position: relative;
    }

    .order-ui-autocomplete-list {
        position: absolute;
        z-index: 20;
        left: 0;
        right: 0;
        top: calc(100% + 0.25rem);
        margin: 0;
        padding: 0.35rem 0;
        list-style: none;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        max-height: 220px;
        overflow: auto;
    }

    .order-ui-autocomplete-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        font-size: 13px;
        color: #0f172a;
    }

    .order-ui-autocomplete-list li:hover,
    .order-ui-autocomplete-list li:focus {
        background: rgba(239, 73, 35, 0.08);
        outline: none;
    }

    .order-ui-suggest-name {
        font-weight: 600;
    }

    .order-ui-suggest-code {
        font-size: 11px;
        color: #64748b;
        white-space: nowrap;
    }

    .orders-list-prototype .badge-source.is-global {
        background: #e7f1ff;
        color: #0b5ed7;
    }

    .orders-list-prototype .orders-table tbody tr.is-global-order {
        background: #f8fafc;
    }

    .orders-list-prototype .order-ui-select-col {
        width: 2.25rem;
        text-align: center;
        vertical-align: middle !important;
    }

    .orders-list-prototype .order-ui-select-disabled {
        color: #94a3b8;
        font-size: 12px;
    }

    .orders-proto-modal .selected-order-list {
        margin: 0.75rem 0 0;
        padding-left: 1.1rem;
        font-size: 13px;
        color: #334155;
        max-height: 160px;
        overflow: auto;
    }
</style>
