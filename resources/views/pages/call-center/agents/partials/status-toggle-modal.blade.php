@php
    $actionUrl = $actionUrl ?? '';
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ $actionUrl }}" data-status-toggle-form>
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="{{ $modalId }}Label">{{ $title }}</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-status-toggle-message>{{ $message }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn {{ $confirmClass ?? 'btn-primary text-white' }}"
                        data-status-toggle-confirm>
                        {{ $confirmLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
