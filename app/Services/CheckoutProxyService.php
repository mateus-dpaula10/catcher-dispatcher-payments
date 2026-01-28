<?php

namespace App\Services;

use App\Models\DadosSusanPetRescue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutProxyService
{
    public function handleSusanPetRescue(Request $request): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204);
        }

        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        try {
            $data = $request->json()->all() ?: [];
            Log::info('Payload recebido do front Susan Pet Rescue:', $data);

            $norm = function ($v) {
                if ($v === null) {
                    return null;
                }
                $v = is_string($v) ? trim($v) : $v;
                if ($v === '') {
                    return null;
                }
                return $v;
            };
            $normUpper2 = function ($v) use ($norm) {
                $v = $norm($v);
                if ($v === null) {
                    return null;
                }
                $v = strtoupper((string) $v);
                if ($v === 'XX') {
                    return null;
                }
                return $v;
            };

            $ipFromPayload = $data['_ip'] ?? $data['ip'] ?? null;
            $ip = $norm($ipFromPayload) ?: $request->ip();

            Log::info('CF GEO debug (checkout)', [
                'request_ip' => $request->ip(),
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                'cf_connecting_ip' => $request->header('CF-Connecting-IP') ?? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null),
                'x_forwarded_for' => $request->header('X-Forwarded-For') ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null),
                'cf_ipcountry' => $request->header('CF-IPCountry') ?? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? null),
                'host' => $request->getHost(),
                'ua' => $request->userAgent(),
            ]);

            $payloadCountry = $normUpper2($data['country'] ?? $data['_country'] ?? null);
            $payloadRegionC = $norm($data['region_code'] ?? $data['_region_code'] ?? null);
            $payloadRegion = $norm($data['region'] ?? $data['_region'] ?? null);
            $payloadCity = $norm($data['city'] ?? $data['_city'] ?? null);

            $cfCountry = $normUpper2($request->header('CF-IPCountry', ''))
                ?: $normUpper2($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');

            $cfRegionCode = $norm($request->header('CF-Region-Code', ''))
                ?: $norm($_SERVER['HTTP_CF_REGION_CODE'] ?? ($_SERVER['HTTP_CF_REGIONCODE'] ?? ($_SERVER['HTTP_CF_IPREGIONCODE'] ?? null)));

            $cfCity = $norm($request->header('CF-IPCity', ''))
                ?: $norm($_SERVER['HTTP_CF_IPCITY'] ?? null);

            $country = $payloadCountry ?: $cfCountry;
            $regionC = $payloadRegionC ?: $cfRegionCode;
            $regionN = $payloadRegion ?: null;
            $city = $payloadCity ?: $cfCity;

            Log::info('GEO resolvido (checkout)', [
                'country' => $country,
                'region_code' => $regionC,
                'region' => $regionN,
                'city' => $city,
                'source' => $payloadCountry ? 'payload' : ($cfCountry ? 'cloudflare_laravel' : 'none'),
            ]);

            $utmKeys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'fbp', 'fbc'];

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

            $popup5dol = null;
            if (array_key_exists('popup_5dol', $data)) {
                $rawPopup = $data['popup_5dol'];
                if ($rawPopup !== null && $rawPopup !== '') {
                    $boolVal = filter_var($rawPopup, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($boolVal !== null) {
                        $popup5dol = $boolVal ? 1 : 0;
                    }
                }
            }

            $externalId = $norm($data['external_id'] ?? null) ?: (string) Str::uuid();

            $payload = [
                'external_id' => $externalId,
                'status' => $norm($data['status'] ?? ($data['event'] ?? null)) ?? 'initiate_checkout',
                'amount' => isset($data['amount']) ? $data['amount'] : null,
                'amount_cents' => isset($data['amount_cents']) ? $data['amount_cents'] : null,
                'first_name' => $norm($data['first_name'] ?? null),
                'last_name' => $norm($data['last_name'] ?? null),
                'name' => $norm($data['name'] ?? null),
                'email' => $norm($data['email'] ?? null),
                'phone' => $norm($data['phone'] ?? null),
                'cpf' => $norm($data['cpf'] ?? null),
                'ip' => $ip,
                'method' => $norm($data['method'] ?? null) ?? 'givewp',
                'event_time' => $eventTime,
                'page_url' => $norm($data['page_url'] ?? null) ?? $request->fullUrl(),
                'client_user_agent' => $norm($data['client_user_agent'] ?? null) ?? $request->userAgent(),
                'popup_5dol' => $popup5dol,
                'pix_key' => $norm($data['pix_key'] ?? null),
                'pix_description' => $norm($data['pix_description'] ?? null),
                'give_form_id' => $norm($data['give_form_id'] ?? null),
                '_country' => $country,
                '_region_code' => $regionC,
                '_region' => $regionN,
                '_city' => $city,
            ];

            $currentUrlParams = $request->query();
            foreach ($utmKeys as $key) {
                if (isset($currentUrlParams[$key])) {
                    $payload[$key] = $norm($currentUrlParams[$key]);
                } elseif (array_key_exists($key, $data)) {
                    $payload[$key] = $norm($data[$key]);
                }
            }

            $payloadToUpdate = [];
            foreach ($payload as $k => $v) {
                if ($v !== null) {
                    $payloadToUpdate[$k] = $v;
                }
            }

            try {
                $existing = DadosSusanPetRescue::where('external_id', $externalId)->first();
                if ($existing) {
                    $currentStatus = strtolower((string) ($existing->status ?? ''));
                    $incomingStatus = strtolower((string) ($payloadToUpdate['status'] ?? ''));
                    if ($currentStatus === 'paid' && in_array($incomingStatus, ['initiate_checkout', 'checkout', 'ic'], true)) {
                        unset($payloadToUpdate['status']);
                    }
                }
            } catch (\Throwable $e) {
            }

            $row = DadosSusanPetRescue::updateOrCreate(
                ['external_id' => $externalId],
                $payloadToUpdate
            );

            return response()->json([
                'ok' => true,
                'external_id' => $externalId,
                'id' => $row->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar dados:', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
