<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallCenter\IndexAgentRequest;
use App\Http\Requests\CallCenter\StoreAgentRequest;
use App\Http\Requests\CallCenter\UpdateAgentCommissionRequest;
use App\Http\Requests\CallCenter\UpdateAgentPermissionsRequest;
use App\Http\Requests\CallCenter\UpdateAgentRequest;
use App\Services\CallCenter\AgentService;
use App\Support\CallCenter\AgentViewData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Call Center Agent screens.
 *
 * List / Profile are production read endpoints (O1.4-D1).
 * Create is a production write endpoint (O1.4-D2).
 * Edit is a production write endpoint (O1.4-D3).
 * Activate / Deactivate are production write endpoints (O1.4-D4).
 * Commission update is a production write endpoint (O1.4-D5-A).
 * Permission update is a production write endpoint (O1.4-D5-B).
 */
class AgentUiController extends Controller
{
    public function __construct(
        private readonly AgentService $agentService,
        private readonly AgentViewData $agentViewData,
    ) {}

    public function index(IndexAgentRequest $request): View
    {
        $actor = Auth::user();
        $search = $request->search();
        $status = $request->statusFilter();

        $paginator = $this->agentService->paginateForCompany($actor, $search, $status);

        $agents = $paginator->getCollection()
            ->map(fn ($agent) => $this->agentViewData->forListItem($agent))
            ->all();

        return view('pages.call-center.agents.index', [
            'agents' => $agents,
            'agentsPaginator' => $paginator,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        $actor = Auth::user();

        return view('pages.call-center.agents.create', [
            'agent' => null,
            'permissionCatalog' => $this->agentService->operationalPermissionCatalog(),
            'assignedPermissions' => old('permissions', []),
            'canManagePermissions' => $actor?->hasPermission(AgentService::PERMISSION_PERMISSIONS_UPDATE) ?? false,
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = $this->agentService->create(Auth::user(), $request->agentPayload());

        return redirect()
            ->route('ui.call-center.agents.show', $agent->uuid)
            ->with('success', 'Call Center Agent created successfully.');
    }

    public function edit(string $agent): View
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $permissionState = $this->agentService->profilePermissionDisplay($actor, $managed);
        $canManagePermissions = $actor->hasPermission(AgentService::PERMISSION_PERMISSIONS_UPDATE);

        return view(
            'pages.call-center.agents.edit',
            array_merge($this->agentViewData->forEdit($managed, $permissionState), [
                'canManagePermissions' => $canManagePermissions,
                'permissionFormId' => $canManagePermissions ? 'agent-permissions-form' : null,
            ]),
        );
    }

    public function update(UpdateAgentRequest $request, string $agent): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $updated = $this->agentService->update($actor, $managed, $request->agentPayload());

        return redirect()
            ->route('ui.call-center.agents.show', $updated->uuid)
            ->with('success', 'Call Center Agent updated successfully.');
    }

    public function show(string $agent): View
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $permissionState = $this->agentService->profilePermissionDisplay($actor, $managed);

        return view(
            'pages.call-center.agents.show',
            $this->agentViewData->forProfile($managed, $permissionState),
        );
    }

    public function activate(string $agent): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $updated = $this->agentService->activate($actor, $managed);

        return redirect()
            ->route('ui.call-center.agents.show', $updated->uuid)
            ->with('success', 'Call Center Agent activated successfully.');
    }

    public function deactivate(string $agent): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $updated = $this->agentService->deactivate($actor, $managed);

        return redirect()
            ->route('ui.call-center.agents.show', $updated->uuid)
            ->with('success', 'Call Center Agent deactivated successfully.');
    }

    public function updateCommission(UpdateAgentCommissionRequest $request, string $agent): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $updated = $this->agentService->updateCommission(
            $actor,
            $managed,
            $request->commissionAmount(),
        );

        return redirect()
            ->route('ui.call-center.agents.show', $updated->uuid)
            ->with('success', 'Call Center Agent commission updated successfully.');
    }

    public function updatePermissions(UpdateAgentPermissionsRequest $request, string $agent): RedirectResponse
    {
        $actor = Auth::user();

        try {
            $managed = $this->agentService->findForManagement($actor, $agent);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $updated = $this->agentService->syncPermissionSelections(
            $actor,
            $managed,
            $request->grantedIdentifiers(),
            $request->deniedIdentifiers(),
        );

        return redirect()
            ->route('ui.call-center.agents.show', $updated->uuid)
            ->with('success', 'Call Center Agent permissions updated successfully.');
    }
}
