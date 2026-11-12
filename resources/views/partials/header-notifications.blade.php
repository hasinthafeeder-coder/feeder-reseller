@php
    $unread = (int) ($headerNotificationUnreadCount ?? 0);
    $recipients = $headerNotificationRecipients ?? collect();
    $indexRoute = $headerNotificationIndexRoute ?? null;
    $showRoute = $headerNotificationShowRoute ?? null;
@endphp

@if ($indexRoute && $showRoute)
    <li class="header-right-item">
        <div class="dropdown notifications noti" id="headerNotificationsDropdown">
            <button class="btn btn-secondary border-0 p-0 position-relative"
                type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Notifications">
                <span class="material-symbols-outlined">notifications</span>
                <span class="count" id="headerNotificationUnreadBadge"
                    @if ($unread <= 0) hidden style="display:none;" @endif>
                    {{ $unread > 99 ? '99+' : $unread }}
                </span>
            </button>
            <div class="dropdown-menu dropdown-lg p-0 border-0 p-0 dropdown-menu-end">
                <div class="d-flex justify-content-between align-items-center title">
                    <span class="fw-medium fs-16 text-secondary">
                        Notifications
                        <span class="fw-normal text-body fs-16" id="headerNotificationUnreadLabel">({{ $unread }})</span>
                    </span>
                    <a href="{{ route($indexRoute) }}" class="fs-15 text-primary fw-medium text-decoration-none">
                        Open inbox
                    </a>
                </div>

                <div id="headerNotificationRecentList" style="max-height: 300px" data-simplebar>
                    @forelse ($recipients as $recipient)
                        @php
                            $notification = $recipient->notification;
                            $isUnread = $recipient->read_at === null;
                        @endphp
                        <div class="notification-menu {{ $isUnread ? 'unseen' : '' }}"
                            data-recipient-uuid="{{ $recipient->uuid }}">
                            <a href="{{ route($showRoute, $notification) }}" class="dropdown-item">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        @if ($notification?->banner_file_uuid && $notification?->bannerFile)
                                            <img src="{{ route('files.view', $notification->banner_file_uuid) }}"
                                                alt=""
                                                class="rounded-2"
                                                style="width: 40px; height: 40px; object-fit: cover;">
                                        @else
                                            <i class="material-symbols-outlined {{ $isUnread ? 'text-primary' : 'text-secondary' }}">
                                                {{ $isUnread ? 'mark_email_unread' : 'mark_email_read' }}
                                            </i>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="fs-16 fw-medium text-secondary mb-0">
                                            {{ \Illuminate\Support\Str::limit((string) $notification?->title, 60) }}
                                        </p>
                                        <span class="fs-13 text-body d-block">
                                            {{ \Illuminate\Support\Str::limit((string) $notification?->body, 70) }}
                                        </span>
                                        <span class="fs-13 text-body d-block">
                                            From {{ $notification?->senderLabel() ?: 'Admin' }}
                                        </span>
                                        <span class="fs-14 fw-medium">
                                            {{ $notification?->publish_at?->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="p-3 text-body fs-14">No notifications yet.</div>
                    @endforelse
                </div>

                <a href="{{ route($indexRoute) }}"
                    class="dropdown-item text-center text-primary d-block view-all fw-medium rounded-bottom-3">
                    <span>See All Notifications</span>
                </a>
            </div>
        </div>
    </li>
@endif
