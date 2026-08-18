<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TeamTreeChildrenRequest;
use App\Http\Requests\Team\TeamTreeSearchRequest;
use App\Services\Team\TeamTreeService;
use Feeder\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TeamTreeController extends Controller
{
    public function __construct(
        private readonly TeamTreeService $teamTreeService,
    ) {}

    public function index(): View
    {
        return view('pages.team.tree');
    }

    public function root(): JsonResponse
    {
        return response()->json(
            $this->teamTreeService->getRootTreeData(),
        );
    }

    public function children(TeamTreeChildrenRequest $request, User $user): JsonResponse
    {
        $limit = (int) $request->validated('limit', config('team.tree.children_limit', 60));

        return response()->json([
            'parent_user_id' => (int) $user->id,
            'children' => $this->teamTreeService->getChildren($user, $limit),
        ]);
    }

    public function search(TeamTreeSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'results' => $this->teamTreeService->searchUsers(
                $validated['q'],
                (int) ($validated['limit'] ?? config('team.tree.search_limit', 10)),
            ),
        ]);
    }

    public function path(User $user): JsonResponse
    {
        $path = $this->teamTreeService->getPathToUser($user);

        return response()->json([
            'path' => $path,
            'selected_user_id' => (int) $user->id,
            'selected_user_uuid' => $user->uuid,
        ]);
    }
}
