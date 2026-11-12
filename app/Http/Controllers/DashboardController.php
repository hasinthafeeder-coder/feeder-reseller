<?php

namespace App\Http\Controllers;

use Feeder\Core\Models\User;
use Feeder\Core\Services\Meeting\MeetingBannerService;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly MeetingBannerService $meetingBanners,
    ) {}

    public function __invoke(): View
    {
        $slides = collect();
        $actor = Auth::user();

        if (
            $actor instanceof User
            && (int) $actor->company_id > 0
            && $actor->hasPermission(NotificationInboxService::PERMISSION_VIEW)
        ) {
            try {
                foreach ($this->inbox->activeBannerRecipientsFor($actor) as $recipient) {
                    $notification = $recipient->notification;
                    if ($notification?->banner_file_uuid) {
                        $slides->push([
                            'kind' => 'notification',
                            'title' => (string) $notification->title,
                            'banner_file_uuid' => (string) $notification->banner_file_uuid,
                            'url' => route('notifications.show', $notification),
                            'sort' => optional($notification->publish_at)?->getTimestamp() ?? 0,
                        ]);
                    }
                }
            } catch (ValidationException) {
                // Keep dashboard usable when inbox visibility cannot be resolved.
            }
        }

        if (
            $actor instanceof User
            && (int) $actor->company_id > 0
            && $actor->hasPermission('meetings.view')
        ) {
            foreach ($this->meetingBanners->activeBannersForReseller($actor) as $meeting) {
                if (! $meeting->banner_file_uuid) {
                    continue;
                }

                $slides->push([
                    'kind' => 'meeting',
                    'title' => (string) $meeting->title,
                    'banner_file_uuid' => (string) $meeting->banner_file_uuid,
                    'url' => route('meetings.show', $meeting),
                    'sort' => optional($meeting->scheduled_at)?->getTimestamp() ?? 0,
                ]);
            }
        }

        $slides = $slides
            ->sortByDesc('sort')
            ->values();

        return view('pages.main.dashboard', [
            'dashboardBanners' => $slides,
            'notificationBanners' => $slides,
        ]);
    }
}
