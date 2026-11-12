@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Meetings</h3>
                <p class="fs-15 text-body mb-0">
                    Monthly reseller meetings and details for your account.
                    @if ($eligibleFrom)
                        Company joining date on file: {{ $eligibleFrom->toDateString() }}.
                    @endif
                </p>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($meetings as $meeting)
                @php
                    $token = $tokensByMeeting[$meeting->id] ?? null;
                    $attendance = $attendanceByMeeting[$meeting->id] ?? null;
                    $status = $meeting->status instanceof \Feeder\Core\Enums\ResellerMeetingStatus
                        ? $meeting->status
                        : \Feeder\Core\Enums\ResellerMeetingStatus::tryFrom((string) $meeting->status);
                    $statusLabel = $status?->label() ?? (string) $meeting->status;
                @endphp
                <div class="col-12 col-lg-6">
                    <div class="card bg-white rounded-10 border border-white h-100 overflow-hidden">
                        @if ($meeting->banner_file_uuid && $meeting->bannerFile)
                            <div style="max-height: 160px; overflow: hidden;">
                                <img src="{{ route('files.view', $meeting->banner_file_uuid) }}"
                                    alt="{{ $meeting->title }}"
                                    class="w-100"
                                    style="max-height: 160px; object-fit: cover;">
                            </div>
                        @endif
                        <div class="p-20">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h4 class="fs-16 mb-0">{{ $meeting->title }}</h4>
                                <span class="badge {{ $meeting->isCancelled() ? 'bg-secondary' : 'bg-success' }} text-white">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="fs-14 text-body mb-1">
                                {{ $meeting->scheduled_at?->timezone(\Feeder\Core\Support\BusinessTimezone::ID)->format('Y-m-d H:i') }}
                            </p>
                            <p class="fs-14 text-body mb-2">{{ $meeting->location ?: 'Location TBA' }}</p>
                            @if ($meeting->description)
                                <p class="fs-14 text-body mb-3">{{ \Illuminate\Support\Str::limit($meeting->description, 140) }}</p>
                            @endif
                            <p class="fs-13 text-body mb-3">
                                @if ($attendance)
                                    Checked in
                                @elseif ($token && $meeting->isScheduled())
                                    QR ready for check-in
                                @else
                                    Details available
                                @endif
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('meetings.show', $meeting) }}" class="btn btn-sm btn-primary text-white">
                                    View details
                                </a>
                                @if ($token && $meeting->isScheduled())
                                    <a href="{{ route('meetings.show', $meeting) }}#meeting-qr" class="btn btn-sm btn-outline-primary">
                                        View QR
                                    </a>
                                    <a href="{{ route('meetings.qr.download', $meeting) }}" class="btn btn-sm btn-outline-primary">
                                        Download QR
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card bg-white rounded-10 border border-white p-20">
                        <p class="mb-0 text-body">No meetings have been published yet.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
