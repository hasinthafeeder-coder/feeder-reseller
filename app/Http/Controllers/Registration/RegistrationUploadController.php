<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegistrationUploadRequest;
use App\Services\Registration\CompanyDetailsService;
use Illuminate\Http\JsonResponse;

class RegistrationUploadController extends Controller
{
    public function __construct(
        private readonly CompanyDetailsService $companyDetailsService,
    ) {}

    public function store(RegistrationUploadRequest $request): JsonResponse
    {
        $file = $this->companyDetailsService->uploadRegistrationFile(
            userUuid: $request->string('user_uuid')->toString(),
            category: $request->string('category')->toString(),
            file: $request->file('file'),
            entityType: $request->input('entity_type'),
            entityUuid: $request->input('entity_uuid'),
        );

        return response()->json([
            'message' => 'File uploaded successfully.',
            'file' => $file,
        ], 201);
    }
}
