<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\DadosSusanPetRescue;
use Square\Environments;
use Square\SquareClient;
use Square\Types\Money;
use Square\Payments\Requests\CreatePaymentRequest;

class SquareController extends Controller
{
    public function createPayment(Request $request)
    {
        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            return response()->json(['message' => 'Invalid amount'], 422);
        }

        $sourceId = (string) $request->input('source_id', '');
        if ($sourceId === '') {
            return response()->json(['message' => 'Missing source_id'], 422);
        }

        $externalId = (string) $request->input('external_id', '');
        if ($externalId === '') {
            return response()->json(['message' => 'Missing external_id'], 422);
        }

        $currency = strtoupper((string) $request->input('currency', 'USD'));
        $locationId = (string) env('SQUARE_LOCATION_ID', '');
        if ($locationId === '') {
            return response()->json(['message' => 'Missing Square location'], 500);
        }

        $envValue = strtolower((string) env('SQUARE_ENV', 'production'));
        $baseUrl = $envValue === 'sandbox'
            ? Environments::Sandbox->value
            : Environments::Production->value;

        $client = new SquareClient(
            token: (string) env('SQUARE_ACCESS_TOKEN', ''),
            options: ['baseUrl' => $baseUrl],
        );

        $amountMoney = new Money();
        $amountMoney->setAmount((int) round($amount * 100));
        $amountMoney->setCurrency($currency);

        $idempotencyKey = $externalId;
        $email = (string) $request->input('email', '');
        $period = (string) $request->input('period', 'one_time');

        $paymentRequest = new CreatePaymentRequest([
            'sourceId' => $sourceId,
            'idempotencyKey' => $idempotencyKey,
            'amountMoney' => $amountMoney,
            'locationId' => $locationId,
            'referenceId' => $externalId,
            'buyerEmailAddress' => $email !== '' ? $email : null,
            'note' => 'mode=' . $period,
        ]);

        $result = $client->payments->create($paymentRequest);
        $errors = $result->getErrors();

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Payment failed',
                'errors' => $errors,
            ], 422);
        }

        $payment = $result->getPayment();

        return response()->json([
            'payment_id' => $payment ? $payment->getId() : null,
        ]);
    }

    public function handlePaid(Request $request)
    {
        Log::info('Square paid payload', [
            'body' => $request->all(),
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ]);

        $rawPayload = $request->all();
        $squarePayment = data_get($rawPayload, 'data.object.payment');
        $fromWebhook = is_array($squarePayment);

        $paymentId = $fromWebhook
            ? (string) data_get($squarePayment, 'id', '')
            : (string) $request->input('payment_id', $request->input('transaction_id', ''));
        $externalId = $fromWebhook
            ? (string) data_get($squarePayment, 'reference_id', '')
            : (string) $request->input('external_id', '');

        $statusRaw = $fromWebhook
            ? strtolower((string) data_get($squarePayment, 'status', ''))
            : strtolower((string) $request->input('status', 'paid'));
        if (in_array($statusRaw, ['paid', 'completed', 'succeeded', 'success'], true)) {
            $status = 'paid';
        } elseif (in_array($statusRaw, ['failed', 'canceled', 'cancelled', 'voided'], true)) {
            $status = 'failed';
        } else {
            $status = $statusRaw !== '' ? $statusRaw : 'paid';
        }

        $dedupeKey = 'square:paid:' . ($paymentId ?: $externalId);
        if ($dedupeKey !== 'square:paid:') {
            if (!Cache::add($dedupeKey, 1, now()->addDays(2))) {
                Log::info('Square paid duplicate', [
                    'payment_id' => $paymentId ?: null,
                    'external_id' => $externalId ?: null,
                    'status' => $status ?: null,
                ]);
                return response('OK', 200);
            }
        }

        $note = $fromWebhook ? (string) data_get($squarePayment, 'note', '') : '';
        $period = strtolower(trim((string) $request->input('period', 'one_time')));
        if ($fromWebhook && $note !== '') {
            if (preg_match('/mode\s*=\s*([a-z_]+)/i', $note, $m)) {
                $period = strtolower(trim((string) $m[1]));
            }
        }
        $isMonthlyMode = in_array($period, ['month', 'monthly'], true);

        $firstName = (string) $request->input('first_name', '');
        $lastName  = (string) $request->input('last_name', '');
        $email     = $fromWebhook
            ? (string) data_get($squarePayment, 'buyer_email_address', '')
            : (string) $request->input('email', '');
        $phone     = (string) $request->input('phone', '');

        $pageUrl = (string) $request->input('page_url', '');
        if ($pageUrl === '') {
            $pageUrl = (string) ($request->header('Referer') ?: 'https://susanpetrescue.org/');
        }

        $utmSource   = (string) $request->input('utm_source', '');
        $utmCampaign = (string) $request->input('utm_campaign', '');
        $utmMedium   = (string) $request->input('utm_medium', '');
        $utmContent  = (string) $request->input('utm_content', '');
        $utmTerm     = (string) $request->input('utm_term', '');
        $utmId       = (string) $request->input('utm_id', '');

        $fbp    = (string) $request->input('fbp', '');
        $fbc    = (string) $request->input('fbc', '');
        $fbclid = (string) $request->input('fbclid', '');

        $currency = $fromWebhook
            ? strtoupper((string) data_get($squarePayment, 'amount_money.currency', 'USD'))
            : strtoupper((string) $request->input('currency', 'USD'));
        $amountCents = $fromWebhook
            ? (int) data_get($squarePayment, 'amount_money.amount', 0)
            : (int) $request->input('amount_cents', 0);
        $amount = $fromWebhook
            ? (float) ($amountCents / 100)
            : (float) $request->input('amount', 0);
        if ($amountCents <= 0 && $amount > 0) {
            $amountCents = (int) round($amount * 100);
        }
        if ($amount <= 0 && $amountCents > 0) {
            $amount = (float) ($amountCents / 100);
        }

        $eventTime = (int) $request->input('event_time', time());
        if ($fromWebhook) {
            $ts = (string) (data_get($squarePayment, 'updated_at') ?: data_get($squarePayment, 'created_at'));
            if ($ts !== '') {
                $parsed = strtotime($ts);
                if ($parsed) $eventTime = (int) $parsed;
            }
        }
        if ($eventTime <= 0) $eventTime = time();

        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';
        $amountFormatted = number_format($amount, 2, '.', '');
        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isMonthlyMode ? " R" : "");

        $externalIdFinal = $externalId;
        if ($externalIdFinal === '' && $paymentId !== '') {
            $externalIdFinal = 'sq_' . $paymentId;
        }

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

            'donation_type'      => 'square',
            'recurring'          => $isMonthlyMode,
            'method'             => $isMonthlyMode ? 'square recurring' : 'square',

            'transaction_id'     => $paymentId ?: null,
        ];

        Log::info('Square paid normalized', [
            'payment_id' => $paymentId ?: null,
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

            if (!$dado && $paymentId !== '') {
                $q = DadosSusanPetRescue::query();
                $hasTransaction = Schema::hasColumn((new DadosSusanPetRescue)->getTable(), 'transaction_id');
                if ($hasTransaction) $q->orWhere('transaction_id', $paymentId);
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

            Log::info('BD match Square paid', [
                'found' => (bool) $dado,
                'matched_id' => $dado->id ?? null,
                'matched_external_id' => $dado->external_id ?? null,
                'payment_id' => $paymentId ?: null,
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

                $setIf($dado, 'method', $isMonthlyMode ? 'square recurring' : 'square');
                $setIf($dado, 'donation_type', 'square');
                $setIf($dado, 'recurring', (int) $isMonthlyMode);

                $setIf($dado, 'transaction_id', $paymentId ?: null);

                $setIf($dado, 'ip', $payload['ip'] ?? null);
                $setIf($dado, 'client_user_agent', $payload['client_user_agent'] ?? null);

                $dado->save();

                $dbCountry = strtoupper(trim((string) ($dado->_country ?? '')));
                if ($dbCountry !== '' && $dbCountry !== 'XX') $country = $dbCountry;

                $region = (string) ($dado->state ?? $dado->region ?? $dado->_region ?? '');
                $city   = (string) ($dado->city ?? $dado->_city ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao buscar/atualizar BD (Square paid)', [
                'error' => $e->getMessage(),
                'external_id' => $payload['external_id'] ?? null,
                'payment_id' => $paymentId ?: null,
            ]);
        }

        if (!$dado) {
            Log::warning('Square paid: registro nao encontrado no BD, ignorando CAPI/UTM', [
                'external_id' => $payload['external_id'] ?? null,
                'payment_id' => $paymentId ?: null,
                'status' => $payload['status'] ?? null,
            ]);
            return response('OK', 200);
        }

        if ($payload['status'] !== 'paid' || (float) $payload['amount'] < 1) {
            Log::info('Square paid: not paid or invalid amount', [
                'status' => $payload['status'],
                'amount' => $payload['amount'],
                'external_id' => $payload['external_id'],
            ]);
            return response('OK', 200);
        }

        $utmPaymentMethod = 'credit_card';

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

            Log::warning('utm_campaign missing B1S/B2S - fallback targets=both (Square)', [
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'payment_id' => $paymentId ?: null,
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

            Log::info("CAPI Payload (Square paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'payload'  => $capiPayload,
            ]);

            $r = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

            Log::info("Facebook CAPI response (Square paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'status'   => $r->status(),
                'body'     => $r->body(),
            ]);
        }

        $utmPayload = [
            'orderId' => $paymentId !== '' ? ('sq_' . $paymentId) : ('sq_' . substr(bin2hex(random_bytes(4)), 0, 8)),
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

            Log::info('Utmify Payload (Square paid) sent', $utmPayload);
            Log::info("Utmify response (Square paid)", [
                'status' => $r->status(),
                'body'   => $r->body(),
            ]);
        } else {
            Log::warning('UTMIFY url or api key not configured (Square)', [
                'utmUrl' => $utmUrl,
                'utmKey' => !empty($utmKey),
            ]);
        }

        return response('OK', 200);
    }
}
