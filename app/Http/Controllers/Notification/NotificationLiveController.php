<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Meeting\MeetingBannerService;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * JSON poll endpoint for open reseller tabs (bell, sidebar, inbox, banners).
 */
class NotificationLiveController extends Controller
{
    public const POLL_INTERVAL_MS = 15000;

    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly MeetingBannerService $meetingBanners,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $actor->hasPermission(NotificationInboxService::PERMISSION_VIEW)) {
            abort(403);
        }

        try {
            $snapshot = $this->inbox->liveSnapshotFor($actor, 5);
        } catch (ValidationException) {
            return response()->json([
                'unread_count' => 0,
                'unread_meeting_count' => 0,
                'latest_recipient_id' => 0,
                'version' => '0:0:0:',
                'items' => [],
                'banners' => [],
                'poll_interval_ms' => self::POLL_INTERVAL_MS,
            ]);
        }

        $items = $snapshot['recipients']->map(function ($recipient) {
            $notification = $recipient->notification;
            $bannerUuid = $notification?->banner_file_uuid;

            return [
                'recipient_uuid' => (string) $recipient->uuid,
                'notification_uuid' => (string) ($notification?->uuid ?? ''),
                'title' => (string) ($notification?->title ?? ''),
                'body_preview' => Str::limit((string) ($notification?->body ?? ''), 120),
                'sender_label' => $notification?->senderLabel() ?: 'Admin',
                'type_label' => $notification?->type?->label() ?: 'Notice',
                'publish_at' => optional($notification?->publish_at)->toIso8601String(),
                'publish_at_label' => optional($notification?->publish_at)
                    ?->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i'),
                'publish_at_human' => optional($notification?->publish_at)->diffForHumans(),
                'is_unread' => $recipient->read_at === null,
                'banner_url' => ($bannerUuid && $notification?->bannerFile)
                    ? route('files.view', $bannerUuid)
                    : null,
                'show_url' => $notification
                    ? route('notifications.show', $notification)
                    : null,
            ];
        })->values()->all();

        $banners = [];
        if ($request->boolean('include_banners')) {
            $banners = $this->bannerSlidesFor($actor);
        }

        $bannerFingerprint = collect($banners)
            ->pluck('banner_file_uuid')
            ->filter()
            ->implode(',');

        $version = implode(':', [
            (string) $snapshot['latest_recipient_id'],
            (string) $snapshot['unread_count'],
            (string) $snapshot['unread_meeting_count'],
            $bannerFingerprint,
        ]);

        return response()->json([
            'unread_count' => $snapshot['unread_count'],
            'unread_meeting_count' => $snapshot['unread_meeting_count'],
            'latest_recipient_id' => $snapshot['latest_recipient_id'],
            'version' => $version,
            'items' => $items,
            'banners' => $banners,
            'poll_interval_ms' => self::POLL_INTERVAL_MS,
        ]);
    }

    /**
     * @return list<array{kind: string, title: string, banner_file_uuid: string, url: string}>
     */
    private function bannerSlidesFor(User $actor): array
    {
        $slides = collect();

        try {
            foreach ($this->inbox->activeBannerRecipientsFor($actor) as $recipient) {
                $notification = $recipient->notification;
                if (! $notification?->banner_file_uuid) {
                    continue;
                }

                $slides->push([
                    'kind' => 'notification',
                    'title' => (string) $notification->title,
                    'banner_file_uuid' => (string) $notification->banner_file_uuid,
                    'url' => route('notifications.show', $notification),
                    'image_url' => route('files.view', $notification->banner_file_uuid),
                    'sort' => optional($notification->publish_at)?->getTimestamp() ?? 0,
                ]);
            }
        } catch (ValidationException) {
            // Keep poll usable when inbox visibility cannot be resolved.
        }

        if ($actor->hasPermission('meetings.view')) {
            foreach ($this->meetingBanners->activeBannersForReseller($actor) as $meeting) {
                if (! $meeting->banner_file_uuid) {
                    continue;
                }

                $slides->push([
                    'kind' => 'meeting',
                    'title' => (string) $meeting->title,
                    'banner_file_uuid' => (string) $meeting->banner_file_uuid,
                    'url' => route('meetings.show', $meeting),
                    'image_url' => route('files.view', $meeting->banner_file_uuid),
                    'sort' => optional($meeting->scheduled_at)?->getTimestamp() ?? 0,
                ]);
            }
        }

        return $slides
            ->sortByDesc('sort')
            ->values()
            ->map(static function (array $slide): array {
                unset($slide['sort']);

                return $slide;
            })
            ->all();
    }
}
