<?php

namespace App\Http\Controllers;

use App\Services\FileServer\FileProxyService;
use Feeder\Core\Services\Order\OrderPaymentReviewService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FileProxyController extends Controller
{
    public function __construct(
        private readonly FileProxyService $fileProxyService,
        private readonly OrderPaymentReviewService $paymentReviewService,
    ) {}

    public function thumbnail(string $uuid, string $size = 'md'): Response
    {
        $this->authorizePaymentProof($uuid);

        return $this->fileProxyService->thumbnail($uuid, $size);
    }

    public function view(string $uuid): Response
    {
        $this->authorizePaymentProof($uuid);

        return $this->fileProxyService->view($uuid);
    }

    public function download(string $uuid): Response
    {
        $this->authorizePaymentProof($uuid);

        return $this->fileProxyService->download($uuid);
    }

    private function authorizePaymentProof(string $uuid): void
    {
        $actor = Auth::user();

        if ($actor === null) {
            abort(403);
        }

        $this->paymentReviewService->authorizeFileAccessIfPaymentProof($actor, $uuid);
    }
}
