<?php

namespace App\Http\Controllers;

use App\Models\DadosSusanPetRescue;
use App\Models\EmailMessage;
use App\Services\PayPalService;
use App\Jobs\SendDonationPaidEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class PayPalController extends Controller
{
    public function __construct(private PayPalService $pp) {}

    public function createOrder(Request $request)
    {
        // ✅ LOG 1: tudo que chegou (body)
        Log::info('PayPal createOrder - request body', [
            'all' => $request->all(),
        ]);

        // ✅ LOG 2: contexto do request
        Log::info('PayPal createOrder - request meta', [
            'ip'           => $request->ip(),
            'ua'           => $request->userAgent(),
            'origin'       => $request->header('Origin'),
            'referer'      => $request->header('Referer'),
            'host'         => $request->getHost(),
            'full_url'     => $request->fullUrl(),
            'method'       => $request->method(),
            'content_type' => $request->header('Content-Type'),
        ]);

        $amount   = (float) $request->input('amount', 0);
        $currency = strtoupper((string) $request->input('currency', 'USD'));

        // usa external_id do front; se não vier, gera
        $externalId = (string) ($request->input('external_id') ?: Str::uuid());

        // ✅ LOG 3: campos normalizados
        Log::info('PayPal createOrder - normalized', [
            'amount'      => $amount,
            'currency'    => $currency,
            'external_id' => $externalId,
            'period'      => (string) $request->input('period', ''),
            'page_url'    => (string) $request->input('page_url', ''),
            'utm_source'  => (string) $request->input('utm_source', ''),
            'utm_campaign'=> (string) $request->input('utm_campaign', ''),
            'fbclid'      => (string) $request->input('fbclid', ''),
        ]);

        if ($amount < 1) {
            Log::warning('PayPal createOrder - invalid amount', [
                'amount'  => $amount,
                'payload' => $request->all(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Valor inválido'], 422);
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => 'Donation',
                'custom_id' => $externalId,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
        ];

        // ✅ LOG 4: payload para PayPal
        Log::info('PayPal createOrder - paypal payload', $payload);

        $res = $this->pp->createOrder($payload);

        // ✅ CORREÇÃO 1: 201 (Created) é sucesso.
        // Então o seu PayPalService precisa usar ->successful() e não ->ok().
        // (Mesmo assim, aqui mantemos a checagem por $res['ok'])
        if (empty($res['ok'])) {
            Log::error('PayPal createOrder - PayPal API failed', [
                'paypal_status' => $res['status'] ?? null,
                'paypal_json'   => $res['json'] ?? null,
                'paypal_raw'    => $res['raw'] ?? null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Create order falhou',
                'paypal_status' => $res['status'] ?? null,
                'debug' => ($res['json'] ?? null) ?: ($res['raw'] ?? null),
            ], 500);
        }

        $orderId = (string) data_get($res['json'], 'id', '');

        // ✅ LOG 5: sucesso
        Log::info('PayPal createOrder - success', [
            'order_id' => $orderId,
            'external_id' => $externalId,
        ]);
        
        // ✅ guarda contexto por external_id (pra usar no capture/webhook)
        $ctx = [
            'external_id' => $externalId,
            'period'      => (string) $request->input('period', 'one_time'),
            'first_name'  => (string) $request->input('first_name', ''),
            'last_name'   => (string) $request->input('last_name', ''),
            'email'       => (string) $request->input('email', ''),
            'phone'       => (string) $request->input('phone', ''),
            'page_url'    => (string) $request->input('page_url', ''),
            'utm_source'  => (string) $request->input('utm_source', ''),
            'utm_medium'  => (string) $request->input('utm_medium', ''),
            'utm_campaign'=> (string) $request->input('utm_campaign', ''),
            'utm_content' => (string) $request->input('utm_content', ''),
            'utm_term'    => (string) $request->input('utm_term', ''),
            'utm_id'      => (string) $request->input('utm_id', ''),
            'gclid'       => (string) $request->input('gclid', ''),
            'gbraid'      => (string) $request->input('gbraid', ''),
            'wbraid'      => (string) $request->input('wbraid', ''),
            'fbclid'      => (string) $request->input('fbclid', ''),
            'clickid'     => (string) $request->input('clickid', ''),
            'ttclid'      => (string) $request->input('ttclid', ''),
            'msclkid'     => (string) $request->input('msclkid', ''),
            'src'         => (string) $request->input('src', ''),
            'fbp'         => (string) $request->input('fbp', ''),
            'fbc'         => (string) $request->input('fbc', ''),
        ];
        
        Cache::put('pp:ctx:' . $externalId, $ctx, now()->addHours(12));
        Log::info('PayPal createOrder - ctx cached', ['external_id' => $externalId]);

        return response()->json([
            'ok' => true,
            'id' => $orderId,
            'orderID' => $orderId,
            'external_id' => $externalId,
        ]);
    }
    
    public function webhook(Request $request)
    {
        $event = $request->all();
        Log::info('PayPal webhook - entry', ['headers' => $request->headers->all()]);

        // headers PayPal vêm como PAYPAL-TRANSMISSION-ID etc
        $headers = collect($request->headers->all())->map(fn($v) => $v[0] ?? '')->all();
        $headers = array_change_key_case($headers, CASE_LOWER);

        $verifySetting = config('services.paypal.verify_webhook', true);
        $verifySetting = filter_var($verifySetting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($verifySetting === null) {
            $verifySetting = (bool) $verifySetting;
        }

        if ($verifySetting) {
            $verified = $this->pp->verifyWebhookSignature($headers, $event);
            if (!$verified) {
                Log::warning('PayPal webhook - invalid signature', [
                    'headers' => [
                        'paypal-transmission-id' => $headers['paypal-transmission-id'] ?? null,
                        'paypal-transmission-time' => $headers['paypal-transmission-time'] ?? null,
                        'paypal-auth-algo' => $headers['paypal-auth-algo'] ?? null,
                    ],
                    'event_type' => data_get($event, 'event_type'),
                ]);

                return response()->json(['ok' => false, 'message' => 'invalid signature'], 400);
            }
        }

        return $this->handleWebhookEvent($event, $request);
    }

    public function donationNotify(Request $request)
    {
        $orderId = (string) $request->input('orderId', '');
        if ($orderId === '') {
            return response()->json(['ok' => false, 'message' => 'orderId is required'], 422);
        }

        $orderRes = $this->pp->fetchOrder($orderId);
        if (empty($orderRes['ok'])) {
            Log::warning('PayPal donation-notify - failed to fetch order', [
                'order_id' => $orderId,
                'status' => $orderRes['status'] ?? null,
                'body' => $orderRes['raw'] ?? null,
            ]);
            return response()->json(['ok' => false, 'message' => 'failed to fetch order'], 422);
        }

        $order = (array) ($orderRes['json'] ?? []);

        $ctx = [
            'external_id' => (string) ($request->input('external_id') ?: Str::uuid()),
            'period' => (string) $request->input('period', 'one_time'),
            'first_name' => (string) $request->input('first_name', ''),
            'last_name' => (string) $request->input('last_name', ''),
            'email' => (string) $request->input('email', ''),
            'phone' => (string) $request->input('phone', ''),
            'page_url' => (string) $request->input('page_url', $request->header('Referer') ?: ''),
            'utm_source' => (string) $request->input('utm_source', ''),
            'utm_medium' => (string) $request->input('utm_medium', ''),
            'utm_campaign' => (string) $request->input('utm_campaign', ''),
            'utm_content' => (string) $request->input('utm_content', ''),
            'utm_term' => (string) $request->input('utm_term', ''),
            'utm_id' => (string) $request->input('utm_id', ''),
            'fbp' => (string) $request->input('fbp', ''),
            'fbc' => (string) $request->input('fbc', ''),
            'fbclid' => (string) $request->input('fbclid', ''),
            'gclid' => (string) $request->input('gclid', ''),
            'gbraid' => (string) $request->input('gbraid', ''),
            'wbraid' => (string) $request->input('wbraid', ''),
            'clickid' => (string) $request->input('clickid', ''),
            'ttclid' => (string) $request->input('ttclid', ''),
            'msclkid' => (string) $request->input('msclkid', ''),
            'src' => (string) $request->input('src', ''),
        ];

        Cache::put('pp:ctx:' . $orderId, $ctx, now()->addHours(12));

        $resource = (array) data_get($order, 'purchase_units.0.payments.captures.0', []);
        if (empty($resource)) {
            Log::warning('PayPal donation-notify - missing capture resource', [
                'order_id' => $orderId,
            ]);
            return response()->json(['ok' => false, 'message' => 'capture resource missing'], 422);
        }

        $event = [
            'id' => 'donate-' . Str::uuid(),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => $resource,
        ];

        return $this->handleWebhookEvent($event, $request, $order);
    }

    private function handleWebhookEvent(array $event, Request $request, array $order = [])
    {
        $eventType = strtoupper((string) data_get($event, 'event_type', ''));
        Log::info('PayPal webhook - received', [
            'event_type' => $eventType ?: null,
            'event_id' => data_get($event, 'id'),
        ]);

        if (strpos($eventType, 'PAYMENT.CAPTURE.') !== 0) {
            return response()->json(['ok' => true]);
        }

        $resource = (array) data_get($event, 'resource', []);
        $captureStatus = strtoupper((string) data_get($resource, 'status', ''));
        $captureId = (string) data_get($resource, 'id', '');
        $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id', '');

        if ($captureStatus !== 'COMPLETED') {
            Log::info('PayPal webhook - capture not completed', [
                'capture_id' => $captureId ?: null,
                'status' => $captureStatus ?: null,
                'order_id' => $orderId ?: null,
                'event_type' => $eventType,
            ]);

            return response()->json(['ok' => true]);
        }

        $dedupeKey = 'pp:webhook:done:' . ($captureId !== '' ? $captureId : (string) data_get($event, 'id', ''));
        if ($dedupeKey !== 'pp:webhook:done:' && !Cache::add($dedupeKey, 1, now()->addDays(7))) {
            Log::info('PayPal webhook - duplicate event', [
                'dedupe_key' => $dedupeKey,
                'capture_id' => $captureId ?: null,
                'order_id' => $orderId ?: null,
            ]);

            return response()->json(['ok' => true]);
        }

        if (!$order && $orderId !== '') {
            $orderRes = $this->pp->fetchOrder($orderId);
            if (!empty($orderRes['ok'])) {
                $order = (array) ($orderRes['json'] ?? []);
            } else {
                Log::warning('PayPal webhook - failed to fetch order', [
                    'order_id' => $orderId,
                    'status' => $orderRes['status'] ?? null,
                    'body' => $orderRes['raw'] ?? null,
                ]);
            }
        }

        $customId = (string) data_get($order, 'purchase_units.0.custom_id', '');
        $ctx = [];
        if ($customId !== '') {
            $ctx = (array) Cache::get('pp:ctx:' . $customId, []);
        }
        if (!$ctx && $orderId !== '') {
            $ctx = (array) Cache::get('pp:ctx:' . $orderId, []);
        }

        $amountStr = (string) data_get($resource, 'amount.value', '');
        if ($amountStr === '') {
            $amountStr = (string) data_get($order, 'purchase_units.0.payments.captures.0.amount.value', '');
        }
        if ($amountStr === '') {
            $amountStr = (string) data_get($order, 'purchase_units.0.amount.value', '');
        }

        $currency = strtoupper((string) data_get($resource, 'amount.currency_code', ''));
        if ($currency === '') {
            $currency = strtoupper((string) data_get($order, 'purchase_units.0.amount.currency_code', 'USD'));
        }

        $captureTimeIso = (string) data_get($resource, 'create_time', '');
        $eventTime = $captureTimeIso ? (int) strtotime($captureTimeIso) : time();
        if (!$eventTime) $eventTime = time();

        $period = strtolower(trim((string) data_get($ctx, 'period', 'one_time')));
        $isMonthlyMode = in_array($period, ['month', 'monthly'], true);

        $firstName = (string) data_get($ctx, 'first_name', '');
        $lastName  = (string) data_get($ctx, 'last_name', '');
        $email     = (string) data_get($ctx, 'email', '');
        $phone     = (string) data_get($ctx, 'phone', '');

        $payerEmail = (string) data_get($order, 'payer.email_address', '');
        $payerFN    = (string) data_get($order, 'payer.name.given_name', '');
        $payerLN    = (string) data_get($order, 'payer.name.surname', '');

        if ($payerFN !== '') $firstName = $payerFN;
        if ($payerLN !== '') $lastName  = $payerLN;
        if ($payerEmail !== '') $email  = $payerEmail;

        $pageUrl = (string) data_get($ctx, 'page_url', '');
        if (!$pageUrl) {
            $pageUrl = (string) ($request->header('Referer') ?: 'https://susanpetrescue.org/');
        }

        $utmSource   = (string) data_get($ctx, 'utm_source', '');
        $utmCampaign = (string) data_get($ctx, 'utm_campaign', '');
        $utmMedium   = (string) data_get($ctx, 'utm_medium', '');
        $utmContent  = (string) data_get($ctx, 'utm_content', '');
        $utmTerm     = (string) data_get($ctx, 'utm_term', '');
        $utmId       = (string) data_get($ctx, 'utm_id', '');

        $fbp    = (string) data_get($ctx, 'fbp', '');
        $fbc    = (string) data_get($ctx, 'fbc', '');
        $fbclid = (string) data_get($ctx, 'fbclid', '');

        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';
        $amount = (float) $amountStr;
        $amountCents = (int) round($amount * 100);
        $amountFormatted = number_format($amount, 2, '.', '');
        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isMonthlyMode ? " R" : "");

        $ctxExternal = (string) ($ctx['external_id'] ?? '');
        $externalId = $customId !== ''
            ? $customId
            : ($ctxExternal !== '' ? $ctxExternal : ('pp_' . ($orderId ?: ($captureId ?: Str::uuid()))));

        $payload = [
            'external_id'        => $externalId,
            'status'             => 'paid',
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
            'donation_type'      => 'paypal',
            'recurring'          => $isMonthlyMode,
            'method'             => $isMonthlyMode ? 'paypal recurring' : 'paypal',
            'paypal_order_id'    => $orderId,
            'paypal_capture_id'  => $captureId ?: null,
            'paypal_transaction_id' => $captureId ?: null,
        ];

        $cacheKeys = [];
        if ($customId !== '') $cacheKeys[] = 'pp:ctx:' . $customId;
        if ($ctxExternal !== '' && $ctxExternal !== $customId) $cacheKeys[] = 'pp:ctx:' . $ctxExternal;
        if ($orderId !== '') $cacheKeys[] = 'pp:ctx:' . $orderId;

        return $this->finalizePayPalPaidPayload($payload, $orderId, $captureId, $cacheKeys);
    }

    private function finalizePayPalPaidPayload(array $payload, string $orderId, string $captureId, array $cacheKeys = [])
    {
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

                if (\Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                    $model->{$col} = $val;
                }
            } catch (\Throwable $e) {
            }
        };

        $normName = function (?string $s): string {
            $s = (string) $s;
            $s = trim($s);
            if ($s === '') return '';
            $s = \Illuminate\Support\Str::ascii($s);
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^a-z0-9]+/i', '', $s);
            return $s ?: '';
        };

        try {
            $dado = DadosSusanPetRescue::where('external_id', $payload['external_id'])->first();

            if (!$dado) {
                $q = DadosSusanPetRescue::query();
                $hasOrder = \Illuminate\Support\Facades\Schema::hasColumn((new DadosSusanPetRescue)->getTable(), 'paypal_order_id');
                $hasCap = \Illuminate\Support\Facades\Schema::hasColumn((new DadosSusanPetRescue)->getTable(), 'paypal_capture_id');

                if ($hasOrder) $q->orWhere('paypal_order_id', $orderId);
                if ($hasCap && $captureId) $q->orWhere('paypal_capture_id', $captureId);

                $dado = $q->first();
            }

            if (!$dado && !empty($payload['first_name'])) {
                $recent = DadosSusanPetRescue::orderByDesc('id')->limit(200)->get();
                foreach ($recent as $row) {
                    if ($normName((string) ($row->first_name ?? '')) === $normName($payload['first_name'])) {
                        $dado = $row;
                        break;
                    }
                }
            }

            Log::info('BD match PayPal webhook paid', [
                'found' => (bool) $dado,
                'matched_id' => $dado->id ?? null,
                'matched_external_id' => $dado->external_id ?? null,
                'paypal_order_id' => $orderId,
                'paypal_capture_id' => $captureId ?: null,
            ]);

            if ($dado) {
                $dado->status = 'paid';

                $setIf($dado, 'currency', $payload['currency']);
                $setIf($dado, 'amount', (float) $payload['amount']);
                $setIf($dado, 'amount_cents', (int) $payload['amount_cents']);
                $setIf($dado, 'event_time', (int) $payload['event_time']);
                $setIf($dado, 'confirmed_at', $payload['confirmed_at'] ?? null);

                $setIf($dado, 'first_name', $payload['first_name'] ?? null);
                $setIf($dado, 'last_name', $payload['last_name'] ?? null);
                $setIf($dado, 'email', $payload['email'] ?? null);
                $setIf($dado, 'phone', $payload['phone'] ?? null);

                $setIf($dado, 'fbp', $payload['fbp'] ?? null);
                $setIf($dado, 'fbc', $payload['fbc'] ?? null);
                $setIf($dado, 'fbclid', $payload['fbclid'] ?? null);

                $setIf($dado, 'utm_source', $payload['utm_source'] ?? null);
                $setIf($dado, 'utm_campaign', $payload['utm_campaign'] ?? null);
                $setIf($dado, 'utm_medium', $payload['utm_medium'] ?? null);
                $setIf($dado, 'utm_content', $payload['utm_content'] ?? null);
                $setIf($dado, 'utm_term', $payload['utm_term'] ?? null);
                $setIf($dado, 'utm_id', $payload['utm_id'] ?? null);

                $setIf($dado, 'page_url', $payload['page_url'] ?? null);

                $setIf($dado, 'method', $payload['method'] ?? null);
                $setIf($dado, 'donation_type', $payload['donation_type'] ?? null);
                $setIf($dado, 'recurring', (int) ($payload['recurring'] ?? 0));

                $setIf($dado, 'paypal_order_id', $orderId);
                $setIf($dado, 'paypal_capture_id', $captureId ?: null);
                $setIf($dado, 'paypal_transaction_id', $captureId ?: null);

                $setIf($dado, 'ip', $payload['ip'] ?? null);
                $setIf($dado, 'client_user_agent', $payload['client_user_agent'] ?? null);

                $dado->save();

                $dbCountry = strtoupper(trim((string) ($dado->_country ?? '')));
                if ($dbCountry !== '' && $dbCountry !== 'XX') $country = $dbCountry;

                $region = (string) ($dado->state ?? $dado->region ?? '');
                $city   = (string) ($dado->city ?? '');
            } else {
                Log::warning('PayPal webhook paid sem IC no BD — criando registro fallback', [
                    'external_id' => $payload['external_id'],
                    'order_id' => $orderId,
                    'capture_id' => $captureId ?: null,
                    'email' => $payload['email'] ?? null,
                ]);

                $dado = new DadosSusanPetRescue();
                $dado->external_id = $payload['external_id'];
                $dado->status = 'paid';

                $setIf($dado, 'currency', $payload['currency']);
                $setIf($dado, 'amount', (float) $payload['amount']);
                $setIf($dado, 'amount_cents', (int) $payload['amount_cents']);
                $setIf($dado, 'event_time', (int) $payload['event_time']);
                $setIf($dado, 'confirmed_at', $payload['confirmed_at'] ?? null);
                $setIf($dado, 'page_url', $payload['page_url'] ?? null);

                $setIf($dado, 'first_name', $payload['first_name'] ?? null);
                $setIf($dado, 'last_name', $payload['last_name'] ?? null);
                $setIf($dado, 'email', $payload['email'] ?? null);
                $setIf($dado, 'phone', $payload['phone'] ?? null);

                $setIf($dado, 'fbp', $payload['fbp'] ?? null);
                $setIf($dado, 'fbc', $payload['fbc'] ?? null);
                $setIf($dado, 'fbclid', $payload['fbclid'] ?? null);

                $setIf($dado, 'utm_source', $payload['utm_source'] ?? null);
                $setIf($dado, 'utm_campaign', $payload['utm_campaign'] ?? null);
                $setIf($dado, 'utm_medium', $payload['utm_medium'] ?? null);
                $setIf($dado, 'utm_content', $payload['utm_content'] ?? null);
                $setIf($dado, 'utm_term', $payload['utm_term'] ?? null);
                $setIf($dado, 'utm_id', $payload['utm_id'] ?? null);

                $setIf($dado, 'method', $payload['method'] ?? null);
                $setIf($dado, 'donation_type', $payload['donation_type'] ?? null);
                $setIf($dado, 'recurring', (int) ($payload['recurring'] ?? 0));

                $setIf($dado, 'paypal_order_id', $orderId);
                $setIf($dado, 'paypal_capture_id', $captureId ?: null);
                $setIf($dado, 'paypal_transaction_id', $captureId ?: null);

                $setIf($dado, 'ip', $payload['ip'] ?? null);
                $setIf($dado, 'client_user_agent', $payload['client_user_agent'] ?? null);

                $dado->save();

                $dbCountry = strtoupper(trim((string) ($dado->_country ?? '')));
                if ($dbCountry !== '' && $dbCountry !== 'XX') $country = $dbCountry;

                $region = (string) ($dado->state ?? $dado->region ?? '');
                $city   = (string) ($dado->city ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao buscar/atualizar BD (PayPal webhook)', [
                'error' => $e->getMessage(),
                'external_id' => $payload['external_id'] ?? null,
                'order_id' => $orderId,
                'capture_id' => $captureId ?: null,
            ]);
        }

        if (!$dado) {
            Log::warning('PayPal webhook - registro nao encontrado no BD, ignorando CAPI/UTM', [
                'external_id' => $payload['external_id'] ?? null,
                'order_id' => $orderId ?: null,
                'capture_id' => $captureId ?: null,
            ]);

            return response()->json(['ok' => true], 200);
        }

        if ($payload['status'] !== 'paid' || (float) ($payload['amount'] ?? 0) < 1) {
            Log::info('PayPal webhook - not paid or invalid amount', [
                'status' => $payload['status'],
                'amount' => $payload['amount'] ?? null,
                'external_id' => $payload['external_id'] ?? null,
            ]);

            return response()->json(['ok' => true], 200);
        }

        Log::info('PayPal webhook - PAID payload (normalized)', $payload);

        $utmPaymentMethod = 'paypal';

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

            Log::warning('utm_campaign sem B1S/B2S — fallback targets=both (PayPal)', [
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'order_id'     => $orderId,
                'email'        => $payload['email'] ?? null,
            ]);
        }

        // foreach ($targets as $label => $serviceKey) {
        //     $pixelId  = config("services.{$serviceKey}.pixel_id");
        //     $apiToken = config("services.{$serviceKey}.access_token");

        //     if (!$pixelId || !$apiToken) {
        //         Log::warning("CAPI creds missing ({$label})", [
        //             'service'  => $serviceKey,
        //             'pixelId'  => $pixelId,
        //             'hasToken' => !empty($apiToken),
        //         ]);
        //         continue;
        //     }

        //     $capiPayload = [
        //         'data' => $dataEvents,
        //         'access_token' => $apiToken,
        //     ];

        //     Log::info("CAPI Payload (PayPal webhook paid) - {$label}", [
        //         'service'  => $serviceKey,
        //         'pixel_id' => $pixelId,
        //         'payload'  => $capiPayload,
        //     ]);

        //     $r = Http::withHeaders(['Content-Type' => 'application/json'])
        //         ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

        //     Log::info("Facebook CAPI response (PayPal webhook paid) - {$label}", [
        //         'service'  => $serviceKey,
        //         'pixel_id' => $pixelId,
        //         'status'   => $r->status(),
        //         'body'     => $r->body(),
        //     ]);
        // }

        $utmPayload = [
            'orderId' => 'pp_' . $orderId,
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

        // if ($utmUrl && $utmKey) {
        //     $r = Http::withHeaders([
        //         'Content-Type' => 'application/json',
        //         'x-api-token'  => $utmKey,
        //     ])->post($utmUrl, $utmPayload);

        //     Log::info('Utmify Payload (PayPal webhook paid) enviado:', $utmPayload);
        //     Log::info("Utmify response (PayPal webhook paid)", [
        //         'status' => $r->status(),
        //         'body'   => $r->body(),
        //     ]);
        // } else {
        //     Log::warning('UTMFY_URL ou UTMFY_API_KEY não configurados', [
        //         'utmUrl' => $utmUrl,
        //         'utmKey' => !empty($utmKey),
        //     ]);
        // }

        $toEmail = (string) ($payload['email'] ?? '');
        $isValidEmail = filter_var($toEmail, FILTER_VALIDATE_EMAIL);

        if (!$isValidEmail) {
            Log::warning('PayPal webhook - invalid email, skipping receipt', [
                'external_id' => $payload['external_id'] ?? null,
                'email' => $toEmail ?: null,
                'order_id' => $orderId ?: null,
                'capture_id' => $captureId ?: null,
            ]);

            return response()->json(['ok' => true], 200);
        }

        $alreadySent = EmailMessage::where('external_id', (string) $payload['external_id'])
            ->where('to_email', $toEmail)
            ->exists();

        if ($alreadySent) {
            Log::info('PayPal webhook - email already sent', [
                'external_id' => $payload['external_id'] ?? null,
                'to' => $toEmail,
            ]);

            return response()->json(['ok' => true], 200);
        }

        $token = Str::random(64);
        $links = [
            'site' => 'https://susanpetrescue.org/',
            'facebook' => 'https://www.facebook.com/susanpetrescue',
            'instagram' => 'https://www.instagram.com/susanpetrescue',
            'contact' => 'https://susanpetrescue.org/about-us',
        ];

        EmailMessage::create([
            'token' => $token,
            'external_id' => (string) $payload['external_id'],
            'to_email' => $toEmail,
            'subject' => 'Thank you for your donation!',
            'sent_at' => now(),
            'links' => $links,
        ]);

        $amountLabel = ($payload['amount_formatted'] ?? '0.00');
        $moneySymbol = ['USD' => '$', 'BRL' => 'R$'][strtoupper($payload['currency'] ?? 'USD')] ?? '$';

        $emailData = [
            'subject' => 'Thank you for your donation!',
            'human_now' => now()->format('M d, Y H:i'),
            'payer_name' => $payload['payer_name'] ?? '',
            'email' => $toEmail,
            'amount_label' => $moneySymbol . $amountLabel,
            'external_id' => (string) $payload['external_id'],
            'donation_id' => $orderId !== '' ? $orderId : (string) $payload['external_id'],
            'donated_at' => $payload['event_time'] ? date('M d, Y H:i', (int) $payload['event_time']) : now()->format('M d, Y H:i'),
            'method' => $payload['method'] ?? 'paypal',
            'track_token' => $token,
        ];

        SendDonationPaidEmail::dispatch($toEmail, $emailData);

        Log::info('PayPal webhook - email queued', [
            'to' => $toEmail,
            'external_id' => (string) $payload['external_id'],
            'order_id' => $orderId ?: null,
            'capture_id' => $captureId ?: null,
        ]);

        foreach ($cacheKeys as $key) {
            if ($key) {
                Cache::forget($key);
            }
        }

        return response()->json([
            'ok' => true,
            'order_id' => $orderId,
            'capture_id' => $captureId ?: null,
            'external_id' => $payload['external_id'],
        ], 200);
    }
}