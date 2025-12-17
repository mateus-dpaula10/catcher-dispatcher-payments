<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DadosSusanPetRescue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaidSusanPetRescueController extends Controller
{
    public function paid(Request $request)
    {
        // 1) valida token simples (o mesmo X-SAHToken que coloquei no WP)
        $token = $request->header('X-SAHToken');
        if ($token !== config('services.givewp.secret')) {
            return response()->json(['ok'=>false,'message'=>'unauthorized'], 401);
        }

        $data = $request->all();
        Log::info("GiveWP PAID recebido", $data);

        $externalId = $data['external_id'] ?? null;
        if (!$externalId) {
            return response()->json(['ok'=>false,'message'=>'missing external_id'], 422);
        }

        // 2) acha o registro do IC
        $dado = DadosSusanPetRescue::where('external_id', $externalId)->first();
        if (!$dado) {
            // opcional: criar registro mesmo sem IC (não recomendo, mas dá)
            return response()->json(['ok'=>false,'message'=>'external_id not found'], 404);
        }

        // 3) atualiza para paid
        $dado->status = 'paid';

        if (!empty($data['payment_id']))   $dado->give_payment_id = (int)$data['payment_id'];
        if (!empty($data['currency']))     $dado->currency = (string)$data['currency'];
        if (isset($data['amount']))        $dado->amount = (float)$data['amount'];
        if (isset($data['amount_cents']))  $dado->amount_cents = (int)$data['amount_cents'];

        $dado->save();

        // ==========================================================
        // HELPERS PADRÃO (valor no formato do gráfico: "SPR $30.00")
        // ==========================================================
        $currency = strtoupper($dado->currency ?: 'USD');

        $symbolByCurrency = [
            'USD' => '$',
            'BRL' => 'R$',
        ];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';

        $amount = (float) ($dado->amount ?? 0);
        $amountFormatted = number_format($amount, 2, '.', ''); // "30.00"

        // garante amount_cents sempre correto (mesmo se vier faltando)
        $amountCents = (isset($dado->amount_cents) && (int) $dado->amount_cents > 0)
            ? (int) $dado->amount_cents
            : (int) round($amount * 100);

        // label do produto como no print: "SPR $30.00" e, se recorrente: "SPR $30.00 R"
        $methodLower = strtolower((string)($dado->method ?? ''));
        $modeLower   = strtolower((string)($dado->donation_mode ?? '')); // se existir no model

        $isRecurring = ($methodLower === 'paypal recurring') || ($modeLower === 'month') || ($modeLower === 'monthly');

        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isRecurring ? " R" : ""); // ex: "SPR $10.00 R"

        // =========================
        // 3) PAYLOAD BASE (IGUAL BR)
        // =========================
        $payload = [
            'status'            => $dado->status,
            'amount'            => $amount,
            'amount_cents'      => $amountCents,
            'currency'          => $currency,

            'first_name'        => $dado->first_name,
            'last_name'         => $dado->last_name,

            'payer_name'        => trim((string)($dado->first_name ?? '') . ' ' . (string)($dado->last_name ?? '')),
            'payer_document'    => $dado->cpf ?? '', // EUA pode ficar vazio
            'confirmed_at'      => $dado->event_time ? date('c', $dado->event_time) : now()->toIso8601String(),

            'email'             => $dado->email,
            'phone'             => $dado->phone,
            'cpf'               => $dado->cpf,
            'ip'                => $dado->ip ?? $request->ip(),
            'method'            => $dado->method,
            'event_time'        => $dado->event_time,
            'page_url'          => $dado->page_url,
            'client_user_agent' => $dado->client_user_agent ?? $request->userAgent(),

            'fbp'               => $dado->fbp ?? null,
            'fbc'               => $dado->fbc ?? null,
            'fbclid'            => $dado->fbclid ?? null,

            'utm_source'        => $dado->utm_source,
            'utm_campaign'      => $dado->utm_campaign,
            'utm_medium'        => $dado->utm_medium,
            'utm_content'       => $dado->utm_content,
            'utm_term'          => $dado->utm_term,

            'pix_key'           => $dado->pix_key,
            'pix_description'   => $dado->pix_description,

            // extras úteis p/ utmify (produto no formato do gráfico)
            'product_label'     => $productLabel,
            'amount_formatted'  => $amountFormatted,
        ];

        $capiPayload = null;
        $utmPayload  = null;

        // =========================
        // 4) CAPI — usando APENAS $payload como padrão
        // =========================
        if ($payload['status'] === 'paid' && (float) $payload['amount'] >= 1) {

            $normalize = fn($str) => strtolower(trim((string) $str));

            $hashedEmail = $payload['email'] ? hash('sha256', $normalize($payload['email'])) : null;

            $cleanPhone  = $payload['phone'] ? preg_replace('/\D+/', '', (string) $payload['phone']) : null;
            $hashedPhone = $cleanPhone ? hash('sha256', $cleanPhone) : null;

            // external_id hash estável
            $externalBase = ($payload['email'] ? $normalize($payload['email']) : '') . ($cleanPhone ?: '');
            $hashedExternalId = $externalBase ? hash('sha256', $externalBase) : null;

            $userData = array_filter([
                'em'                => $hashedEmail ? [$hashedEmail] : null,
                'ph'                => $hashedPhone ? [$hashedPhone] : null,
                'fn'                => $payload['first_name'] ? hash('sha256', $normalize($payload['first_name'])) : null,
                'ln'                => $payload['last_name']  ? hash('sha256', $normalize($payload['last_name']))  : null,

                'external_id'       => $hashedExternalId ?: null,
                'client_ip_address' => $payload['ip'] ?? null,
                'client_user_agent' => $payload['client_user_agent'] ?? null,

                'fbc'               => $payload['fbc'] ?? null,
                'fbp'               => $payload['fbp'] ?? null,
            ]);

            $customData = [
                'value'        => (float) $payload['amount'],
                'currency'     => $payload['currency'],
                'contents'     => [['id' => 'donation', 'quantity' => 1]],
                'content_type' => 'product',

                'utm_source'   => $payload['utm_source'] ?? null,
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'utm_medium'   => $payload['utm_medium'] ?? null,
                'utm_content'  => $payload['utm_content'] ?? null,
                'utm_term'     => $payload['utm_term'] ?? null,

                'lead_id'      => $payload['fbclid'] ?? null,
            ];

            $generateEventId = fn() => bin2hex(random_bytes(16));

            $eventsToSend = ['Purchase', 'Donate'];
            $dataEvents = [];

            foreach ($eventsToSend as $eventName) {
                $dataEvents[] = [
                    'event_name'       => $eventName,
                    'event_time'       => $payload['event_time'] ?? time(),
                    'action_source'    => 'website',
                    'event_id'         => $generateEventId(),
                    'event_source_url' => $payload['page_url'],
                    'user_data'        => $userData,
                    'custom_data'      => $customData,
                ];
            }

            $targets = [
                'b1s' => 'facebook_capi_susan_pet_rescue_b1s',
                'b2s' => 'facebook_capi_susan_pet_rescue_b2s',
            ];

            foreach ($targets as $label => $serviceKey) {
                $pixelId  = config("services.{$serviceKey}.pixel_id");
                $apiToken = config("services.{$serviceKey}.access_token");

                if (!$pixelId || !$apiToken) {
                    Log::warning("CAPI creds missing ({$label})", [
                        'service' => $serviceKey,
                        'pixelId' => $pixelId,
                        'hasToken' => !empty($apiToken),
                    ]);
                    continue;
                }

                $capiPayload = [
                    'data' => $dataEvents,
                    'access_token' => $apiToken,
                ];

                Log::info("CAPI Payload (GiveWP paid) - {$label}", [
                    'service'  => $serviceKey,
                    'pixel_id' => $pixelId,
                    'payload'  => $capiPayload,
                ]);

                $res = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

                Log::info("Facebook CAPI response (GiveWP paid) - {$label}", [
                    'service' => $serviceKey,
                    'pixel_id' => $pixelId,
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
            }
        }

        // =========================
        // 5) UTMIFY — usando APENAS $payload como padrão
        //    Produto/valor no formato do gráfico: "SPR $30.00"
        // =========================
        if ($payload['status'] === 'paid') {

            $utmPayload = [
                'orderId' => 'ord_' . substr(bin2hex(random_bytes(4)), 0, 8),
                'platform' => 'Checkout',
                'paymentMethod' => 'paypal',
                'status' => 'paid',
                'createdAt' => $payload['event_time'] ? date('c', $payload['event_time']) : now()->toIso8601String(),
                'approvedDate' => $payload['confirmed_at'],
                'refundedAt' => null,

                'customer' => [
                    'name' => $payload['payer_name'] ?? '',
                    'email' => $payload['email'] ?? '',
                    'phone' => $payload['phone'] ?? '',
                    'document' => $payload['payer_document'] ?? '',
                    'country' => 'US',
                    'ip' => $payload['ip'] ?? ''
                ],

                'products' => [
                    [
                        'id' => 'SPR',
                        'name' => $payload['product_label'],             // "SPR $30.00"
                        'planId' => $payload['amount_formatted'],        // "30.00"
                        'planName' => $payload['product_label'],         // "SPR $30.00"
                        'quantity' => 1,
                        'priceInCents' => $payload['amount_cents'],
                    ]
                ],

                'trackingParameters' => [
                    'src' => $payload['utm_source'] ?? '',
                    'utm_source' => $payload['utm_source'] ?? '',
                    'utm_campaign' => $payload['utm_campaign'] ?? '',
                    'utm_medium' => $payload['utm_medium'] ?? '',
                    'utm_content' => $payload['utm_content'] ?? '',
                    'utm_term' => $payload['utm_term'] ?? ''
                ],

                'commission' => [
                    'totalPriceInCents' => $payload['amount_cents'],
                    'gatewayFeeInCents' => 0,
                    'userCommissionInCents' => $payload['amount_cents'],
                    'currency' => $payload['currency'],
                ],

                'isTest' => false
            ];

            $utmUrl = config('services.utmify_susan_pet_rescue.url');
            $utmKey = config('services.utmify_susan_pet_rescue.api_key');

            if ($utmUrl && $utmKey) {
                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-token'  => $utmKey,
                ])->post($utmUrl, $utmPayload);

                Log::info('Utmify Payload (GiveWP paid) recebido:', $utmPayload);
                Log::info("Utmify response (GiveWP paid)", [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
            } else {
                Log::warning('UTMFY_URL ou UTMFY_API_KEY não configurados', [
                    'utmUrl' => $utmUrl,
                    'utmKey' => !empty($utmKey),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'GiveWP paid processado com sucesso',
            'received' => [
                'capi' => $capiPayload,
                'utm'  => $utmPayload,
            ]
        ], 200);
    }
}