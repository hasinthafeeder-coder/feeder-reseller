<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Support\CallCenter\AgentUiPreviewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Temporary UI-only controller for O1.1 Call Center Agents screens.
 *
 * Routes here return Blade views with static preview data only.
 * They must not persist, authorize against the permission engine,
 * or call production services. Replace during O1.2/O1.3.
 */
class AgentUiController extends Controller
{
    public function __construct(
        private readonly AgentUiPreviewData $previewData,
    ) {}

    public function index(Request $request): View
    {
        return view(
            'pages.call-center.agents.index',
            $this->previewData->listViewData($request),
        );
    }

    public function create(): View
    {
        return view(
            'pages.call-center.agents.create',
            $this->previewData->formViewData(),
        );
    }

    public function edit(string $agent): View
    {
        $viewData = $this->previewData->formViewData($agent);

        abort_if($viewData['agent'] === null, 404);

        return view('pages.call-center.agents.edit', $viewData);
    }

    public function show(string $agent): View
    {
        $viewData = $this->previewData->profileViewData($agent);

        abort_if($viewData === null, 404);

        return view('pages.call-center.agents.show', $viewData);
    }
}
