<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BackfillSusanPetRescuePaidControllerUtmify extends Controller
{
    public function resendUtmifyOne(Request $request)
    {
        // 1) valida token simples (o mesmo X-SAHToken que coloquei no WP)
        $token = $request->header('X-SAHToken');
        if ($token !== config('services.givewp.secret')) {
            return response()->json(['ok' => false, 'message' => 'unauthorized'], 401);
        }

        $externalId = (string) ($request->input('external_id') ?? '');
        if (!$externalId) {
            return response()->json(['ok' => false, 'message' => 'missing external_id'], 422);
        }

        $dado = \App\Models\DadosSusanPetRescue::where('external_id', $externalId)->first();
        if (!$dado) {
            return response()->json(['ok' => false, 'message' => 'external_id not found'], 404);
        }

        // ==========================================================
        // HELPERS PADRÃO (igual ao seu)
        // ==========================================================
        $currency = strtoupper($dado->currency ?: 'USD');

        $symbolByCurrency = [
            'USD' => '$',
            'BRL' => 'R$',
        ];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';

        $amount = (float) ($dado->amount ?? 0);
        $amountFormatted = number_format($amount, 2, '.', ''); // "30.00"

        $amountCents = (isset($dado->amount_cents) && (int) $dado->amount_cents > 0)
            ? (int) $dado->amount_cents
            : (int) round($amount * 100);

        $methodLower = strtolower((string)($dado->method ?? ''));
        $modeLower   = strtolower((string)($dado->donation_mode ?? ''));

        $isRecurring = ($methodLower === 'paypal recurring') || ($modeLower === 'month') || ($modeLower === 'monthly');

        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isRecurring ? " R" : "");

        // base payload mínimo (pra manter padrão igual)
        $payload = [
            'status'       => $dado->status,
            'amount'       => $amount,
            'amount_cents' => $amountCents,
            'currency'     => $currency,

            'first_name'   => $dado->first_name,
            'last_name'    => $dado->last_name,
            'payer_name'   => trim((string)($dado->first_name ?? '') . ' ' . (string)($dado->last_name ?? '')),
            'payer_document' => $dado->cpf ?? '',
            'confirmed_at' => $dado->event_time ? date('c', $dado->event_time) : now()->toIso8601String(),

            'email'        => $dado->email,
            'phone'        => $dado->phone,
            'ip'           => $dado->ip ?? $request->ip(),
            'event_time'   => $dado->event_time,
            'page_url'     => $dado->page_url,

            'utm_source'   => $dado->utm_source,
            'utm_campaign' => $dado->utm_campaign,
            'utm_medium'   => $dado->utm_medium,
            'utm_content'  => $dado->utm_content,
            'utm_term'     => $dado->utm_term,

            'product_label'    => $productLabel,
            'amount_formatted' => $amountFormatted,
        ];

        // Só reenviar se fizer sentido (ajuste se quiser forçar)
        if (($payload['status'] ?? null) !== 'paid') {
            return response()->json([
                'ok' => false,
                'message' => 'record is not paid (not sending to Utmify)',
                'status' => $payload['status'] ?? null,
            ], 422);
        }

        // ==========================================================
        // UTMIFY — IGUAL ao seu, mas com orderId determinístico
        // ==========================================================
        $utmPayload = [
            // IMPORTANTÍSSIMO p/ retry não duplicar:
            'orderId' => 'spr_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $externalId),

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
                    'name' => $payload['product_label'],
                    'planId' => $payload['amount_formatted'],
                    'planName' => $payload['product_label'],
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

        if (!$utmUrl || !$utmKey) {
            \Log::warning('UTMFY_URL ou UTMFY_API_KEY não configurados', [
                'utmUrl' => $utmUrl,
                'utmKey' => !empty($utmKey),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'utmify not configured',
            ], 500);
        }

        try {
            \Log::info('Utmify RESEND-ONE payload:', [
                'external_id' => $externalId,
                'utm' => $utmPayload,
            ]);

            $res = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-token'  => $utmKey,
            ])->post($utmUrl, $utmPayload);

            \Log::info("Utmify RESEND-ONE response", [
                'external_id' => $externalId,
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);

            return response()->json([
                'ok' => $res->successful(),
                'external_id' => $externalId,
                'utmify' => [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ],
                'sent' => [
                    'orderId' => $utmPayload['orderId'],
                    'product_label' => $payload['product_label'],
                    'amount_cents' => $payload['amount_cents'],
                ]
            ], $res->successful() ? 200 : 502);
        } catch (\Throwable $e) {
            \Log::error("Utmify RESEND-ONE exception", [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'exception sending to utmify',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
