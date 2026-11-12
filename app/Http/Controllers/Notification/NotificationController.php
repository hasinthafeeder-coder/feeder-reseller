<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\ResellerMeeting;
use Feeder\Core\Models\ResellerMeetingCheckInToken;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Meeting\MeetingCheckInTokenService;
use Feeder\Core\Services\Meeting\MeetingEligibilityService;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly NotificationPublicationService $publisher,
        private readonly MeetingEligibilityService $meetingEligibility,
        private readonly MeetingCheckInTokenService $meetingTokens,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actor();
        $perPage = max(1, min(50, (int) $request->integer('per_page', 20)));

        return view('pages.notifications.index', [
            'recipients' => $this->inbox->paginateVisibleFor($actor, $perPage),
            'unreadCount' => $this->inbox->unreadCountFor($actor),
        ]);
    }

    public function show(Notification $notification): View
    {
        $actor = $this->actor();

        try {
            $recipient = $this->inbox->markOpenedByNotificationUuid($actor, (string) $notification->uuid);
        } catch (ValidationException) {
            abort(403);
        }

        $loaded = $this->publisher->loadForDisplay($recipient->notification ?? $notification);
        [$meeting, $token] = $this->resolveMeetingContext($actor, $loaded);

        return view('pages.notifications.show', [
            'notification' => $loaded,
            'recipient' => $recipient,
            'meeting' => $meeting,
            'token' => $token,
        ]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $actor = $this->actor();

        try {
            $this->inbox->markReadByNotificationUuid($actor, (string) $notification->uuid);
        } catch (ValidationException) {
            abort(403);
        }

        return redirect()
            ->route('notifications.show', $notification)
            ->with('success', 'Notification marked as read.');
    }

    /**
     * @return array{0: ?ResellerMeeting, 1: ?ResellerMeetingCheckInToken}
     */
    private function resolveMeetingContext(User $actor, Notification $notification): array
    {
        if ($notification->type !== NotificationType::MEETING_REMINDER) {
            return [null, null];
        }

        $meeting = $notification->meeting;
        if (! $meeting instanceof ResellerMeeting || $meeting->isCancelled()) {
            return [null, null];
        }

        $company = $actor->company;
        if ($company === null || ! $this->meetingEligibility->isCompanyEligibleForMeeting($company, $meeting)) {
            return [$meeting, null];
        }

        $token = $this->meetingTokens->ensureActiveToken($meeting, $company, $actor);

        if ((int) $token->company_id !== (int) $company->id) {
            return [$meeting, null];
        }

        return [$meeting, $token];
    }

    private function actor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
