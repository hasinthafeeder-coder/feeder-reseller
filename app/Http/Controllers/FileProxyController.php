<?php

namespace App\Http\Controllers;

use App\Services\FileServer\FileProxyService;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Meeting\MeetingBannerService;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Feeder\Core\Services\Order\OrderPaymentReviewService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FileProxyController extends Controller
{
    public function __construct(
        private readonly FileProxyService $fileProxyService,
        private readonly OrderPaymentReviewService $paymentReviewService,
        private readonly NotificationInboxService $notificationInbox,
        private readonly MeetingBannerService $meetingBanners,
    ) {}

    public function thumbnail(string $uuid, string $size = 'md'): Response
    {
        $this->authorizeFileAccess($uuid);

        return $this->fileProxyService->thumbnail($uuid, $size);
    }

    public function view(string $uuid): Response
    {
        $this->authorizeFileAccess($uuid);

        return $this->fileProxyService->view($uuid);
    }

    public function download(string $uuid): Response
    {
        $this->authorizeFileAccess($uuid);

        return $this->fileProxyService->download($uuid);
    }

    private function authorizeFileAccess(string $uuid): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->paymentReviewService->authorizeFileAccessIfPaymentProof($actor, $uuid);
        $this->notificationInbox->authorizeBannerFileAccess($actor, $uuid);
        $this->meetingBanners->authorizeBannerFileAccess($actor, $uuid);
    }
}
