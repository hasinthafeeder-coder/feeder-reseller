<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="{{ $modalId }}Label">{{ $title }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-status-toggle-message>{{ $message }}</p>
                <p class="text-muted mb-0 fs-14">This action is shown for UI review only and does not change agent status yet.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn {{ $confirmClass ?? 'btn-primary text-white' }}" data-bs-dismiss="modal"
                    data-status-toggle-confirm>
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
