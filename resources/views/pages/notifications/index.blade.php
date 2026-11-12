@extends('layout_main.app')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="resellerNotificationsPage">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Notifications</h3>
                <p class="fs-15 text-body mb-0">
                    Messages addressed to your company.
                    <span class="text-secondary" id="notificationInboxUnreadLabel">
                        @if (($unreadCount ?? 0) > 0)
                            {{ $unreadCount }} unread
                        @endif
                    </span>
                </p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Notifications</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if (session('success'))
            <div class="alert alert-success border mb-3" role="alert">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4" id="notificationInboxList">
            @forelse ($recipients as $recipient)
                @php
                    $notification = $recipient->notification;
                    $isUnread = $recipient->read_at === null;
                    $bannerUuid = $notification?->banner_file_uuid;
                @endphp
                <div class="col-12" data-recipient-uuid="{{ $recipient->uuid }}">
                    <a href="{{ route('notifications.show', $notification) }}"
                        class="card bg-white border {{ $isUnread ? 'border-primary' : 'border-white' }} rounded-10 text-decoration-none d-block p-20 h-100">
                        <div class="d-flex gap-3 align-items-start">
                            @if ($bannerUuid && $notification?->bannerFile)
                                <img src="{{ route('files.view', $bannerUuid) }}"
                                    alt=""
                                    class="rounded-10 border flex-shrink-0"
                                    style="width: 96px; height: 64px; object-fit: cover;">
                            @else
                                <div class="rounded-10 border flex-shrink-0 d-flex align-items-center justify-content-center bg-body-tertiary"
                                    style="width: 96px; height: 64px;">
                                    <i class="material-symbols-outlined {{ $isUnread ? 'text-primary' : 'text-secondary' }}">
                                        {{ $isUnread ? 'mark_email_unread' : 'mark_email_read' }}
                                    </i>
                                </div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <h4 class="fs-16 mb-1 {{ $isUnread ? 'fw-semibold text-secondary' : 'fw-medium text-body' }}">
                                        {{ $notification?->title }}
                                    </h4>
                                    @if ($isUnread)
                                        <span class="text-primary bg-primary bg-opacity-10 fs-13 fw-normal d-inline-block default-badge">Unread</span>
                                    @else
                                        <span class="text-secondary bg-secondary bg-opacity-10 fs-13 fw-normal d-inline-block default-badge">Read</span>
                                    @endif
                                </div>
                                <p class="fs-14 text-body mb-2">{{ Str::limit((string) $notification?->body, 120) }}</p>
                                <div class="fs-13 text-secondary">
                                    From {{ $notification?->senderLabel() ?: 'Admin' }}
                                    ·
                                    {{ $notification?->type?->label() ?: 'Notice' }}
                                    ·
                                    {{ $notification?->publish_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12" data-notification-empty="1">
                    <div class="card bg-white rounded-10 border border-white p-40 text-center text-muted">
                        No notifications.
                    </div>
                </div>
            @endforelse
        </div>

        @include('partials.pagination', [
            'paginator' => $recipients,
            'ariaLabel' => 'Notifications pagination',
        ])
    </div>
@endsection
