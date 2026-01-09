<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * OAuth token (cached).
     */
    public function accessToken(): string
    {
        $mode = (string) config('services.paypal.mode');
        $cacheKey = 'paypal:access_token:' . $mode;

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($mode) {
            $clientId = (string) config('services.paypal.client_id');
            $secret   = (string) config('services.paypal.client_secret');

            // ✅ logs leves (sem vazar segredo)
            Log::info('PayPal OAuth - requesting token', [
                'mode' => $mode,
                'has_client_id' => $clientId !== '',
                'has_client_secret' => $secret !== '',
                'base_url' => $this->baseUrl(),
            ]);

            $res = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->acceptJson()
                ->post($this->baseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$res->ok()) {
                Log::error('PayPal OAuth - failed', [
                    'mode' => $mode,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);

                throw new \RuntimeException('PayPal OAuth falhou: ' . $res->body());
            }

            $token = (string) $res->json('access_token');

            if ($token === '') {
                Log::error('PayPal OAuth - empty access_token', [
                    'mode' => $mode,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                throw new \RuntimeException('PayPal OAuth falhou: access_token vazio');
            }

            return $token;
        });
    }

    /**
     * Create Order (Orders v2)
     */
    public function createOrder(array $payload, ?string $requestId = null): array
    {
        $requestId = $requestId ?: (string) Str::uuid(); // idempotência
        $url = $this->baseUrl() . '/v2/checkout/orders';

        // ✅ log do payload (sem token)
        Log::info('PayPal createOrder - outgoing', [
            'url' => $url,
            'request_id' => $requestId,
            'payload' => $payload,
        ]);

        $res = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson() // garante JSON
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
            ])
            ->post($url, $payload);

        return [
            'ok' => $res->successful(),
            'status' => $res->status(),
            'json' => $res->json(),
            'raw' => $res->body(),
            'request_id' => $requestId,
        ];
    }

    /**
     * Capture Order (Orders v2)
     *
     * ✅ Ajuste principal:
     * - PayPal /capture NÃO deve receber array [] nem body vazio "quebrado".
     * - Forçamos body "{}" (JSON objeto), evitando MALFORMED_REQUEST_JSON.
     */
    public function captureOrder(string $orderId, ?string $requestId = null): array
    {
        $requestId = $requestId ?: (string) \Illuminate\Support\Str::uuid();
    
        $url  = $this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture";
        $body = "{}"; // ✅ PayPal gosta de objeto JSON
    
        Log::info('PayPalService captureOrder - outgoing', [
            'order_id'   => $orderId,
            'url'        => $url,
            'request_id' => $requestId,
            'body'       => $body,
        ]);
    
        $res = \Illuminate\Support\Facades\Http::withToken($this->accessToken())
            ->acceptJson()
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ])
            ->withBody($body, 'application/json')   // ✅ aqui é o ponto
            ->send('POST', $url);                   // ✅ send + withBody
    
        return [
            'ok'         => $res->successful(),
            'status'     => $res->status(),
            'json'       => $res->json(),
            'raw'        => $res->body(),
            'request_id' => $requestId,
        ];
    }

    /**
     * Verify Webhook Signature (recommended by PayPal)
     */
    public function verifyWebhookSignature(array $headers, array $event): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');
        if ($webhookId === '') return false;

        // headers PayPal chegam como PAYPAL-TRANSMISSION-ID etc (em lower no controller)
        $body = [
            'auth_algo'         => $headers['paypal-auth-algo'] ?? '',
            'cert_url'          => $headers['paypal-cert-url'] ?? '',
            'transmission_id'   => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig'  => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id'        => $webhookId,
            'webhook_event'     => $event,
        ];

        $url = $this->baseUrl() . '/v1/notifications/verify-webhook-signature';

        Log::info('PayPal verifyWebhookSignature - outgoing', [
            'url' => $url,
            'has_webhook_id' => $webhookId !== '',
            'transmission_id' => $body['transmission_id'] ?: null,
        ]);

        $res = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        $ok = $res->ok() && ($res->json('verification_status') === 'SUCCESS');

        if (!$ok) {
            Log::warning('PayPal verifyWebhookSignature - failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
        }

        return $ok;
    }
}