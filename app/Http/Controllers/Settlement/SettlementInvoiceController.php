<?php

namespace App\Http\Controllers\Settlement;

use App\Http\Controllers\Controller;
use App\Services\Settlement\ResellerSettlementInvoiceReadService;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reseller portal: view own settlement invoices, deductions, and payment history.
 *
 * Read-only. Does not record payments or regenerate invoices.
 * Print uses persisted invoice totals only (same contract as Admin PDF/print).
 */
class SettlementInvoiceController extends Controller
{
    public function __construct(
        private readonly ResellerSettlementInvoiceReadService $readService,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actor($request);

        return view('pages.settlements.invoices.index', [
            'invoices' => $this->readService->paginateForReseller($actor),
        ]);
    }

    public function show(Request $request, ResellerSettlementInvoice $invoice): View
    {
        $detail = $this->readService->loadOwnedInvoiceDetail($this->actor($request), $invoice);

        return view('pages.settlements.invoices.show', $detail);
    }

    public function print(Request $request, ResellerSettlementInvoice $invoice): View
    {
        $detail = $this->readService->loadOwnedInvoiceDetail($this->actor($request), $invoice);

        return view('pages.settlements.invoices.print', [
            'invoice' => $detail['invoice'],
            'batch' => $detail['invoice']->batch,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }
}
