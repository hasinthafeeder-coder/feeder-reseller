<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UploadOrderImportRequest;
use App\Services\Order\ResellerOrderImportService;
use Feeder\Core\Models\OrderImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImportController extends Controller
{
    public function __construct(
        private readonly ResellerOrderImportService $importService,
    ) {}

    public function template(): StreamedResponse
    {
        $csv = $this->importService->templateCsv();

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            'order-import-template.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    public function upload(UploadOrderImportRequest $request): JsonResponse
    {
        $result = $this->importService->uploadAndValidate(
            Auth::user(),
            $request->file('file'),
        );

        return response()->json([
            'data' => $result,
            'message' => 'Import file validated.',
        ]);
    }

    public function show(OrderImportBatch $batch): JsonResponse
    {
        $result = $this->importService->show(Auth::user(), $batch);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function process(OrderImportBatch $batch): JsonResponse
    {
        $result = $this->importService->process(Auth::user(), $batch);

        return response()->json([
            'data' => $result,
            'message' => 'Valid import rows were created as orders.',
        ]);
    }
}
