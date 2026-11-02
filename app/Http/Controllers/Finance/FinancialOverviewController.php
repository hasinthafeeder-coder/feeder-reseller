<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\IndexFinancialOverviewRequest;
use App\Services\Settlement\ResellerSettlementInvoiceReadService;
use Feeder\Core\Models\User;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reseller portal financial overview.
 *
 * Invoice rows and summary totals use the authenticated reseller's eligible
 * invoices. Another reseller's id is never accepted.
 */
class FinancialOverviewController extends Controller
{
    public function __construct(
        private readonly ResellerSettlementInvoiceReadService $invoices,
        private readonly PortalFinancialOverviewMarkets $overviewMarkets,
    ) {}

    public function index(IndexFinancialOverviewRequest $request): View
    {
        $actor = $this->actor($request);
        $filters = $request->filters();
        $markets = $this->overviewMarkets->viewData($actor);

        return view('pages.finance.overview', [
            'filters' => $filters,
            'invoices' => $this->invoices->paginateForFinancialOverview(
                $actor,
                15,
                $filters['date_from'] !== '' ? $filters['date_from'] : null,
                $filters['date_to'] !== '' ? $filters['date_to'] : null,
            ),
            'marketSummaries' => $this->invoices->financialSummaries(
                $actor,
                $markets['overviewMarkets'] instanceof Collection ? $markets['overviewMarkets'] : new Collection(),
            ),
            ...$markets,
        ]);
    }

    private function actor(IndexFinancialOverviewRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }
}
