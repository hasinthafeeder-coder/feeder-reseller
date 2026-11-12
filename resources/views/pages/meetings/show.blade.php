@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden">
        @php
            $status = $meeting->status instanceof \Feeder\Core\Enums\ResellerMeetingStatus
                ? $meeting->status
                : \Feeder\Core\Enums\ResellerMeetingStatus::tryFrom((string) $meeting->status);
            $statusLabel = $status?->label() ?? (string) $meeting->status;
        @endphp

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <h3 class="mb-0">{{ $meeting->title }}</h3>
                    <span class="badge {{ $meeting->isCancelled() ? 'bg-secondary' : 'bg-success' }} text-white">
                        {{ $statusLabel }}
                    </span>
                </div>
                <p class="fs-15 text-body mb-0">
                    {{ $meeting->scheduled_at?->timezone(\Feeder\Core\Support\BusinessTimezone::ID)->format('Y-m-d H:i') }}
                    @if ($meeting->location)
                        · {{ $meeting->location }}
                    @endif
                </p>
            </div>
            <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>

        @if ($meeting->banner_file_uuid && $meeting->bannerFile)
            <div class="mb-4 rounded-10 overflow-hidden border" style="max-height: 220px;">
                <img src="{{ route('files.view', $meeting->banner_file_uuid) }}"
                    alt="{{ $meeting->title }}"
                    class="w-100"
                    style="max-height: 220px; object-fit: cover;">
            </div>
        @endif

        @if ($meeting->description)
            <div class="card bg-white rounded-10 border border-white p-20 mb-4">
                <h4 class="fs-16 mb-2">Details</h4>
                <p class="fs-14 text-body mb-0" style="white-space: pre-wrap;">{{ $meeting->description }}</p>
            </div>
        @endif

        @if ($attendance)
            <div class="alert alert-success border mb-3" role="alert">
                Checked in at {{ $attendance->checked_in_at?->timezone(\Feeder\Core\Support\BusinessTimezone::ID)->format('Y-m-d H:i') }}.
            </div>
        @endif

        @if ($token && $meeting->isScheduled())
            <div id="meeting-qr" class="card bg-white rounded-10 border border-white p-20 mb-4 text-center">
                <h4 class="fs-16 mb-2">Your check-in QR</h4>
                <p class="fs-14 text-body mb-3">Present this code at the meeting. The QR contains no personal data and is unique to your reseller account.</p>
                <div id="meetingQrCode" class="d-inline-block mx-auto mb-3" data-token="{{ $token->token }}"></div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-0">
                    <a href="#meeting-qr" class="btn btn-outline-primary">View QR</a>
                    <a href="{{ route('meetings.qr.download', $meeting) }}" class="btn btn-primary text-white">
                        Download QR
                    </a>
                </div>
            </div>
        @elseif ($meeting->isScheduled())
            <div class="card bg-white rounded-10 border border-white p-20 mb-4">
                <h4 class="fs-16 mb-2">Check-in QR</h4>
                <p class="fs-14 text-body mb-0">
                    Your check-in QR is not available yet. Contact support if this persists.
                </p>
            </div>
        @endif
    </div>
@endsection

@if ($token && $meeting->isScheduled())
    @push('scripts')
        <script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
        <script>
            (function () {
                var el = document.getElementById('meetingQrCode');
                if (!el || typeof QRCode === 'undefined') {
                    return;
                }
                new QRCode(el, {
                    text: el.getAttribute('data-token'),
                    width: 220,
                    height: 220
                });
            })();
        </script>
    @endpush
@endif
