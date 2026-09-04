<?php

namespace App\Http\Controllers;

use App\Services\FileServer\FileProxyService;
use Illuminate\Http\Response;

class FileProxyController extends Controller
{
    public function __construct(
        private readonly FileProxyService $fileProxyService,
    ) {}

    public function thumbnail(string $uuid, string $size = 'md'): Response
    {
        return $this->fileProxyService->thumbnail($uuid, $size);
    }

    public function view(string $uuid): Response
    {
        return $this->fileProxyService->view($uuid);
    }
}
