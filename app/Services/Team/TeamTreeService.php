<?php

namespace App\Services\Team;

use Feeder\Core\Models\User;
use Feeder\Core\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeamTreeService
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    public function getRootTreeData(): array
    {
        $rootUser = $this->getAuthenticatedReseller();
        $maxDepth = $this->getMaxDepth();
        $childrenLimit = (int) config('team.tree.children_limit', 60);

        $rootNode = $this->referralService->getNodeData($rootUser);

        if ($rootNode === null) {
            return [
                'root' => null,
                'children' => [],
            ];
        }

        $rootNode['has_children'] = $maxDepth > 0 && ((int) $rootNode['direct_referrals'] > 0);

        return [
            'root' => $rootNode,
            'children' => $this->referralService->getScopedChildrenNodeData(
                $rootUser,
                $rootUser,
                $maxDepth,
                $childrenLimit,
            ),
        ];
    }

    public function getChildren(User $parentUser, int $limit = 60): array
    {
        $rootUser = $this->getAuthenticatedReseller();
        $maxDepth = $this->getMaxDepth();

        if ($this->referralService->getUserDepthFromRoot($rootUser, $parentUser, $maxDepth) === null) {
            throw new NotFoundHttpException('The selected user is outside your team scope.');
        }

        return $this->referralService->getScopedChildrenNodeData($rootUser, $parentUser, $maxDepth, $limit);
    }

    public function searchUsers(string $query, int $limit = 10): array
    {
        $rootUser = $this->getAuthenticatedReseller();

        return $this->referralService->searchTreeUsersWithinDepth(
            $rootUser,
            $query,
            $this->getMaxDepth(),
            $limit,
        );
    }

    public function getPathToUser(User $targetUser): array
    {
        $rootUser = $this->getAuthenticatedReseller();

        $path = $this->referralService->getDepthScopedPathNodeData(
            $targetUser,
            $rootUser,
            $this->getMaxDepth(),
        );

        if ($path === []) {
            throw new NotFoundHttpException('No team path exists for the selected user in your allowed scope.');
        }

        return $path;
    }

    private function getAuthenticatedReseller(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new NotFoundHttpException('Authenticated reseller was not found.');
        }

        return $user;
    }

    private function getMaxDepth(): int
    {
        return max(0, (int) config('team.tree.max_depth', 3));
    }
}
