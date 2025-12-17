<?php

namespace App\Http\Controllers;
use App\Models\DadosSusanPetRescue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use Illuminate\Http\Request;

class BackfillSusanPetRescuePaidController extends Controller
{
    /**
     * POST /api/spr/backfill/capi/test-first?cred=alt
     * Envia apenas o 1º registro de HOJE (paid + updated_at hoje)
     */
    public function testFirst(Request $request)
    {
        $this->authorizeBackfill($request);

        $tz = 'America/Sao_Paulo';
        [$start, $end] = $this->todayRange($request, $tz);

        $credKey = $request->get('cred', 'b2s');
        $creds = $this->resolveCreds($credKey);

        $query = DadosSusanPetRescue::query()
            ->where('status', 'paid')
            ->whereBetween('updated_at', [$start, $end])
            ->orderBy('updated_at', 'asc');

        $dado = $query->first();

        if (!$dado) {
            return response()->json([
                'ok' => true,
                'message' => 'Nenhum paid atualizado hoje para enviar.',
                'range' => [$start->toIso8601String(), $end->toIso8601String()],
                'cred' => $credKey,
            ], 200);
        }

        $dryRun = $request->boolean('dry_run', false);

        $result = $this->sendCapiForDado($dado, $creds, $dryRun);

        return response()->json([
            'ok' => true,
            'mode' => 'test-first',
            'cred' => $credKey,
            'dry_run' => $dryRun,
            'range' => [$start->toIso8601String(), $end->toIso8601String()],
            'sent' => $result,
        ], 200);
    }

    /**
     * POST /api/spr/backfill/capi/run?cred=alt&offset=1&limit=200
     * Envia o restante de HOJE (paid + updated_at hoje)
     */
    public function run(Request $request)
    {
        $this->authorizeBackfill($request);

        $tz = 'America/Sao_Paulo';
        [$start, $end] = $this->todayRange($request, $tz);

        $credKey = $request->get('cred', 'b2s');
        $creds = $this->resolveCreds($credKey);

        $offset = (int) $request->get('offset', 1); // por padrão pula o 1º (que você testou)
        $limit  = (int) $request->get('limit', 500);
        $dryRun = $request->boolean('dry_run', false);

        $items = DadosSusanPetRescue::query()
            ->where('status', 'paid')
            ->whereBetween('updated_at', [$start, $end])
            ->orderBy('updated_at', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'ok' => true,
                'message' => 'Nada para enviar (após offset/limit).',
                'cred' => $credKey,
                'dry_run' => $dryRun,
                'offset' => $offset,
                'limit' => $limit,
                'range' => [$start->toIso8601String(), $end->toIso8601String()],
            ], 200);
        }

        $success = 0;
        $fail = 0;
        $details = [];

        foreach ($items as $dado) {
            $res = $this->sendCapiForDado($dado, $creds, $dryRun);

            $details[] = $res;

            if (($res['ok'] ?? false) === true) $success++;
            else $fail++;
        }

        return response()->json([
            'ok' => true,
            'mode' => 'run',
            'cred' => $credKey,
            'dry_run' => $dryRun,
            'offset' => $offset,
            'limit' => $limit,
            'range' => [$start->toIso8601String(), $end->toIso8601String()],
            'count' => $items->count(),
            'success' => $success,
            'fail' => $fail,
            'details' => $details,
        ], 200);
    }

    // =========================================================
    // Internals
    // =========================================================

    private function authorizeBackfill(Request $request): void
    {
        $token = (string) $request->header('X-Backfill-Token');
        if (!$token || $token !== (string) config('services.backfill.secret')) {
            abort(response()->json(['ok' => false, 'message' => 'unauthorized'], 401));
        }
    }

    private function todayRange(Request $request, string $tz): array
    {
        // Opcional: ?date=2025-12-16 para rodar em outro dia específico
        $date = $request->get('date');

        $start = $date
            ? Carbon::parse($date, $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $end = (clone $start)->endOfDay();

        return [$start, $end];
    }

    private function resolveCreds(string $key): array
    {
        $serviceKey = match ($key) {
            'b2s' => 'facebook_capi_susan_pet_rescue_b2s',
            'alt' => 'facebook_capi_susan_pet_rescue_alt',
            'alt2' => 'facebook_capi_susan_pet_rescue_alt2',
            default => 'facebook_capi_susan_pet_rescue'
        };

        return [
            'pixel_id'     => config("services.{$serviceKey}.pixel_id"),
            'access_token' => config("services.{$serviceKey}.access_token"),
            // 'pixel_id'     => "1875181019755728",
            // 'access_token' => "EAAHTUPZBJAmEBQHwTZBtzoNHgjZCJaxGkZCggWR5SKNXly2ojB0o5vZAG63bYBEiLlsAHZCmdNRrj88EopPi4FX0T5fyyTNxM6OCebfViN2oKtJsBHRv4oQSsGNXVValZCYVbA32s3o9CELZC7YYTRnFn9XtaOBdwGIzw2VpqOhPSwHO3L4hVrvY3W2qt4EDvgZDZD",
            'service_key'  => $serviceKey,
        ];
    }

    private function sendCapiForDado(DadosSusanPetRescue $dado, array $creds, bool $dryRun = false): array
    {
        $pixelId  = $creds['pixel_id'] ?? null;
        $apiToken = $creds['access_token'] ?? null;

        if (!$pixelId || !$apiToken) {
            Log::warning('Backfill CAPI: credenciais ausentes', ['creds' => $creds]);
            return [
                'ok' => false,
                'reason' => 'missing_creds',
                'service_key' => $creds['service_key'] ?? null,
                'external_id' => $dado->external_id ?? null,
                'id' => $dado->id ?? null,
            ];
        }

        // ==== replica a lógica do teu payload ====
        $currency = strtoupper($dado->currency ?: 'USD');

        $symbolByCurrency = ['USD' => '$', 'BRL' => 'R$'];
        $moneySymbol = $symbolByCurrency[$currency] ?? '$';

        $amount = (float) ($dado->amount ?? 0);
        $amountFormatted = number_format($amount, 2, '.', '');

        $amountCents = (isset($dado->amount_cents) && (int) $dado->amount_cents > 0)
            ? (int) $dado->amount_cents
            : (int) round($amount * 100);

        $methodLower = strtolower((string)($dado->method ?? ''));
        $modeLower   = strtolower((string)($dado->donation_mode ?? ''));

        $isRecurring = ($methodLower === 'paypal recurring') || ($modeLower === 'month') || ($modeLower === 'monthly');

        $productLabel = "SPR {$moneySymbol}{$amountFormatted}" . ($isRecurring ? " R" : "");

        $payload = [
            'status'            => $dado->status,
            'amount'            => $amount,
            'amount_cents'      => $amountCents,
            'currency'          => $currency,

            'first_name'        => $dado->first_name,
            'last_name'         => $dado->last_name,

            'payer_name'        => trim((string)($dado->first_name ?? '') . ' ' . (string)($dado->last_name ?? '')),
            'payer_document'    => $dado->cpf ?? '',
            'confirmed_at'      => $dado->event_time ? date('c', $dado->event_time) : now()->toIso8601String(),

            'email'             => $dado->email,
            'phone'             => $dado->phone,
            'cpf'               => $dado->cpf,
            'ip'                => $dado->ip ?? request()->ip(),
            'method'            => $dado->method,
            'event_time'        => $dado->event_time ?: ($dado->updated_at?->timestamp ?? time()),
            'page_url'          => $dado->page_url,
            'client_user_agent' => $dado->client_user_agent ?? request()->userAgent(),

            'fbp'               => $dado->fbp ?? null,
            'fbc'               => $dado->fbc ?? null,
            'fbclid'            => $dado->fbclid ?? null,

            'utm_source'        => $dado->utm_source,
            'utm_campaign'      => $dado->utm_campaign,
            'utm_medium'        => $dado->utm_medium,
            'utm_content'       => $dado->utm_content,
            'utm_term'          => $dado->utm_term,

            'product_label'     => $productLabel,
            'amount_formatted'  => $amountFormatted,
        ];

        if ($payload['status'] !== 'paid' || (float) $payload['amount'] < 1) {
            return [
                'ok' => false,
                'reason' => 'not_paid_or_amount_lt_1',
                'external_id' => $dado->external_id ?? null,
                'id' => $dado->id ?? null,
            ];
        }

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

        $generateEventId = fn() => bin2hex(random_bytes(16));

        $eventsToSend = ['Purchase', 'Donate'];
        $dataEvents = [];

        foreach ($eventsToSend as $eventName) {
            $dataEvents[] = [
                'event_name'       => $eventName,
                'event_time'       => (int) ($payload['event_time'] ?? time()),
                'action_source'    => 'website',
                'event_id'         => $generateEventId(),
                'event_source_url' => $payload['page_url'],
                'user_data'        => $userData,
                'custom_data'      => $customData,
            ];
        }

        $capiPayload = [
            'data' => $dataEvents,
            'access_token' => $apiToken,
        ];

        Log::info('Backfill CAPI payload', [
            'service_key' => $creds['service_key'] ?? null,
            'pixel_id' => $pixelId,
            'external_id' => $dado->external_id ?? null,
            'id' => $dado->id ?? null,
            'dry_run' => $dryRun,
        ]);

        if ($dryRun) {
            return [
                'ok' => true,
                'dry_run' => true,
                'service_key' => $creds['service_key'] ?? null,
                'pixel_id' => $pixelId,
                'external_id' => $dado->external_id ?? null,
                'id' => $dado->id ?? null,

                'payload_base' => $payload,
                'user_data'    => $userData,
                'custom_data'  => $customData,
                'capi_payload' => $capiPayload,
                'endpoint'     => "https://graph.facebook.com/v17.0/{$pixelId}/events"
            ];
        }

        $res = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

        Log::info("Backfill CAPI response", [
            'service_key' => $creds['service_key'] ?? null,
            'external_id' => $dado->external_id ?? null,
            'id' => $dado->id ?? null,
            'status' => $res->status(),
            'body' => $res->body(),
        ]);

        return [
            'ok' => $res->successful(),
            'service_key' => $creds['service_key'] ?? null,
            'pixel_id' => $pixelId,
            'external_id' => $dado->external_id ?? null,
            'id' => $dado->id ?? null,
            'http_status' => $res->status(),
            'body' => $res->json(),
        ];
    }
}
