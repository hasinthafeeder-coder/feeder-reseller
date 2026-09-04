<?php

namespace App\Services\FileServer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class FileProxyService
{
    public function thumbnail(string $uuid, string $size = 'md'): Response
    {
        $response = $this->client()->get('/api/files/'.$uuid.'/thumbnail', [
            'size' => $size,
        ]);

        return $this->toResponse($response);
    }

    public function view(string $uuid): Response
    {
        $response = $this->client()->get('/api/files/'.$uuid.'/view');

        return $this->toResponse($response);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('feeder.file_server.url'))
            ->accept('*/*')
            ->withToken(config('feeder.file_server.api_key'));
    }

    private function toResponse(ClientResponse $response): Response
    {
        if (! $response->successful()) {
            abort($response->status());
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/octet-stream')
            ->header('Cache-Control', $response->header('Cache-Control') ?: 'private, max-age=3600');
    }
}
