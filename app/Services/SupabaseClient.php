<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SupabaseClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function request(string $method, string $path, $body = null, bool $useServiceRole = true): ?array
    {
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');
        if ($base === '') {
            return null;
        }

        $url = $base . $path;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (strtoupper($method) === 'POST') {
            $headers['Prefer'] = 'return=representation';
        }

        $serviceKey = (string) ($this->config['service_role_key'] ?? '');
        $anonKey = (string) ($this->config['anon_key'] ?? '');

        if ($useServiceRole && $serviceKey !== '') {
            $headers['apikey'] = $serviceKey;
            $headers['Authorization'] = 'Bearer ' . $serviceKey;
        } elseif ($anonKey !== '') {
            $headers['apikey'] = $anonKey;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->send($method, $url, $body === null ? [] : [
                    'body' => is_string($body) ? $body : json_encode($body),
                ]);

            $raw = $response->body();
            $decoded = json_decode($raw, true);

            return [
                'status' => $response->status(),
                'body' => is_array($decoded) ? $decoded : null,
                'raw' => $raw,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'body' => null,
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getUser(string $idToken): ?array
    {
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');
        if ($base === '') {
            return null;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $idToken,
        ];

        $serviceKey = (string) ($this->config['service_role_key'] ?? '');
        if ($serviceKey !== '') {
            $headers['apikey'] = $serviceKey;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get($base . '/auth/v1/user');

            $raw = $response->body();
            $decoded = json_decode($raw, true);

            return [
                'status' => $response->status(),
                'body' => is_array($decoded) ? $decoded : null,
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'body' => null,
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function safeRequest(string $method, string $path, $body = null, bool $useServiceRole = true): array
    {
        $res = $this->request($method, $path, $body, $useServiceRole);

        if ($res === null) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'Request failed',
                'raw' => null,
            ];
        }

        $status = (int) ($res['status'] ?? 0);
        if ($status >= 200 && $status < 300) {
            return [
                'ok' => true,
                'status' => $status,
                'body' => $res['body'] ?? null,
                'raw' => $res['raw'] ?? null,
            ];
        }

        $message = 'Supabase returned HTTP ' . $status;
        if (!empty($res['error'])) {
            $message .= ': ' . $res['error'];
        }

        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'body' => $res['body'] ?? null,
            'raw' => $res['raw'] ?? null,
        ];
    }
}
