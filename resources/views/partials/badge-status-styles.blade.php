{{-- Same badge-status tokens as Reseller Orders call-center workspace. --}}
<style>
    .badge-status {
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
        background: #e9ecef;
        color: #41464b;
    }

    .badge-status.is-pending { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
    .badge-status.is-confirmed { background: #cff4fc; color: #055160; }
    .badge-status.is-processing { background: #fff3cd; color: #664d03; }
    .badge-status.is-1st-attempt { background: #cfe2ff; color: #084298; }
    .badge-status.is-2nd-attempt { background: #fff3cd; color: #664d03; }
    .badge-status.is-3rd-attempt { background: rgba(239, 73, 35, 0.12); color: #c0391a; }
    .badge-status.is-hold { background: #fff3cd; color: #664d03; }
    .badge-status.is-pending-approval { background: #fff3cd; color: #664d03; }
    .badge-status.is-expiring { background: rgba(239, 73, 35, 0.12); color: #ef4923; }
    .badge-status.is-expired { background: #e9ecef; color: #41464b; }
    .badge-status.is-print,
    .badge-status.is-printed { background: #cfe2ff; color: #084298; }
    .badge-status.is-packing { background: #fff3cd; color: #664d03; }
    .badge-status.is-dispatched { background: #cff4fc; color: #055160; }
    .badge-status.is-delivered { background: #d1e7dd; color: #0f5132; }
    .badge-status.is-returned { background: #fff3cd; color: #664d03; }
    .badge-status.is-cancelled { background: #f8d7da; color: #842029; }
    .badge-status.is-settlement-pending { background: #fff3cd; color: #664d03; }
    .badge-status.is-settled { background: #d1e7dd; color: #0f5132; }
    .badge-status.is-settlement-adjustment { background: #e9ecef; color: #41464b; }
    .badge-status.is-unpaid,
    .badge-status.is-partially-paid,
    .badge-status.is-partially-offset,
    .badge-status.is-partial-and-offset { background: #fff3cd; color: #664d03; }
    .badge-status.is-paid,
    .badge-status.is-offset-settled,
    .badge-status.is-offset-and-paid { background: #d1e7dd; color: #0f5132; }
    .badge-status.is-open { background: #cfe2ff; color: #084298; }
    .badge-status.is-locked { background: #cff4fc; color: #055160; }
    .badge-status.is-closed { background: #e9ecef; color: #41464b; }
    .badge-status.is-active { background: #d1e7dd; color: #0f5132; }
    .badge-status.is-voided { background: #f8d7da; color: #842029; }
</style>
