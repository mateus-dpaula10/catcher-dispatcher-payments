<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\DadosSusanPetRescue;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        $amount   = (float) $request->input('amount', 0);
        $currency = strtoupper((string) $request->input('currency', 'USD'));
        $period   = (string) $request->input('period', 'one_time'); // one_time|monthly

        if ($amount <= 0) return response()->json(['message' => 'Invalid amount'], 422);

        $externalId = (string) $request->input('external_id', '');
        if (!$externalId) return response()->json(['message' => 'Missing external_id'], 422);

        $email = (string) $request->input('email', '');

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // volta sempre para a página origem (susanpetrescue.org)
        $successUrl = 'https://susanpetrescue.org/';
        $cancelUrl  = 'https://susanpetrescue.org/';

        $metadata = [
            'external_id' => $externalId,
            'period'      => $period,
            'page_url'    => (string) $request->input('page_url', ''),
            'utm_source'  => (string) $request->input('utm_source', ''),
            'utm_medium'  => (string) $request->input('utm_medium', ''),
            'utm_campaign'=> (string) $request->input('utm_campaign', ''),
            'utm_content' => (string) $request->input('utm_content', ''),
            'utm_term'    => (string) $request->input('utm_term', ''),
            'utm_id'      => (string) $request->input('utm_id', ''),
            'fbclid'      => (string) $request->input('fbclid', ''),
            'gclid'       => (string) $request->input('gclid', ''),
        ];

        $amountCents = (int) round($amount * 100);

        $lineItem = [
            'quantity' => 1,
            'price_data' => [
                'currency' => strtolower($currency),
                'product_data' => ['name' => 'Donation'],
                'unit_amount' => $amountCents,
            ],
        ];

        $mode = 'payment';
        if ($period === 'monthly') {
            $mode = 'subscription';
            $lineItem['price_data']['recurring'] = ['interval' => 'month'];
        }

        $sessionPayload = [
            'mode' => $mode,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $email ?: null,
            'line_items' => [$lineItem],
            'metadata' => $metadata,
        ];

        if ($mode === 'payment') {
            $sessionPayload['payment_intent_data'] = [
                'metadata' => $metadata,
            ];
        } else {
            $sessionPayload['subscription_data'] = [
                'metadata' => $metadata,
            ];
        }

        $session = Session::create($sessionPayload);

        return response()->json(['id' => $session->id]);
    }

    public function createPaymentIntent(Request $request)
    {
        // Payload do front
        $amount      = (float) $request->input('amount', 0);
        $currency    = strtoupper((string) $request->input('currency', env('STRIPE_CURRENCY', 'USD')));
        $externalId  = (string) $request->input('external_id', '');
        $email       = (string) $request->input('email', '');
        $firstName   = (string) $request->input('first_name', '');
        $lastName    = (string) $request->input('last_name', '');
        $pageUrl     = (string) $request->input('page_url', $request->header('referer', ''));

        // Segurança mínima
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 100) { // $1 mínimo (ajuste se quiser)
            return response()->json(['message' => 'Invalid amount'], 422);
        }
        if (!$externalId) {
            return response()->json(['message' => 'Missing external_id'], 422);
        }

        // Captura UTMs/clickids (guarda no metadata)
        $metaKeys = [
            'utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id',
            'fbclid','gclid','gbraid','wbraid','ttclid','msclkid','clickid','src',
            'page_url'
        ];

        $metaMax = 500;
        $trimMeta = function ($val) use ($metaMax) {
            $val = (string) $val;
            if (strlen($val) <= $metaMax) return $val;
            return substr($val, 0, $metaMax);
        };

        $metadata = [
            'external_id' => $trimMeta($externalId),
            'first_name'  => $trimMeta($firstName),
            'last_name'   => $trimMeta($lastName),
            'email'       => $trimMeta($email),
            'page_url'    => $trimMeta($pageUrl),
        ];

        foreach ($metaKeys as $k) {
            $v = $request->input($k);
            if (!is_null($v) && $v !== '') $metadata[$k] = $trimMeta($v);
        }

        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true, // Payment Element decide (card, apple pay, etc)
                ],
                'receipt_email' => $email ?: null,
                'description' => 'Donation',
                'metadata' => $metadata,

                // opcional: ajuda a evitar fraude
                // 'statement_descriptor' => 'LUSA DONATION', // precisa aprovar na Stripe
            ]);

            // Log útil
            Log::info('Stripe PaymentIntent created', [
                'external_id' => $externalId,
                'intent_id' => $intent->id,
                'amount_cents' => $amountCents,
                'livemode' => $intent->livemode,
            ]);

            return response()->json([
                'client_secret' => $intent->client_secret,
                'id' => $intent->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Stripe createPaymentIntent error', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Stripe error'], 500);
        }
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        Log::info('Stripe webhook raw payload', [
            'body' => $payload,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'headers' => [
                'stripe-signature' => $sig,
                'content-type' => $request->header('Content-Type'),
            ],
        ]);

        try {
            $event = Webhook::constructEvent($payload, $sig, env('STRIPE_WEBHOOK_SECRET'));
        } catch (\Throwable $e) {
            return response('Invalid', 400);
        }

        $eventType = (string) ($event->type ?? '');
        if (!in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            return response('OK', 200);
        }

        $intent = $event->data->object ?? null;
        if (!$intent) {
            return response('OK', 200);
        }

        $intentId = (string) ($intent->id ?? '');
        $dedupeKey = 'stripe:webhook:' . ((string) ($event->id ?? $intentId));
        if ($dedupeKey !== 'stripe:webhook:') {
            if (!Cache::add($dedupeKey, 1, now()->addDays(2))) {
                Log::info('Stripe webhook duplicate', [
                    'event_id' => (string) ($event->id ?? ''),
                    'intent_id' => $intentId ?: null,
                    'type' => $eventType ?: null,
                ]);
                return response('OK', 200);
            }
        }

        $meta = [];
        if (isset($intent->metadata)) {
            if (is_array($intent->metadata)) {
                $meta = $intent->metadata;
            } elseif (is_object($intent->metadata) && method_exists($intent->metadata, 'toArray')) {
                $meta = (array) $intent->metadata->toArray();
            } else {
                $meta = (array) $intent->metadata;
            }
        }

        $pickMeta = function (string $k, $default = '') use ($meta) {
            if (array_key_exists($k, $meta) && $meta[$k] !== null && $meta[$k] !== '') return $meta[$k];
            return $default;
        };

        $charge = null;
        if (isset($intent->charges) && isset($intent->charges->data) && is_array($intent->charges->data) && count($intent->charges->data) > 0) {
            $charge = $intent->charges->data[0];
        }

        $billing = $charge->billing_details ?? null;
        $chargeEmail = (string) ($billing->email ?? '');
        $chargeName  = (string) ($billing->name ?? '');
        $chargePhone = (string) ($billing->phone ?? '');

        $period = strtolower(trim((string) $pickMeta('period', 'one_time')));
        $isMonthlyMode = in_array($period, ['month', 'monthly'], true);

        $firstName = (string) $pickMeta('first_name', '');
        $lastName  = (string) $pickMeta('last_name', '');
        $email     = (string) $pickMeta('email', '');
        $phone     = (string) $pickMeta('phone', '');

        if ($email === '' && $chargeEmail !== '') $email = $chargeEmail;
        if ($phone === '' && $chargePhone !== '') $phone = $chargePhone;
        if ($firstName === '' && $lastName === '' && $chargeName !== '') {
            $parts = preg_split('/\s+/', trim($chargeName));
            if (!empty($parts)) {
                $firstName = array_shift($parts) ?: '';
                $lastName  = trim(implode(' ', $parts));
            }
        }

        $pageUrl = (string) $pickMeta('page_url', '');
        if ($pageUrl === '') {
            $pageUrl = (string) ($request->header('Referer') ?: 'https://susanpetrescue.org/');
        }

        $utmSource   = (string) $pickMeta('utm_source', '');
        $utmCampaign = (string) $pickMeta('utm_campaign', '');
        $utmMedium   = (string) $pickMeta('utm_medium', '');
        $utmContent  = (string) $pickMeta('utm_content', '');
        $utmTerm     = (string) $pickMeta('utm_term', '');
        $utmId       = (string) $pickMeta('utm_id', '');

        $fbp    = (string) $pickMeta('fbp', '');
        $fbc    = (string) $pickMeta('fbc', '');
        $fbclid = (string) $pickMeta('fbclid', '');

        $currency = strtoupper((string) ($intent->currency ?? 'USD'));
        $amountCents = (int) ($intent->amount_received ?? $intent->amount ?? 0);
        $amount = (float) ($amountCents / 100);

        $eventTime = (int) ($intent->created ?? time());
        if ($eventTime <= 0) $eventTime = time();

        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';
        $amountFormatted = number_format($amount, 2, '.', '');
        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isMonthlyMode ? " R" : "");

        $externalIdFinal = (string) $pickMeta('external_id', '');
        if ($externalIdFinal === '' && $intentId !== '') {
            $externalIdFinal = 'st_' . $intentId;
        }

        $status = $eventType === 'payment_intent.succeeded' ? 'paid' : 'failed';

        $payload = [
            'external_id'        => $externalIdFinal,
            'status'             => $status,

            'amount'             => $amount,
            'amount_cents'       => $amountCents,
            'currency'           => $currency,

            'first_name'         => $firstName ?: null,
            'last_name'          => $lastName ?: null,
            'payer_name'         => trim($firstName . ' ' . $lastName) ?: null,
            'payer_document'     => '',

            'email'              => $email ?: null,
            'phone'              => $phone ?: null,
            'ip'                 => $request->ip(),
            'client_user_agent'  => $request->userAgent(),

            'fbp'                => $fbp ?: null,
            'fbc'                => $fbc ?: null,
            'fbclid'             => $fbclid ?: null,

            'utm_source'         => $utmSource ?: null,
            'utm_campaign'       => $utmCampaign ?: null,
            'utm_medium'         => $utmMedium ?: null,
            'utm_content'        => $utmContent ?: null,
            'utm_term'           => $utmTerm ?: null,
            'utm_id'             => $utmId ?: null,

            'event_time'         => (int) $eventTime,
            'confirmed_at'       => date('c', (int) $eventTime),
            'page_url'           => $pageUrl,

            'product_label'      => $productLabel,
            'amount_formatted'   => $amountFormatted,

            'donation_type'      => 'stripe',
            'recurring'          => $isMonthlyMode,
            'method'             => $isMonthlyMode ? 'stripe recurring' : 'stripe',

            'transaction_id'     => $intentId ?: null,
        ];

        Log::info('Stripe webhook normalized', [
            'event_type' => $eventType ?: null,
            'intent_id' => $intentId ?: null,
            'external_id' => $payload['external_id'] ?: null,
            'status' => $payload['status'] ?: null,
        ]);

        $dado = null;
        $country = 'US';
        $region = '';
        $city = '';

        $setIf = function ($model, string $col, $val) {
            try {
                if ($val === null) return;
                if (is_string($val) && trim($val) === '') return;

                $table = method_exists($model, 'getTable') ? $model->getTable() : null;
                if (!$table) return;

                if (Schema::hasColumn($table, $col)) {
                    $model->{$col} = $val;
                }
            } catch (\Throwable $e) {
            }
        };

        $normName = function (?string $s): string {
            $s = (string) $s;
            $s = trim($s);
            if ($s === '') return '';
            $s = Str::ascii($s);
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^a-z0-9]+/i', '', $s);
            return $s ?: '';
        };

        try {
            if ($payload['external_id'] !== '') {
                $dado = DadosSusanPetRescue::where('external_id', $payload['external_id'])->first();
            }

            if (!$dado && $intentId !== '') {
                $q = DadosSusanPetRescue::query();
                $hasTransaction = Schema::hasColumn((new DadosSusanPetRescue)->getTable(), 'transaction_id');
                if ($hasTransaction) $q->orWhere('transaction_id', $intentId);
                $dado = $q->first();
            }

            if (!$dado && $email !== '' && $amountCents > 0) {
                $cands = DadosSusanPetRescue::query()
                    ->where('email', (string) $email)
                    ->where(function ($qq) {
                        $qq->whereNull('status')->orWhere('status', '!=', 'paid');
                    })
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get();

                foreach ($cands as $row) {
                    $rowCents = (int) ($row->amount_cents ?? 0);
                    if ($rowCents <= 0 && isset($row->amount)) {
                        $rowCents = (int) round(((float) $row->amount) * 100);
                    }

                    if ($rowCents !== $amountCents) continue;

                    if ($eventTime > 0 && !empty($row->created_at)) {
                        $rowTs = strtotime((string) $row->created_at);
                        if ($rowTs && abs($rowTs - $eventTime) > (6 * 3600)) {
                            continue;
                        }
                    }

                    if ($firstName !== '') {
                        if ($normName((string) ($row->first_name ?? '')) !== $normName($firstName)) {
                            continue;
                        }
                    }

                    $dado = $row;
                    break;
                }
            }

            if (!$dado && $firstName !== '') {
                $recent = DadosSusanPetRescue::orderByDesc('id')->limit(200)->get();
                foreach ($recent as $row) {
                    if ($normName((string) ($row->first_name ?? '')) === $normName($firstName)) {
                        $dado = $row;
                        break;
                    }
                }
            }

            Log::info('BD match Stripe webhook', [
                'found' => (bool) $dado,
                'matched_id' => $dado->id ?? null,
                'matched_external_id' => $dado->external_id ?? null,
                'intent_id' => $intentId ?: null,
            ]);

            if ($dado) {
                $dado->status = $status;

                $setIf($dado, 'currency', $currency);
                $setIf($dado, 'amount', (float) $amount);
                $setIf($dado, 'amount_cents', (int) $amountCents);
                $setIf($dado, 'event_time', (int) $eventTime);
                $setIf($dado, 'confirmed_at', date('c', (int) $eventTime));

                $setIf($dado, 'first_name', $firstName);
                $setIf($dado, 'last_name', $lastName);
                $setIf($dado, 'email', $email);
                $setIf($dado, 'phone', $phone);

                $setIf($dado, 'fbp', $payload['fbp']);
                $setIf($dado, 'fbc', $payload['fbc']);
                $setIf($dado, 'fbclid', $payload['fbclid']);

                $setIf($dado, 'utm_source', $utmSource);
                $setIf($dado, 'utm_campaign', $utmCampaign);
                $setIf($dado, 'utm_medium', $utmMedium);
                $setIf($dado, 'utm_content', $utmContent);
                $setIf($dado, 'utm_term', $utmTerm);
                $setIf($dado, 'utm_id', $utmId);

                $setIf($dado, 'method', $isMonthlyMode ? 'stripe recurring' : 'stripe');
                $setIf($dado, 'donation_type', 'stripe');
                $setIf($dado, 'recurring', (int) $isMonthlyMode);

                $setIf($dado, 'transaction_id', $intentId ?: null);

                $setIf($dado, 'ip', $payload['ip'] ?? null);
                $setIf($dado, 'client_user_agent', $payload['client_user_agent'] ?? null);

                $dado->save();

                $dbCountry = strtoupper(trim((string) ($dado->_country ?? '')));
                if ($dbCountry !== '' && $dbCountry !== 'XX') $country = $dbCountry;

                $region = (string) ($dado->state ?? $dado->region ?? $dado->_region ?? '');
                $city   = (string) ($dado->city ?? $dado->_city ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao buscar/atualizar BD (Stripe webhook)', [
                'error' => $e->getMessage(),
                'external_id' => $payload['external_id'] ?? null,
                'intent_id' => $intentId ?: null,
            ]);
        }

        if (!$dado) {
            Log::warning('Stripe webhook: registro nao encontrado no BD, ignorando CAPI/UTM', [
                'external_id' => $payload['external_id'] ?? null,
                'intent_id' => $intentId ?: null,
                'status' => $payload['status'] ?? null,
            ]);
            return response('OK', 200);
        }

        if ($payload['status'] !== 'paid' || (float) $payload['amount'] < 1) {
            Log::info('Stripe webhook: not paid or invalid amount', [
                'status' => $payload['status'],
                'amount' => $payload['amount'],
                'external_id' => $payload['external_id'],
            ]);
            return response('OK', 200);
        }

        $utmPaymentMethod = 'credit_card';

        $capiPayload = null;

        $normalize = fn($str) => strtolower(trim((string) $str));

        $hashedEmail = $payload['email'] ? hash('sha256', $normalize($payload['email'])) : null;

        $cleanPhone  = $payload['phone'] ? preg_replace('/\D+/', '', (string) $payload['phone']) : null;
        $hashedPhone = $cleanPhone ? hash('sha256', $cleanPhone) : null;

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

        $baseId = (string) ($payload['external_id'] ?? '');
        if ($baseId === '') $baseId = (string) ($payload['email'] ?? '') . '|' . (string) ($payload['event_time'] ?? time());

        $eventIdFor = fn(string $eventName) => substr(hash('sha256', $baseId . '|' . $eventName), 0, 32);

        $dataEvents = [[
            'event_name'       => 'Purchase',
            'event_time'       => $payload['event_time'] ?? time(),
            'action_source'    => 'website',
            'event_id'         => $eventIdFor('Purchase'),
            'event_source_url' => $payload['page_url'],
            'user_data'        => $userData,
            'custom_data'      => $customData,
        ]];

        $targets = [];
        $camp = strtoupper(trim((string) ($payload['utm_campaign'] ?? '')));

        if (preg_match('/\bB1S\b/i', $camp)) {
            $targets = ['b1s' => 'facebook_capi_susan_pet_rescue_b1s'];
        } elseif (preg_match('/\bB2S\b/i', $camp)) {
            $targets = ['b2s' => 'facebook_capi_susan_pet_rescue_b2s'];
        } else {
            $targets = [
                'b1s' => 'facebook_capi_susan_pet_rescue_b1s',
                'b2s' => 'facebook_capi_susan_pet_rescue_b2s'
            ];

            Log::warning('utm_campaign missing B1S/B2S - fallback targets=both (Stripe)', [
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'intent_id' => $intentId ?: null,
                'email' => $payload['email'] ?? null,
            ]);
        }

        foreach ($targets as $label => $serviceKey) {
            $pixelId  = config("services.{$serviceKey}.pixel_id");
            $apiToken = config("services.{$serviceKey}.access_token");

            if (!$pixelId || !$apiToken) {
                Log::warning("CAPI creds missing ({$label})", [
                    'service'  => $serviceKey,
                    'pixelId'  => $pixelId,
                    'hasToken' => !empty($apiToken),
                ]);
                continue;
            }

            $capiPayload = [
                'data' => $dataEvents,
                'access_token' => $apiToken,
            ];

            Log::info("CAPI Payload (Stripe paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'payload'  => $capiPayload,
            ]);

            $r = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

            Log::info("Facebook CAPI response (Stripe paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'status'   => $r->status(),
                'body'     => $r->body(),
            ]);
        }

        $utmPayload = [
            'orderId' => $intentId !== '' ? ('st_' . $intentId) : ('st_' . substr(bin2hex(random_bytes(4)), 0, 8)),
            'platform' => 'Checkout',
            'paymentMethod' => $utmPaymentMethod,
            'status' => 'paid',
            'createdAt' => $payload['event_time'] ? date('c', (int) $payload['event_time']) : now()->toIso8601String(),
            'approvedDate' => $payload['confirmed_at'],
            'refundedAt' => null,

            'customer' => [
                'name'     => $payload['payer_name'] ?? '',
                'email'    => $payload['email'] ?? '',
                'phone'    => $payload['phone'] ?? '',
                'document' => $payload['payer_document'] ?? '',
                'country'  => $country,
                'ip'       => $payload['ip'] ?? '',
            ],

            'products' => [[
                'id' => 'SPR',
                'name' => $payload['product_label'],
                'planId' => $payload['amount_formatted'],
                'planName' => $payload['product_label'],
                'quantity' => 1,
                'priceInCents' => $payload['amount_cents'],
            ]],

            'trackingParameters' => [
                'src'          => $payload['utm_source'] ?? '',
                'utm_source'   => $payload['utm_source'] ?? '',
                'utm_campaign' => $payload['utm_campaign'] ?? '',
                'utm_medium'   => $payload['utm_medium'] ?? '',
                'utm_content'  => $payload['utm_content'] ?? '',
                'utm_term'     => $payload['utm_term'] ?? '',
                'country'      => $country,
                'region'       => $region,
                'city'         => $city,
            ],

            'commission' => [
                'totalPriceInCents'     => $payload['amount_cents'],
                'gatewayFeeInCents'     => 0,
                'userCommissionInCents' => $payload['amount_cents'],
                'currency'              => $payload['currency'],
            ],

            'isTest' => false
        ];

        $utmUrl = config('services.utmify_susan_pet_rescue.url');
        $utmKey = config('services.utmify_susan_pet_rescue.api_key');

        if ($utmUrl && $utmKey) {
            $r = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-token'  => $utmKey,
            ])->post($utmUrl, $utmPayload);

            Log::info('Utmify Payload (Stripe paid) sent', $utmPayload);
            Log::info("Utmify response (Stripe paid)", [
                'status' => $r->status(),
                'body'   => $r->body(),
            ]);
        } else {
            Log::warning('UTMIFY url or api key not configured (Stripe)', [
                'utmUrl' => $utmUrl,
                'utmKey' => !empty($utmKey),
            ]);
        }

        return response('OK', 200);
    }
}
