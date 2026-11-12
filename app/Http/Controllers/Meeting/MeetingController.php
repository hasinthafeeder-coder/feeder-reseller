<?php

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use Feeder\Core\Models\ResellerMeeting;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Meeting\ResellerMeetingPortalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function __construct(
        private readonly ResellerMeetingPortalService $meetings,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actor($request);

        try {
            $payload = $this->meetings->indexPayloadFor($actor);
        } catch (ValidationException) {
            abort(403);
        }

        return view('pages.meetings.index', $payload);
    }

    public function show(Request $request, ResellerMeeting $meeting): View
    {
        $actor = $this->actor($request);

        try {
            $payload = $this->meetings->showPayloadFor($actor, $meeting);
        } catch (ValidationException) {
            abort(403);
        }

        return view('pages.meetings.show', $payload);
    }

    public function downloadQr(Request $request, ResellerMeeting $meeting): View
    {
        $actor = $this->actor($request);

        try {
            $meeting = $this->meetings->findVisibleMeetingOrFail($actor, $meeting);
        } catch (ValidationException) {
            abort(403);
        }

        $token = $this->meetings->ownActiveToken($actor, $meeting);

        if ($token === null) {
            abort(404);
        }

        $actor->loadMissing('company');

        return view('pages.meetings.qr-download', [
            'meeting' => $meeting,
            'token' => $token,
            'companyName' => (string) ($actor->company?->name ?? 'your reseller account'),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->company) {
            abort(403);
        }

        return $actor;
    }
}
