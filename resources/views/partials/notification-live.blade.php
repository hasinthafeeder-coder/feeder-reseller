@php
    $canPoll = auth()->check()
        && auth()->user()?->hasPermission(\Feeder\Core\Services\Notification\NotificationInboxService::PERMISSION_VIEW)
        && \Illuminate\Support\Facades\Route::has('notifications.live');
    $includeBanners = request()->routeIs('dashboard');
@endphp

@if ($canPoll)
    <div id="notificationLiveConfig"
        data-endpoint="{{ route('notifications.live') }}"
        data-interval-ms="15000"
        data-include-banners="{{ $includeBanners ? '1' : '0' }}"
        data-refresh-on-load="1"
        hidden></div>
    <script src="{{ asset('assets/js/notification-live.js') }}" defer></script>
@endif
