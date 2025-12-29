<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;
use App\Models\DadosSusanPetRescue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204);
        }

        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        try {
            // Pega tudo que vem do front (JSON)
            $data = $request->json()->all() ?: [];
            Log::info('Payload recebido do front:', $data);

            $ipFromPayload = $data['_ip'] ?? $data['ip'] ?? null;
            $ip = $ipFromPayload ?: $request->ip();

            // Chaves que queremos capturar
            $utmKeys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'fbp', 'fbc'];

            $eventTime = $data['event_time'] ?? $data['date'] ?? null;

            if ($eventTime) {
                if (is_numeric($eventTime)) {
                    // Se o número tiver 13 dígitos → milissegundos, divide por 1000
                    $eventTime = strlen((string)$eventTime) > 10 ? intval($eventTime / 1000) : intval($eventTime);
                } else {
                    // Se for string ISO 8601
                    $eventTime = strtotime($eventTime);
                }
            } else {
                $eventTime = time(); // fallback
            }

            // Monta payload básico com os valores do JSON
            $payload = [
                'status'            => $data['status'] ?? $data['event'] ?? null,
                'amount'            => $data['amount'] ?? null,
                'amount_cents'      => $data['amount_cents'] ?? null,
                'first_name'        => $data['first_name'] ?? null,
                'last_name'         => $data['last_name'] ?? null,
                'name'              => $data['name'] ?? null,
                'email'             => $data['email'] ?? null,
                'phone'             => $data['phone'] ?? null,
                'cpf'               => $data['cpf'] ?? null,
                'ip'                => $ip,
                'method'            => $data['method'] ?? null,
                'event_time'        => $eventTime,
                'page_url'          => $data['page_url'] ?? $request->fullUrl(),
                'client_user_agent' => $data['client_user_agent'] ?? $request->userAgent(),
                'pix_key'           => $data['pix_key'] ?? null,
                'pix_description'   => $data['pix_description'] ?? null,
            ];

            // Pega os parâmetros diretamente da URL
            $currentUrlParams = $request->query(); // GET params

            // Sobrescreve os valores do JSON com os valores da URL
            foreach ($utmKeys as $key) {
                if (isset($currentUrlParams[$key])) {
                    $payload[$key] = $currentUrlParams[$key]; // URL prevalece
                } elseif (isset($data[$key])) {
                    $payload[$key] = $data[$key]; // fallback para JSON
                } else {
                    $payload[$key] = null; // caso nenhum exista
                }
            }

            Dados::create($payload);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar dados:', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function handleSusanPetRescue(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204);
        }

        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        try {
            // Pega tudo que vem do front (JSON)
            $data = $request->json()->all() ?: [];
            Log::info('Payload recebido do front Susan Pet Rescue:', $data);

            // helpers
            $norm = function ($v) {
                if ($v === null) return null;
                $v = is_string($v) ? trim($v) : $v;
                if ($v === '') return null;
                return $v;
            };
            $normUpper2 = function ($v) use ($norm) {
                $v = $norm($v);
                if ($v === null) return null;
                $v = strtoupper((string)$v);
                if ($v === 'XX') return null;
                return $v;
            };

            // IP
            $ipFromPayload = $data['_ip'] ?? $data['ip'] ?? null;
            $ip = $norm($ipFromPayload) ?: $request->ip();

            // Debug do CF no Laravel (vai mostrar IP/CF do WP quando o WP chama)
            Log::info('CF GEO debug (checkout)', [
                'request_ip'       => $request->ip(),
                'remote_addr'      => $_SERVER['REMOTE_ADDR'] ?? null,
                'cf_connecting_ip' => $request->header('CF-Connecting-IP') ?? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null),
                'x_forwarded_for'  => $request->header('X-Forwarded-For') ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null),
                'cf_ipcountry'     => $request->header('CF-IPCountry') ?? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? null),
                'host'             => $request->getHost(),
                'ua'               => $request->userAgent(),
            ]);

            /**
             * =========================================================
             * ✅ GEO: prioridade para o que vem NO PAYLOAD (WP proxy)
             * =========================================================
             */
            $payloadCountry = $normUpper2($data['country'] ?? $data['_country'] ?? null);
            $payloadRegionC = $norm($data['region_code'] ?? $data['_region_code'] ?? null);
            $payloadRegion  = $norm($data['region'] ?? $data['_region'] ?? null);
            $payloadCity    = $norm($data['city'] ?? $data['_city'] ?? null);

            // Fallback: CF do Laravel (só se payload não trouxe)
            $cfCountry = $normUpper2($request->header('CF-IPCountry', ''))
                ?: $normUpper2($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');

            $cfRegionCode = $norm($request->header('CF-Region-Code', ''))
                ?: $norm($_SERVER['HTTP_CF_REGION_CODE'] ?? ($_SERVER['HTTP_CF_IPREGIONCODE'] ?? ($_SERVER['HTTP_CF_REGIONCODE'] ?? null)));

            $cfCity = $norm($request->header('CF-IPCity', ''))
                ?: $norm($_SERVER['HTTP_CF_IPCITY'] ?? null);

            $country = $payloadCountry ?: $cfCountry;
            $regionC = $payloadRegionC ?: $cfRegionCode;
            $regionN = $payloadRegion ?: null; // se não vier, mantém null
            $city    = $payloadCity ?: $cfCity;

            Log::info('GEO resolvido (checkout)', [
                'country' => $country,
                'region_code' => $regionC,
                'region' => $regionN,
                'city' => $city,
                'source' => $payloadCountry ? 'payload' : ($cfCountry ? 'cloudflare_laravel' : 'none'),
            ]);

            // UTM keys
            $utmKeys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'fbp', 'fbc'];

            // event_time
            $eventTime = $data['event_time'] ?? $data['date'] ?? null;
            if ($eventTime) {
                if (is_numeric($eventTime)) {
                    $eventTime = strlen((string)$eventTime) > 10 ? intval($eventTime / 1000) : intval($eventTime);
                } else {
                    $eventTime = strtotime($eventTime);
                }
            } else {
                $eventTime = time();
            }

            // external_id
            $externalId = $norm($data['external_id'] ?? null) ?: (string) Str::uuid();

            /**
             * =========================================================
             * ✅ Anti-"wipe": NÃO sobrescrever com null/vazio
             * - Isso resolve "payload em duas partes" apagando amount/email etc.
             * =========================================================
             */
            $payload = [
                'external_id'       => $externalId,
                'status'            => $norm($data['status'] ?? ($data['event'] ?? null)) ?? 'initiate_checkout',

                'amount'            => isset($data['amount']) ? $data['amount'] : null,
                'amount_cents'      => isset($data['amount_cents']) ? $data['amount_cents'] : null,

                'first_name'        => $norm($data['first_name'] ?? null),
                'last_name'         => $norm($data['last_name'] ?? null),
                'name'              => $norm($data['name'] ?? null),
                'email'             => $norm($data['email'] ?? null),
                'phone'             => $norm($data['phone'] ?? null),
                'cpf'               => $norm($data['cpf'] ?? null),

                'ip'                => $ip,
                'method'            => $norm($data['method'] ?? null) ?? 'givewp',
                'event_time'        => $eventTime,
                'page_url'          => $norm($data['page_url'] ?? null) ?? $request->fullUrl(),
                'client_user_agent' => $norm($data['client_user_agent'] ?? null) ?? $request->userAgent(),

                'pix_key'           => $norm($data['pix_key'] ?? null),
                'pix_description'   => $norm($data['pix_description'] ?? null),
                'give_form_id'      => $norm($data['give_form_id'] ?? null),

                // salva GEO final
                '_country'     => $country,
                '_region_code' => $regionC,
                '_region'      => $regionN,
                '_city'        => $city,
            ];

            // ✅ UTM: URL prevalece, mas se não tiver, só atualiza se veio no JSON
            $currentUrlParams = $request->query();
            foreach ($utmKeys as $key) {
                if (isset($currentUrlParams[$key])) {
                    $payload[$key] = $norm($currentUrlParams[$key]);
                } elseif (array_key_exists($key, $data)) {
                    $payload[$key] = $norm($data[$key]);
                }
                // se não existe em lugar nenhum: não seta -> não apaga o que já existe no BD
            }

            // ✅ Remove nulls (e strings vazias já viraram null pelo norm)
            // IMPORTANT: mantém 0 e "0"
            $payloadToUpdate = [];
            foreach ($payload as $k => $v) {
                if ($v !== null) $payloadToUpdate[$k] = $v;
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

    public function handleSusanPetRescueDonor(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204);
        }

        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        try {
            // Pega tudo que vem do front (JSON)
            $data = $request->json()->all() ?: [];
            Log::info('Payload recebido do front Susan Pet Rescue Donor:', $data);

            // helpers
            $norm = function ($v) {
                if ($v === null) return null;
                $v = is_string($v) ? trim($v) : $v;
                if ($v === '') return null;
                return $v;
            };
            $normUpper2 = function ($v) use ($norm) {
                $v = $norm($v);
                if ($v === null) return null;
                $v = strtoupper((string)$v);
                if ($v === 'XX') return null;
                return $v;
            };

            // IP
            $ipFromPayload = $data['_ip'] ?? $data['ip'] ?? null;
            $ip = $norm($ipFromPayload) ?: $request->ip();

            // Debug do CF no Laravel (vai mostrar IP/CF do WP quando o WP chama)
            Log::info('CF GEO debug (checkout)', [
                'request_ip'       => $request->ip(),
                'remote_addr'      => $_SERVER['REMOTE_ADDR'] ?? null,
                'cf_connecting_ip' => $request->header('CF-Connecting-IP') ?? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null),
                'x_forwarded_for'  => $request->header('X-Forwarded-For') ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null),
                'cf_ipcountry'     => $request->header('CF-IPCountry') ?? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? null),
                'host'             => $request->getHost(),
                'ua'               => $request->userAgent(),
            ]);

            /**
             * =========================================================
             * ✅ GEO: prioridade para o que vem NO PAYLOAD (WP proxy)
             * =========================================================
             */
            $payloadCountry = $normUpper2($data['country'] ?? $data['_country'] ?? null);
            $payloadRegionC = $norm($data['region_code'] ?? $data['_region_code'] ?? null);
            $payloadRegion  = $norm($data['region'] ?? $data['_region'] ?? null);
            $payloadCity    = $norm($data['city'] ?? $data['_city'] ?? null);

            // Fallback: CF do Laravel (só se payload não trouxe)
            $cfCountry = $normUpper2($request->header('CF-IPCountry', ''))
                ?: $normUpper2($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');

            $cfRegionCode = $norm($request->header('CF-Region-Code', ''))
                ?: $norm($_SERVER['HTTP_CF_REGION_CODE'] ?? ($_SERVER['HTTP_CF_IPREGIONCODE'] ?? ($_SERVER['HTTP_CF_REGIONCODE'] ?? null)));

            $cfCity = $norm($request->header('CF-IPCity', ''))
                ?: $norm($_SERVER['HTTP_CF_IPCITY'] ?? null);

            $country = $payloadCountry ?: $cfCountry;
            $regionC = $payloadRegionC ?: $cfRegionCode;
            $regionN = $payloadRegion ?: null; // se não vier, mantém null
            $city    = $payloadCity ?: $cfCity;

            Log::info('GEO resolvido (checkout)', [
                'country' => $country,
                'region_code' => $regionC,
                'region' => $regionN,
                'city' => $city,
                'source' => $payloadCountry ? 'payload' : ($cfCountry ? 'cloudflare_laravel' : 'none'),
            ]);

            // UTM keys
            $utmKeys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'utm_id', 'fbclid', 'fbp', 'fbc'];

            // event_time
            $eventTime = $data['event_time'] ?? $data['date'] ?? null;
            if ($eventTime) {
                if (is_numeric($eventTime)) {
                    $eventTime = strlen((string)$eventTime) > 10 ? intval($eventTime / 1000) : intval($eventTime);
                } else {
                    $eventTime = strtotime($eventTime);
                }
            } else {
                $eventTime = time();
            }

            // external_id
            $externalId = $norm($data['external_id'] ?? null) ?: (string) Str::uuid();

            /**
             * =========================================================
             * ✅ Anti-"wipe": NÃO sobrescrever com null/vazio
             * - Isso resolve "payload em duas partes" apagando amount/email etc.
             * =========================================================
             */
            $payload = [
                'external_id'       => $externalId,
                'status'            => $norm($data['status'] ?? ($data['event'] ?? null)) ?? 'initiate_checkout',

                'amount'            => isset($data['amount']) ? $data['amount'] : null,
                'amount_cents'      => isset($data['amount_cents']) ? $data['amount_cents'] : null,

                'first_name'        => $norm($data['first_name'] ?? null),
                'last_name'         => $norm($data['last_name'] ?? null),
                'name'              => $norm($data['name'] ?? null),
                'email'             => $norm($data['email'] ?? null),
                'phone'             => $norm($data['phone'] ?? null),
                'cpf'               => $norm($data['cpf'] ?? null),

                'ip'                => $ip,
                'method'            => $norm($data['method'] ?? null) ?? 'givewp',
                'event_time'        => $eventTime,
                'page_url'          => $norm($data['page_url'] ?? null) ?? $request->fullUrl(),
                'client_user_agent' => $norm($data['client_user_agent'] ?? null) ?? $request->userAgent(),

                'pix_key'           => $norm($data['pix_key'] ?? null),
                'pix_description'   => $norm($data['pix_description'] ?? null),
                'give_form_id'      => $norm($data['give_form_id'] ?? null),

                // salva GEO final
                '_country'     => $country,
                '_region_code' => $regionC,
                '_region'      => $regionN,
                '_city'        => $city,
            ];

            // ✅ UTM: URL prevalece, mas se não tiver, só atualiza se veio no JSON
            $currentUrlParams = $request->query();
            foreach ($utmKeys as $key) {
                if (isset($currentUrlParams[$key])) {
                    $payload[$key] = $norm($currentUrlParams[$key]);
                } elseif (array_key_exists($key, $data)) {
                    $payload[$key] = $norm($data[$key]);
                }
                // se não existe em lugar nenhum: não seta -> não apaga o que já existe no BD
            }

            // ✅ Remove nulls (e strings vazias já viraram null pelo norm)
            // IMPORTANT: mantém 0 e "0"
            $payloadToUpdate = [];
            foreach ($payload as $k => $v) {
                if ($v !== null) $payloadToUpdate[$k] = $v;
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
