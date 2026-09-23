@php
    $ccaName = $ccaName ?? '—';
    $canClaimFromPool = $canClaimFromPool ?? false;
    $canAssignCca = $canAssignCca ?? false;
    $eligibleCcas = $eligibleCcas ?? [];
    $displayActor = $displayActor ?? static fn ($user) => '—';
    $fieldIdPrefix = $fieldIdPrefix ?? 'cca';
@endphp

<div class="card bg-white rounded-10 border border-white mb-4" id="orderCcaAssignmentCard">
    <div class="p-20 border-bottom">
        <h4 class="fs-18 mb-0">CCA Assignment</h4>
    </div>
    <div class="p-20">
        <div class="mb-3">
            <div class="fs-13 text-body mb-1">Current CCA</div>
            <div class="fw-medium">{{ $ccaName }}</div>
        </div>

        @if ($canClaimFromPool)
            <form method="POST" action="{{ route('orders.cca.claim', $order) }}" class="mb-4">
                @csrf
                <button type="submit" class="btn btn-primary text-white w-100">
                    Assign to Me
                </button>
            </form>
        @endif

        @if ($canAssignCca)
            <form method="POST" action="{{ route('orders.cca.assign', $order) }}" class="mb-3">
                @csrf
                <div class="mb-3">
                    <label for="{{ $fieldIdPrefix }}Id" class="label fs-14 mb-2">Assign / reassign CCA</label>
                    <select class="form-select form-control" id="{{ $fieldIdPrefix }}Id" name="cca_id" required>
                        <option value="">Select CCA</option>
                        @foreach ($eligibleCcas as $cca)
                            <option value="{{ $cca['id'] }}"
                                @selected((string) old('cca_id', $order->cca_id) === (string) $cca['id'])>
                                {{ $cca['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="{{ $fieldIdPrefix }}Note" class="label fs-14 mb-2">Note (optional)</label>
                    <input type="text" class="form-control" id="{{ $fieldIdPrefix }}Note" name="note"
                        value="{{ old('note') }}" maxlength="1000">
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">Assign CCA</button>
            </form>

            <button type="button" class="btn btn-light border w-100 mt-2 mb-2" data-open-order-ui-modal="callCenterAssignModal">
                Send to Order Pool
            </button>
        @endif

        <div class="fs-14 fw-medium mb-2">Assignment history</div>
        @forelse ($order->ccaAssignments as $assignment)
            <div class="@if (! $loop->last) border-bottom pb-2 mb-2 @endif fs-13">
                <div class="fw-medium">{{ $displayActor($assignment->cca) }}</div>
                <div class="text-body">
                    Assigned {{ optional($assignment->assigned_at)->format('Y-m-d H:i') ?? '—' }}
                    @if ($assignment->assignedByUser)
                        by {{ $displayActor($assignment->assignedByUser) }}
                    @endif
                </div>
                @if ($assignment->unassigned_at)
                    <div class="text-body">
                        Replaced {{ $assignment->unassigned_at->format('Y-m-d H:i') }}
                    </div>
                @endif
            </div>
        @empty
            <div class="text-body fs-13">No CCA assignments yet.</div>
        @endforelse
    </div>
</div>
