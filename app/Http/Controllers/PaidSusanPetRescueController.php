<?php

namespace App\Http\Controllers;

use App\Jobs\SendDonationPaidEmail;
use Illuminate\Http\Request;
use App\Models\DadosSusanPetRescue;
use App\Models\EmailMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PaidSusanPetRescueController extends Controller
{
    public function paid(Request $request)
    {
        $data = $request->all();
        Log::info("GiveWP PAID recebido", $data);

        // =========================
        // 1) Extrai do payload do GiveWP Webhook
        // =========================
        $eventId  = strtolower((string) data_get($data, 'event.id', ''));
        $donation = (array) data_get($data, 'data.donation', []);

        $donationIdFromWebhook = (int) data_get($donation, 'id', 0);

        $statusFromWebhookRaw = strtolower((string) data_get($donation, 'status', ''));
        $isPaidByWebhook = in_array($statusFromWebhookRaw, ['publish', 'complete', 'completed', 'paid'], true);

        $whFirstName = (string) data_get($donation, 'firstName', '');
        $whLastName  = (string) data_get($donation, 'lastName', '');
        $whEmail     = (string) data_get($donation, 'email', '');
        $whAmount    = (float)  data_get($donation, 'amount', 0);
        $whCurrency  = strtoupper((string) data_get($donation, 'currency', 'USD'));

        // createdAt: "29/12/2025 14:40"
        $whCreatedAt = (string) data_get($donation, 'createdAt', '');
        $whCreatedTs = 0;
        if ($whCreatedAt !== '') {
            $dt = \DateTime::createFromFormat('d/m/Y H:i', $whCreatedAt);
            if ($dt instanceof \DateTime) $whCreatedTs = $dt->getTimestamp();
            if (!$whCreatedTs) {
                $tmp = strtotime($whCreatedAt);
                if ($tmp) $whCreatedTs = $tmp;
            }
        }

        // amount_cents (webhook não manda) -> calcula
        $whAmountCents = (int) round($whAmount * 100);

        // =========================
        // 2) Se ainda não é paid, não dispara
        // =========================
        if (!$isPaidByWebhook) {
            Log::info('GiveWP recebido mas ainda não é paid — ignorando', [
                'event'       => $eventId ?: null,
                'status'      => $statusFromWebhookRaw ?: null,
                'donation_id' => $donationIdFromWebhook ?: null,
                'email'       => $whEmail ?: null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Webhook recebido (ainda não paid).',
            ], 200);
        }

        // =========================
        // 3) Idempotência (evita reprocessar retries do GiveWP)
        // =========================
        $dedupeBase = $donationIdFromWebhook > 0
            ? ('givewp:' . $donationIdFromWebhook)
            : ('givewp:' . $whEmail . '|' . $whCreatedTs . '|' . $whAmountCents . '|' . $whCurrency);

        $dedupeKey = 'givewp_paid:' . hash('sha256', (string) $dedupeBase);

        if (!Cache::add($dedupeKey, 1, now()->addHours(24))) {
            Log::info('GiveWP paid DUPLICADO (idempotência) — ignorando reprocessamento', [
                'dedupe_key'  => $dedupeKey,
                'donation_id' => $donationIdFromWebhook ?: null,
                'email'       => $whEmail ?: null,
                'amount_cents' => $whAmountCents ?: null,
                'created_ts'  => $whCreatedTs ?: null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'duplicate webhook ignored',
            ], 200);
        }

        // =========================
        // 4) Normalizador de nome p/ comparar "AnnKathrine" vs "Ann-Kathrine"
        // =========================
        $normName = function (?string $s): string {
            $s = (string) $s;
            $s = trim($s);
            if ($s === '') return '';

            $s = \Illuminate\Support\Str::ascii($s);
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^a-z0-9]+/i', '', $s);

            return $s ?: '';
        };

        // =========================
        // 5) BUSCA NO BD (SEM external_id)
        // 1) email + amount_cents (preferência)
        // 2) fallback: first_name nos 200 mais recentes
        // =========================
        $dado = null;

        try {
            // (1) email + amount_cents
            if ($whEmail !== '' && $whAmountCents > 0) {
                $cands = DadosSusanPetRescue::query()
                    ->where('email', (string) $whEmail)
                    ->where(function ($qq) {
                        $qq->whereNull('status')->orWhere('status', '!=', 'paid');
                    })
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get();

                foreach ($cands as $row) {
                    $rowCents = (int)($row->amount_cents ?? 0);
                    if ($rowCents <= 0 && isset($row->amount)) {
                        $rowCents = (int) round(((float)$row->amount) * 100);
                    }

                    if ($rowCents !== $whAmountCents) continue;

                    // opcional (seguro): se tiver createdAt, garante janela de 6h
                    if ($whCreatedTs > 0 && !empty($row->created_at)) {
                        $rowTs = strtotime((string)$row->created_at);
                        if ($rowTs && abs($rowTs - $whCreatedTs) > (6 * 3600)) {
                            continue;
                        }
                    }

                    // opcional: se tiver nome, tenta bater normalizado
                    if ($whFirstName !== '') {
                        if ($normName((string)($row->first_name ?? '')) !== $normName($whFirstName)) {
                            continue;
                        }
                    }

                    $dado = $row;
                    break;
                }
            }

            // (2) fallback: first_name nos 200 mais recentes
            if (!$dado && $whFirstName !== '') {
                $recent = DadosSusanPetRescue::orderByDesc('id')->limit(200)->get();

                foreach ($recent as $row) {
                    if ($normName((string)($row->first_name ?? '')) === $normName($whFirstName)) {
                        $dado = $row;
                        break;
                    }
                }
            }

            Log::info('BD match GiveWP (sem external_id)', [
                'email'        => $whEmail ?: null,
                'amount_cents'  => $whAmountCents ?: null,
                'first_name'    => $whFirstName ?: null,
                'found'         => (bool) $dado,
                'matched_id'    => $dado->id ?? null,
                'give_payment_id_before' => $dado->give_payment_id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao buscar BD GiveWP (sem external_id)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Se não achou registro, NÃO retorne 404 (para não ficar tentando 5x no GiveWP)
        if (!$dado) {
            Log::warning('GiveWP paid: registro não encontrado no BD (sem external_id)', [
                'donation_id' => $donationIdFromWebhook ?: null,
                'email'       => $whEmail ?: null,
                'amount'      => $whAmount ?: null,
                'amount_cents' => $whAmountCents ?: null,
                'firstName'   => $whFirstName ?: null,
                'status'      => $statusFromWebhookRaw ?: null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'paid recebido, mas registro não encontrado no BD (sem external_id).',
            ], 200);
        }

        // =========================
        // 6) Atualiza BD (paid + amarra give_payment_id)
        // =========================
        $dado->status = 'paid';

        if ($donationIdFromWebhook > 0) {
            $dado->give_payment_id = (int) $donationIdFromWebhook;
        }

        // completa currency/amount/amount_cents (sem apagar se você preferir manter)
        if (!empty($whCurrency)) $dado->currency = (string) $whCurrency;
        if ($whAmount > 0) $dado->amount = (float) $whAmount;
        if ($whAmountCents > 0) $dado->amount_cents = (int) $whAmountCents;

        // nomes/email só completa se vier e/ou se estiver vazio
        if (!empty($whFirstName)) $dado->first_name = (string) $whFirstName;
        if (!empty($whLastName))  $dado->last_name  = (string) $whLastName;
        if (!empty($whEmail))     $dado->email      = (string) $whEmail;

        // event_time: se não tiver, usa createdAt do webhook
        if (empty($dado->event_time)) {
            $dado->event_time = $whCreatedTs > 0 ? (int) $whCreatedTs : time();
        }

        $dado->save();

        // =========================
        // 7) GEO: IGNORAR payload e usar apenas BD
        // =========================
        $country = strtoupper((string)($dado->_country ?? ''));
        if ($country === '' || $country === 'XX') $country = 'US';

        // se quiser manter region/city do BD para utmify:
        $region = (string)($dado->_region_code ?? ($dado->_region ?? ''));
        $city   = (string)($dado->_city ?? '');

        // =========================
        // 8) Helpers produto/label (com dados do BD)
        // =========================
        $currency = strtoupper((string)($dado->currency ?: 'USD'));

        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol      = $symbolByCurrency[$currency] ?? '$';

        $amount = (float) ($dado->amount ?? 0);
        $amountFormatted = number_format($amount, 2, '.', '');

        $amountCents = (int)($dado->amount_cents ?? 0);
        if ($amountCents <= 0 && $amount > 0) $amountCents = (int) round($amount * 100);

        $methodLower = strtolower((string)($dado->method ?? ''));
        $isRecurring = ($methodLower === 'paypal recurring') || str_contains($methodLower, 'recurring');

        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isRecurring ? " R" : "");

        // =========================
        // 9) Payload base (USANDO O BD)
        // =========================
        $payload = [
            'status'            => $dado->status,
            'amount'            => $amount,
            'amount_cents'      => $amountCents,
            'currency'          => $currency,

            'first_name'        => $dado->first_name,
            'last_name'         => $dado->last_name,

            'payer_name'        => trim((string)($dado->first_name ?? '') . ' ' . (string)($dado->last_name ?? '')),
            'payer_document'    => $dado->cpf ?? '',
            'confirmed_at'      => $dado->event_time ? date('c', (int)$dado->event_time) : now()->toIso8601String(),

            'email'             => $dado->email,
            'phone'             => $dado->phone,
            'cpf'               => $dado->cpf,
            'ip'                => $dado->ip ?? $request->ip(),
            'method'            => $dado->method,
            'event_time'        => (int)($dado->event_time ?? time()),
            'page_url'          => $dado->page_url ?: 'https://susanpetrescue.org/',
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

            'product_label'     => $productLabel,
            'amount_formatted'  => $amountFormatted,
        ];

        $capiPayload = null;
        $utmPayload  = null;

        // =========================
        // 10) CAPI — Purchase (somente)
        // =========================
        if ($payload['status'] === 'paid' && (float)$payload['amount'] >= 1) {

            $normalize = fn($str) => strtolower(trim((string) $str));

            $hashedEmail = $payload['email'] ? hash('sha256', $normalize($payload['email'])) : null;

            $cleanPhone  = $payload['phone'] ? preg_replace('/\D+/', '', (string) $payload['phone']) : null;
            $hashedPhone = $cleanPhone ? hash('sha256', $cleanPhone) : null;

            // Facebook external_id (do Facebook) -> hash(email+phone), NÃO é o seu external_id
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

            // event_id determinístico (sem usar external_id)
            $baseId = ($donationIdFromWebhook > 0 ? (string)$donationIdFromWebhook : '')
                . '|' . (string)($payload['email'] ?? '')
                . '|' . (string)($payload['event_time'] ?? time())
                . '|' . (string)($payload['amount_cents'] ?? '');

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

            $targets = [
                'b1s' => 'facebook_capi_susan_pet_rescue_b1s',
                'b2s' => 'facebook_capi_susan_pet_rescue_b2s',
            ];

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

                Log::info("CAPI Payload (GiveWP paid) - {$label}", [
                    'service'  => $serviceKey,
                    'pixel_id' => $pixelId,
                    'payload'  => $capiPayload,
                ]);

                $res = \Illuminate\Support\Facades\Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

                Log::info("Facebook CAPI response (GiveWP paid) - {$label}", [
                    'service'  => $serviceKey,
                    'pixel_id' => $pixelId,
                    'status'   => $res->status(),
                    'body'     => $res->body(),
                ]);
            }
        }

        // =========================
        // 11) UTMIFY — paid (geo do BD)
        // =========================
        // paymentMethod baseado no method salvo
        $utmPaymentMethod = 'paypal';
        if (str_contains($methodLower, 'pix')) $utmPaymentMethod = 'pix';
        elseif (str_contains($methodLower, 'boleto')) $utmPaymentMethod = 'boleto';
        elseif (str_contains($methodLower, 'paypal')) $utmPaymentMethod = 'paypal';

        if ($payload['status'] === 'paid') {

            $utmPayload = [
                'orderId' => 'ord_' . substr(bin2hex(random_bytes(4)), 0, 8),
                'platform' => 'Checkout',
                'paymentMethod' => $utmPaymentMethod,
                'status' => 'paid',
                'createdAt' => $payload['event_time'] ? date('c', (int)$payload['event_time']) : now()->toIso8601String(),
                'approvedDate' => $payload['confirmed_at'],
                'refundedAt' => null,

                'customer' => [
                    'name'     => $payload['payer_name'] ?? '',
                    'email'    => $payload['email'] ?? '',
                    'phone'    => $payload['phone'] ?? '',
                    'document' => $payload['payer_document'] ?? '',
                    'country'  => $country,
                    'ip'       => $payload['ip'] ?? ''
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
                    'city'         => $city
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
                $res = \Illuminate\Support\Facades\Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-token'  => $utmKey,
                ])->post($utmUrl, $utmPayload);

                Log::info('Utmify Payload (GiveWP paid) enviado:', $utmPayload);
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

        // =========================
        // 6) EMAIL (fila) — disparo com HTML da view emails.donation_paid_html
        // =========================
        // try {
        //     if (($payload['status'] ?? null) === 'paid') {

        //         $toEmail = (string) ($payload['email'] ?? '');
        //         $isValidEmail = filter_var($toEmail, FILTER_VALIDATE_EMAIL);

        //         if ($isValidEmail) {

        //             // Datas
        //             $donatedAtHuman = '';
        //             if (!empty($payload['event_time'])) {
        //                 $donatedAtHuman = date('M d, Y H:i', (int) $payload['event_time']);
        //             } else {
        //                 $donatedAtHuman = now()->format('M d, Y H:i');
        //             }

        //             // ID doação (usa payment_id do GiveWP se existir, senão external_id)
        //             $donationId = '';
        //             if (!empty($dado->give_payment_id)) {
        //                 $donationId = (string) $dado->give_payment_id;
        //             } else {
        //                 $donationId = (string) $externalId;
        //             }

        //             // amount_label no formato que o HTML usa (ex: "$30.00" / "R$100.00")
        //             $amountLabel = "{$moneySymbol}{$amountFormatted}";

        //             // payer name
        //             $payerName = trim((string)($payload['payer_name'] ?? ''));
        //             if ($payerName === '') {
        //                 $payerName = trim((string)($payload['first_name'] ?? '') . ' ' . (string)($payload['last_name'] ?? ''));
        //             }
        //             if ($payerName === '') {
        //                 $payerName = 'friend';
        //             }

        //             // (Opcional) Mensagem dinâmica — se não tiver, pode ficar vazio
        //             $dynamicMessage = (string) ($payload['dynamic_message'] ?? '');

        //             $emailData = [
        //                 'subject'         => 'Thank you for your donation! ❤️',
        //                 'human_now'       => now()->format('M d, Y H:i'),

        //                 'payer_name'      => $payerName,
        //                 'email'           => $toEmail,
        //                 'amount_label'    => $amountLabel,

        //                 'external_id'     => (string) $externalId,

        //                 'donation_id'     => $donationId,
        //                 'donated_at'      => $donatedAtHuman,
        //                 'method'          => (string) ($payload['method'] ?? 'Paypal'),

        //                 'dynamic_message' => $dynamicMessage,
        //             ];

        //             $alreadySent = EmailMessage::where('external_id', (string)$externalId)
        //                 ->where('to_email', $toEmail)
        //                 ->exists();

        //             if ($alreadySent) {
        //                 Log::info('DonationPaidMail skipped (already sent)', [
        //                     'external_id' => (string)$externalId,
        //                     'to' => $toEmail,
        //                 ]);
        //             } else {
        //                 $token = Str::random(64);

        //                 $links = [
        //                     'site'     => 'https://susanpetrescue.org/',
        //                     'facebook' => 'https://www.facebook.com/susanpetrescue',
        //                     'instagram' => 'https://www.instagram.com/susanpetrescue',
        //                     // ⚠️ mailto não dá pra trackear via redirect. Use uma página de contato:
        //                     'contact'  => 'https://susanpetrescue.org/about-us',
        //                 ];

        //                 EmailMessage::create([
        //                     'token'      => $token,
        //                     'external_id' => (string)$externalId,
        //                     'to_email'   => $toEmail,
        //                     'subject'    => 'Thank you for your donation! ❤️',
        //                     'sent_at'    => now(),
        //                     'links'      => $links
        //                 ]);

        //                 $emailData['track_token'] = $token;

        //                 // Dispara na fila (recomendado)
        //                 SendDonationPaidEmail::dispatch($toEmail, $emailData);
        //             }

        //             Log::info('DonationPaidMail queued', [
        //                 'to' => $toEmail,
        //                 'external_id' => (string) $externalId,
        //                 'donation_id' => $donationId,
        //             ]);
        //         } else {
        //             Log::warning('DonationPaidMail skipped (invalid email)', [
        //                 'external_id' => (string) $externalId,
        //                 'email' => $toEmail,
        //             ]);
        //         }
        //     }
        // } catch (\Throwable $e) {
        //     // não quebra o webhook se email falhar
        //     Log::error('DonationPaidMail error (ignored)', [
        //         'external_id' => (string) $externalId,
        //         'error' => $e->getMessage(),
        //     ]);
        // }

        return response()->json([
            'ok' => true,
            'message' => 'GiveWP paid processado com sucesso (sem external_id no match)',
            'matched' => [
                'id' => $dado->id ?? null,
                'give_payment_id' => $dado->give_payment_id ?? null,
            ],
            'received' => [
                'capi' => $capiPayload,
                'utm'  => $utmPayload,
            ]
        ], 200);
    }

    public function paidDonor(Request $request)
    {
        $data = $request->all();
        Log::info("Donor recebido", $data);

        // =========================
        // 1) Extrai campos do payload REAL do Donor (sem BD)
        // =========================
        $donationId       = (string) data_get($data, 'donation.id', '');
        $statusRaw        = strtolower((string) data_get($data, 'donation.status', ''));
        $donationType     = strtolower((string) data_get($data, 'donation.donation_type', '')); // ex: paypal_express
        $currency         = strtoupper((string) data_get($data, 'donation.currency', 'USD'));

        $amount           = (float) data_get($data, 'donation.amount', 0);
        $amountCents      = (int) data_get($data, 'donation.amount_cents', 0);
        if ($amountCents <= 0 && $amount > 0) $amountCents = (int) round($amount * 100);

        $isRecurring      = (bool) data_get($data, 'donation.recurring', false);

        $firstName        = (string) data_get($data, 'donation.first_name', data_get($data, 'donation.donor.first_name', ''));
        $lastName         = (string) data_get($data, 'donation.last_name',  data_get($data, 'donation.donor.last_name', ''));
        $email            = (string) data_get($data, 'donation.email',      data_get($data, 'donation.donor.email', ''));
        $phone            = (string) data_get($data, 'donation.phone',      data_get($data, 'donation.donor.phone', ''));

        $payerName        = trim($firstName . ' ' . $lastName);

        // UTM
        $utmSource        = (string) data_get($data, 'donation.utm_source', '');
        $utmCampaign      = (string) data_get($data, 'donation.utm_campaign', '');
        $utmMedium        = (string) data_get($data, 'donation.utm_medium', '');
        $utmContent       = (string) data_get($data, 'donation.utm_content', '');
        $utmTerm          = (string) data_get($data, 'donation.utm_term', '');

        // Datas (ISO -> timestamp)
        $donationDateIso  = (string) data_get($data, 'donation.donation_date', '');
        $eventTime        = $donationDateIso ? strtotime($donationDateIso) : time();
        if (!$eventTime) $eventTime = time();

        // Page URL fallback (donor não manda page_url)
        $pageUrl = (string) data_get($data, 'donation.org.website', 'https://susanpetrescue.org/');

        // PayPal refs do Donor
        $paypalCaptureId    = (string) data_get($data, 'donation.paypal_capture_id', '');
        $paypalTransaction  = (string) data_get($data, 'donation.paypal_transaction_id', '');

        Log::info('PayPal refs recebidas do Donor', [
            'donation_type'         => $donationType ?: null,
            'paypal_capture_id'     => $paypalCaptureId ?: null,
            'paypal_transaction_id' => $paypalTransaction ?: null,
            'donation_id'           => $donationId ?: null,
            'status'                => $statusRaw ?: null,
        ]);

        // =========================
        // 2) Geo (Donor geralmente vem null) + fallbacks
        // =========================
        $country = strtoupper((string) data_get($data, 'donation.country', ''));
        if ($country === '' || $country === 'XX') {
            $country = strtoupper((string) data_get($data, 'donation.org.operating_country_code', ''));
        }
        if ($country === '' || $country === 'XX') $country = 'US';

        $region = (string) data_get($data, 'donation.state', '');
        $city   = (string) data_get($data, 'donation.city', '');

        // =========================
        // 3) Helpers produto/label
        // =========================
        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol      = $symbolByCurrency[$currency] ?? '$';
        $amountFormatted  = number_format($amount, 2, '.', '');
        $productLabel     = "SPR {$moneySymbol}{$amountFormatted}" . ($isRecurring ? " R" : "");

        // =========================
        // 4) Payload base padronizado (sem BD)
        // =========================
        $payload = [
            'external_id'        => $donationId !== '' ? $donationId : ('donor_' . substr(bin2hex(random_bytes(8)), 0, 16)),
            'status'             => $statusRaw ?: 'paid',

            'amount'             => $amount,
            'amount_cents'       => $amountCents,
            'currency'           => $currency,

            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'payer_name'         => $payerName,
            'payer_document'     => '',

            'email'              => $email,
            'phone'              => $phone,
            'ip'                 => $request->ip(),
            'client_user_agent'  => $request->userAgent(),

            // Donor não manda fbp/fbc/fbclid normalmente
            'fbp'                => null,
            'fbc'                => null,
            'fbclid'             => null,

            'utm_source'         => $utmSource,
            'utm_campaign'       => $utmCampaign,
            'utm_medium'         => $utmMedium,
            'utm_content'        => $utmContent,
            'utm_term'           => $utmTerm,

            'event_time'         => (int) $eventTime,
            'confirmed_at'       => date('c', (int) $eventTime),
            'page_url'           => $pageUrl,

            'product_label'      => $productLabel,
            'amount_formatted'   => $amountFormatted,

            'donation_type'      => $donationType ?: null,
            'recurring'          => $isRecurring,

            'paypal_capture_id'  => $paypalCaptureId ?: null,
            'paypal_transaction_id' => $paypalTransaction ?: null,
        ];

        // Só dispara se for paid mesmo
        if ($payload['status'] !== 'paid' || (float)$payload['amount'] < 1) {
            Log::info('Donor recebido mas não é paid/valor inválido — não enviando CAPI/UTM', [
                'status' => $payload['status'],
                'amount' => $payload['amount'],
                'external_id' => $payload['external_id'],
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Donor recebido (não-paid ou valor inválido).',
            ], 200);
        }

        // ==========================================================
        // ✅ [NOVO] (2) IDEMPOTÊNCIA / DEDUPE DO WEBHOOK "paid"
        // - se o webhook repetir, NÃO reprocessa (BD/CAPI/UTMify)
        // - trava por 24h
        // ==========================================================
        $dedupeBase = $donationId ?: (
            ((string)($payload['email'] ?? '')) . '|' .
            ((string)($payload['event_time'] ?? '')) . '|' .
            ((string)($payload['amount_cents'] ?? '')) . '|' .
            ((string)($payload['currency'] ?? ''))
        );

        $dedupeKey = 'donor_paid:' . hash('sha256', (string) $dedupeBase);

        if (!Cache::add($dedupeKey, 1, now()->addHours(24))) {
            Log::info('Donor paid DUPLICADO (idempotência) — ignorando reprocessamento', [
                'dedupe_key'  => $dedupeKey,
                'donation_id' => $donationId ?: null,
                'email'       => $payload['email'] ?? null,
                'amount_cents' => $payload['amount_cents'] ?? null,
                'event_time'  => $payload['event_time'] ?? null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'duplicate webhook ignored',
            ], 200);
        }

        // ==========================================================
        // ✅ [NOVO] normalizador de nome p/ comparar "AnnKathrine" vs "Ann-Kathrine"
        // ==========================================================
        $normName = function (?string $s): string {
            $s = (string) $s;
            $s = trim($s);
            if ($s === '') return '';

            $s = Str::ascii($s);                 // remove acentos
            $s = mb_strtolower($s, 'UTF-8');     // lowercase
            $s = preg_replace('/[^a-z0-9]+/i', '', $s); // remove hífen/espaço/pontuação

            return $s ?: '';
        };

        // ==========================================================
        // ✅ AJUSTE PEDIDO: BUSCAR NO BD PELO first_name (NOS 200 MAIS RECENTES)
        // ==========================================================
        $dado = null;
        try {
            $firstNameToFind = trim((string) $firstName);

            if ($firstNameToFind !== '') {
                $recent = DadosSusanPetRescue::orderByDesc('id')->limit(200)->get();

                foreach ($recent as $row) {
                    if ($normName((string)($row->first_name ?? '')) === $normName($firstNameToFind)) {
                        $dado = $row;
                        break; // primeiro match dentro dos mais recentes
                    }
                }
            }

            Log::info('BD match por first_name (200 mais recentes)', [
                'first_name' => $firstName ?: null,
                'found' => (bool) $dado,
                'matched_id' => $dado->id ?? null,
                'matched_external_id' => $dado->external_id ?? null,
            ]);

            if ($dado) {
                // 3) atualiza para paid (mesma lógica da função original)
                $dado->status = 'paid';

                // mantém o que já existe e só completa com o donor
                if (!empty($currency))        $dado->currency = (string) $currency;
                if (isset($amount))           $dado->amount = (float) $amount;
                if (isset($amountCents))      $dado->amount_cents = (int) $amountCents;

                // completa dados pessoais se vierem do donor
                if (!empty($firstName))       $dado->first_name = (string) $firstName;
                if (!empty($lastName))        $dado->last_name  = (string) $lastName;
                if (!empty($email))           $dado->email      = (string) $email;
                if (!empty($phone))           $dado->phone      = (string) $phone;

                // event_time: usa o do donor se vier válido
                if (!empty($eventTime))       $dado->event_time = (int) $eventTime;

                // UTM: completa se vier (não apaga o que existe)
                if (!empty($utmSource))       $dado->utm_source   = (string) $utmSource;
                if (!empty($utmCampaign))     $dado->utm_campaign = (string) $utmCampaign;
                if (!empty($utmMedium))       $dado->utm_medium   = (string) $utmMedium;
                if (!empty($utmContent))      $dado->utm_content  = (string) $utmContent;
                if (!empty($utmTerm))         $dado->utm_term     = (string) $utmTerm;

                // método do donor (opcional)
                // if (!empty($donationType))    $dado->method = (string) $donationType;

                if (!empty($donationType)) {
                    $dt = strtolower(trim((string) $donationType));

                    if (str_contains($dt, 'paypal')) {
                        // paypal_express / paypal / paypal_*  => salva "paypal"
                        // se for recorrente => salva "paypal recurring" (mesma lógica do seu padrão)
                        $dado->method = $isRecurring ? 'paypal recurring' : 'paypal';
                    } else {
                        // mantém o tipo original quando não é paypal
                        $dado->method = (string) $donationType;
                    }
                }

                $dado->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao buscar/atualizar BD por first_name (200 mais recentes)', [
                'error' => $e->getMessage(),
            ]);
        }

        // =========================
        // 5) ✅ UTMIFY paymentMethod (SEM FUNÇÕES PAYPAL)
        //    utmify aceita: 'credit_card' | 'boleto' | 'pix' | 'paypal' | 'free_price'
        // =========================
        $dtRaw = strtolower(trim((string) $donationType));

        // normaliza: "credit card" -> "credit_card", "apple-pay" -> "apple_pay"
        $dt = str_replace([' ', '-'], '_', $dtRaw);

        if (((int)$amountCents <= 0) || ((float)$amount <= 0)) {
            $utmPaymentMethod = 'free_price';
        } elseif ($dt === 'paypal' || $dt === 'paypal_express' || str_contains($dt, 'paypal')) {
            $utmPaymentMethod = 'paypal';
        } elseif (
            in_array($dt, ['stripe', 'credit_card', 'card', 'debit_card', 'apple_pay', 'google_pay', 'ideal', 'sepa'], true)
            || str_contains($dt, 'stripe')
            || str_contains($dt, 'card')
            || str_contains($dt, 'apple_pay')
            || str_contains($dt, 'google_pay')
        ) {
            $utmPaymentMethod = 'credit_card';
        } elseif ($dt === 'pix' || str_contains($dt, 'pix')) {
            $utmPaymentMethod = 'pix';
        } elseif ($dt === 'boleto' || str_contains($dt, 'boleto')) {
            $utmPaymentMethod = 'boleto';
        } else {
            $utmPaymentMethod = 'credit_card';
        }

        // =========================
        // 6) FACEBOOK CAPI — Purchase (somente)
        // =========================
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

            // fbc/fbp null (donor não envia) — ok
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
        if ($baseId === '') $baseId = (string)($payload['email'] ?? '') . '|' . (string)($payload['event_time'] ?? time());

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

        $targets = [
            'b1s' => 'facebook_capi_susan_pet_rescue_b1s',
            'b2s' => 'facebook_capi_susan_pet_rescue_b2s',
        ];

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

            Log::info("CAPI Payload (Donor paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'payload'  => $capiPayload,
            ]);

            $res = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

            Log::info("Facebook CAPI response (Donor paid) - {$label}", [
                'service'  => $serviceKey,
                'pixel_id' => $pixelId,
                'status'   => $res->status(),
                'body'     => $res->body(),
            ]);
        }

        // =========================
        // 7) UTMIFY — paid
        // =========================
        $utmPayload = [
            'orderId' => 'ord_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'platform' => 'Checkout',
            'paymentMethod' => $utmPaymentMethod,
            'status' => 'paid',
            'createdAt' => $payload['event_time'] ? date('c', (int)$payload['event_time']) : now()->toIso8601String(),
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
            $res = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-token'  => $utmKey,
            ])->post($utmUrl, $utmPayload);

            Log::info('Utmify Payload (Donor paid) enviado:', $utmPayload);
            Log::info("Utmify response (Donor paid)", [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);
        } else {
            Log::warning('UTMFY_URL ou UTMFY_API_KEY não configurados', [
                'utmUrl' => $utmUrl,
                'utmKey' => !empty($utmKey),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Donor paid processado com sucesso (sem BD)',
            'received' => [
                'capi' => $capiPayload,
                'utm'  => $utmPayload,
            ]
        ], 200);
    }
}
