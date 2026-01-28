<?php

namespace App\Services;

use App\Models\Dados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutBrService
{
    public function handleCheckoutBr(Request $request): JsonResponse
    {
        return $this->processCheckoutRequest($request, [
            'payloadLog' => 'Payload recebido do front checkout-br:',
            'disableLog' => 'checkout-br disabled because enable flag missing or wrong',
            'envKey' => 'JSZ_ZAPIER_WEBHOOK_BR',
            'configKey' => 'services.zapier.webhook_br',
            'userAgent' => 'sah-checkout-br',
            'logLabel' => 'checkout-br',
            'label' => 'checkout-br proxy',
        ]);
    }

    private function processCheckoutRequest(Request $request, array $options): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204);
        }

        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        $enable = (string) $request->query('enable', '');
        if ($enable !== '1') {
            if (!empty($options['disableLog'])) {
                Log::info($options['disableLog'], ['enable' => $enable]);
            }
            return response()->json([
                'ok' => false,
                'error' => 'TEMP_PROXY_DISABLED',
                'hint' => 'Use ?enable=1 para habilitar'
            ], 403);
        }

        try {
            $dados = $this->readCheckoutBrPayload($request);
            if (empty($dados)) {
                return response()->json(['ok' => false, 'error' => 'EMPTY_OR_INVALID_BODY'], 400);
            }

            if (!empty($options['payloadLog'])) {
                Log::info($options['payloadLog'], $dados);
            }

            $dados = $this->enrichCheckoutBrPayload($dados, $request);
            $this->persistDadosFromPayload($request, $dados);

            $forwarded = $this->forwardCheckoutRequest(
                $dados,
                $options['envKey'],
                $options['configKey'],
                $options['userAgent'],
                $options['logLabel']
            );

            $status = $forwarded['status'] ?? 200;
            if ($status < 100 || $status >= 600) {
                $status = 200;
            }

            return response()->json($forwarded, $status);
        } catch (\Exception $e) {
            Log::error(sprintf('Erro ao processar %s:', $options['label'] ?? 'checkout'), ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    private function persistDadosFromPayload(Request $request, array $data): void
    {
        $payload = $this->buildDadosPayload($request, $data);
        $customId = $payload['custom_id'] ?? null;

        if ($customId !== null && $customId !== '') {
            Dados::updateOrCreate(['custom_id' => $customId], $payload);
            return;
        }

        Dados::create($payload);
    }

    private function buildDadosPayload(Request $request, array $data): array
    {
        $ipFromPayload = $data['_ip'] ?? $data['ip'] ?? null;
        $ip = $ipFromPayload ?: $request->ip();

        $eventTime = $data['event_time'] ?? $data['date'] ?? null;
        if ($eventTime) {
            if (is_numeric($eventTime)) {
                $eventTime = strlen((string) $eventTime) > 10 ? intval($eventTime / 1000) : intval($eventTime);
            } else {
                $eventTime = strtotime($eventTime);
            }
        } else {
            $eventTime = time();
        }

        $payload = [
            'custom_id' => $data['custom_id'] ?? null,
            'status' => $data['status'] ?? $data['event'] ?? null,
            'amount' => $data['amount'] ?? null,
            'amount_cents' => $data['amount_cents'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'cpf' => $data['cpf'] ?? null,
            'ip' => $ip,
            'method' => $data['method'] ?? null,
            'event_time' => $eventTime,
            'page_url' => $data['page_url'] ?? $request->fullUrl(),
            'client_user_agent' => $data['client_user_agent'] ?? $request->userAgent(),
            'pix_key' => $data['pix_key'] ?? null,
            'pix_description' => $data['pix_description'] ?? null,
        ];

        $currentUrlParams = $request->query();
        foreach ($this->getUtmKeys() as $key) {
            if (isset($currentUrlParams[$key])) {
                $payload[$key] = $currentUrlParams[$key];
            } elseif (isset($data[$key])) {
                $payload[$key] = $data[$key];
            } else {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    private function getUtmKeys(): array
    {
        return ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'fbp', 'fbc'];
    }

    private function readCheckoutBrPayload(Request $request): ?array
    {
        $dados = $request->json()->all();
        if (!$this->hasCheckoutBrPayload($dados)) {
            $dados = $request->post();
            if (isset($dados['payload']) && is_string($dados['payload'])) {
                $decoded = json_decode($dados['payload'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $dados = $decoded;
                }
            }
        }

        if (!$this->hasCheckoutBrPayload($dados)) {
            return null;
        }

        return $dados;
    }

    private function hasCheckoutBrPayload($dados): bool
    {
        return is_array($dados) && !empty($dados);
    }

    private function enrichCheckoutBrPayload(array $dados, Request $request): array
    {
        $geo = $this->deriveCheckoutBrGeo($request);
        $defaults = [
            '_site' => config('app.url') ?: url('/'),
            '_ua' => $request->userAgent() ?? '',
            '_ip' => $this->deriveClientIp($request),
            '_time' => time(),
            '_country' => (string) ($geo['country'] ?? ''),
            '_region' => (string) ($geo['region'] ?? ''),
            '_region_code' => (string) ($geo['region_code'] ?? ''),
            '_city' => (string) ($geo['city'] ?? ''),
            'country' => (string) ($geo['country'] ?? ''),
            'region' => (string) ($geo['region'] ?? ''),
            'region_code' => (string) ($geo['region_code'] ?? ''),
            'city' => (string) ($geo['city'] ?? ''),
        ];

        return $dados + $defaults;
    }

    private function deriveCheckoutBrGeo(Request $request): array
    {
        $country = $this->firstHeaderValue($request, ['CF-IPCountry', 'CF_IPCOUNTRY']);
        $region = $this->firstHeaderValue($request, ['CF-Region', 'CF-IPRegion', 'CF_REGION']);
        $regionCode = $this->firstHeaderValue($request, ['CF-Region-Code', 'CF-IPRegionCode', 'CF-RegionCode', 'CF_REGIONCODE', 'CF_REGION_CODE']);
        $city = $this->firstHeaderValue($request, ['CF-IPCity', 'CF_IPCITY']);

        return [
            'country' => $country ? Str::upper($country) : '',
            'region' => $region,
            'region_code' => $regionCode ? Str::upper($regionCode) : '',
            'city' => $city,
        ];
    }

    private function deriveClientIp(Request $request): string
    {
        $connectIp = $this->firstHeaderValue($request, ['CF-Connecting-IP', 'CF_CONNECTING_IP']);
        if ($connectIp) {
            return trim(explode(',', $connectIp)[0]);
        }

        $forwarded = $this->firstHeaderValue($request, ['X-Forwarded-For']);
        if ($forwarded) {
            return trim(explode(',', $forwarded)[0]);
        }

        return $request->ip() ?? '';
    }

    private function firstHeaderValue(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $request->header($key);
            if ($value !== null && (string) $value !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function forwardCheckoutRequest(array $dados, string $envKey, string $configKey, string $userAgentSuffix, string $logLabel): array
    {
        $dest = env($envKey) ?: config($configKey);
        if (!$dest) {
            Log::error("{$logLabel} missing destination {$envKey}");
            return [
                'ok' => false,
                'error' => "MISSING_{$envKey}",
                'status' => 500,
            ];
        }

        $attempt = $this->deliverCheckoutPayload($dados, $dest, $userAgentSuffix, true);
        if (!$attempt['success'] || !$attempt['status']) {
            $msg = $attempt['error'] ?? 'empty_response';
            Log::error("[{$logLabel}] falhou: " . $msg);
            $retry = $this->deliverCheckoutPayload($dados, $dest, $userAgentSuffix, false);
            if (!$retry['success'] || !$retry['status']) {
                $retryMsg = $retry['error'] ?? 'empty_response';
                Log::error("[{$logLabel}] retry falhou: " . $retryMsg);
                return [
                    'ok' => false,
                    'error' => 'HTTP_ERROR',
                    'message' => $msg . ' | retry: ' . $retryMsg,
                    'status' => 502,
                ];
            }

            return [
                'ok' => ($retry['status'] >= 200 && $retry['status'] < 300),
                'status' => $retry['status'],
                'zapier_response' => $retry['body'] ?? '',
                'retry_sslverify' => false,
            ];
        }

        return [
            'ok' => ($attempt['status'] >= 200 && $attempt['status'] < 300),
            'status' => $attempt['status'],
            'zapier_response' => $attempt['body'] ?? '',
        ];
    }

    private function deliverCheckoutPayload(array $dados, string $dest, string $userAgentSuffix, bool $verify): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel/' . app()->version() . ' ' . $userAgentSuffix,
                'Expect' => '',
            ])
                ->timeout(20)
                ->withOptions([
                    'verify' => $verify,
                    'max_redirects' => 0,
                    'http_errors' => false,
                    'version' => 1.1,
                ])
                ->post($dest, $dados);

            return [
                'success' => true,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
