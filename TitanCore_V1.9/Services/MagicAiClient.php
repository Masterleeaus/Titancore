<?php

namespace Modules\TitanCore\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MagicAiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 60,
    ) {}

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->withBasicAuth($this->apiKey, '')
            ->acceptJson();
    }

    /**
     * Generic request forwarder to MagicAI.
     *
     * @param string $method  GET|POST|PUT|PATCH|DELETE
     * @param string $path    Path starting with '/' (e.g. /v1/tools)
     * @param array  $payload JSON payload
     * @param array  $headers Extra headers
     */
    public function request(string $method, string $path, array $payload = [], array $headers = []): array
    {
        $method = strtoupper($method);
        if (!Str::startsWith($path, '/')) {
            $path = '/' . $path;
        }

        $url = rtrim($this->baseUrl, '/') . $path;

        $req = $this->client()->withHeaders($headers);

        $resp = match ($method) {
            'GET'    => $req->get($url, $payload),
            'POST'   => $req->post($url, $payload),
            'PUT'    => $req->put($url, $payload),
            'PATCH'  => $req->patch($url, $payload),
            'DELETE' => $req->delete($url, $payload),
            default  => throw new \InvalidArgumentException('Unsupported method: ' . $method),
        };

        return [
            'ok' => $resp->successful(),
            'status' => $resp->status(),
            'headers' => $resp->headers(),
            'body' => $resp->json() ?? $resp->body(),
        ];
    }
}
