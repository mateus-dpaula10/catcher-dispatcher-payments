<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class LytexController extends Controller
{
    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0, $weight = 10; $i < 9; $i++, $weight--) {
            $sum += ((int) $cpf[$i]) * $weight;
        }
        $rest = $sum % 11;
        $digit1 = $rest < 2 ? 0 : 11 - $rest;

        if ((int) $cpf[9] !== $digit1) {
            return false;
        }

        $sum = 0;
        for ($i = 0, $weight = 11; $i < 10; $i++, $weight--) {
            $sum += ((int) $cpf[$i]) * $weight;
        }
        $rest = $sum % 11;
        $digit2 = $rest < 2 ? 0 : 11 - $rest;

        return (int) $cpf[10] === $digit2;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $cnpj[$i]) * $weights1[$i];
        }
        $rest = $sum % 11;
        $digit1 = $rest < 2 ? 0 : 11 - $rest;

        if ((int) $cnpj[12] !== $digit1) {
            return false;
        }

        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += ((int) $cnpj[$i]) * $weights2[$i];
        }
        $rest = $sum % 11;
        $digit2 = $rest < 2 ? 0 : 11 - $rest;

        return (int) $cnpj[13] === $digit2;
    }

    private function getLytexToken(): string
    {
        $cacheKey = 'lytex.token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $authUrl = config('services.lytex.auth_url');
        $clientId = config('services.lytex.client_id');
        $clientSecret = config('services.lytex.client_secret');

        if (!$authUrl || !$clientId || !$clientSecret) {
            throw new \RuntimeException('Lytex auth config missing');
        }

        $res = Http::timeout(30)
            ->asJson()
            ->post($authUrl, [
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
            ]);

        if ($res->failed()) {
            Log::warning('Lytex token request failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException('Lytex token request failed');
        }

        $json = $res->json();
        $token = $json['accessToken'] ?? $json['access_token'] ?? $json['token'] ?? null;
        if (!is_string($token) || $token === '') {
            Log::warning('Lytex token missing in response', [
                'body' => $res->body(),
            ]);
            throw new \RuntimeException('Lytex token missing');
        }

        $expiresIn = (int) ($json['expires_in'] ?? 1700);
        $ttl = max(300, $expiresIn - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    private function lytexHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getLytexToken(),
        ];
    }

    public function createPixOnce(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_cents' => 'required|integer|min:200',
            'name'         => 'required|string|min:2',
            'email'        => 'required|email',
            'cellphone'    => 'required|string',
            'cpf'          => 'nullable|string',
            'reference_id' => 'nullable|string|max:100',
            'page_url'     => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Missing or invalid fields',
                'details' => $validator->errors()
            ], 422);
        }

        $taxId = preg_replace('/\D+/', '', (string) $request->input('cpf', ''));
        if ($taxId !== '') {
            $isCpf  = strlen($taxId) === 11 && $this->isValidCpf($taxId);
            $isCnpj = strlen($taxId) === 14 && $this->isValidCnpj($taxId);
            if (!$isCpf && !$isCnpj) {
                return response()->json(['error' => 'Invalid payer tax ID'], 422);
            }
        }

        $apiUrl = config('services.lytex.invoice_url');
        if (!$apiUrl) {
            return response()->json([
                'error' => 'Lytex api URL not configured'
            ], 500);
        }

        $name = (string) $request->input('name');
        $email = (string) $request->input('email');
        $cell = $this->normalizePhone($request->input('cellphone'));
        $referenceId = $request->input('reference_id');
        $pageUrl = $request->input('page_url');
        $description = (string) ($request->input('description') ?: 'Doação');

        $address = [
            "street" => "Praça da Sé",
            "number" => "1",
            "zone"   => "Sé",
            "city"   => "São Paulo",
            "state"  => "SP",
            "zip"    => "01001000",
        ];

        $payload = [
            "client" => [
                "type"      => ($taxId !== '' && strlen($taxId) === 14) ? "pj" : "pf",
                "name"      => $name,
                "cpfCnpj"   => $taxId !== '' ? $taxId : null,
                "email"     => $email,
                "cellphone" => $cell,
                "address"   => $address,
                "referenceId" => $referenceId,
            ],

            "items" => [[
                "name"     => $description,
                "quantity" => 1,
                "value"    => (int) $request->input('amount_cents'),
            ]],

            "totalValue" => (int) $request->input('amount_cents'),
            "dueDate"    => now()->endOfDay()->toIso8601String(),

            "paymentMethods" => [
                "pix"        => ["enable" => true],
                "boleto"     => ["enable" => false],
                "creditCard" => ["enable" => false],
            ],

            "referenceId" => $referenceId,

            "integration" => [
                "enable" => true,
                "customFields" => array_values(array_filter([
                    ["name" => "method", "value" => "pix"],
                    ["name" => "recurrence", "value" => "once"],
                    $pageUrl ? ["name" => "page_url", "value" => $pageUrl] : null,
                    $referenceId ? ["name" => "custom_id", "value" => $referenceId] : null,
                ])),
            ],

            "observation" => "Doação (pix)",
            "async" => false,
        ];

        $stripNulls = function ($arr) use (&$stripNulls) {
            if (!is_array($arr)) return $arr;
            foreach ($arr as $k => $v) {
                if (is_array($v)) $arr[$k] = $stripNulls($v);
                if (array_key_exists($k, $arr) && $arr[$k] === null) unset($arr[$k]);
            }
            return $arr;
        };
        $payload = $stripNulls($payload);

        try {
            $headers = $this->lytexHeaders();
            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post($apiUrl, $payload);

            if ($res->status() === 401) {
                Cache::forget('lytex.token');
                $headers = $this->lytexHeaders();
                $res = Http::timeout(30)->withHeaders($headers)->post($apiUrl, $payload);
            }

            if ($res->failed()) {
                return response()->json([
                    'status'  => $res->status(),
                    'error'   => $res->json() ?? $res->body(),
                    'message' => 'Erro ao criar Pix avulso',
                ], $res->status());
            }

            $json = $res->json() ?? [];
            $pix = [
                'txId'   => data_get($json, 'paymentMethods.pix.txId')
                        ?? data_get($json, 'pix.txId'),
                'qrcode' => data_get($json, 'paymentMethods.pix.qrcode')
                        ?? data_get($json, 'pix.qrcode'),
                'emv'    => data_get($json, 'paymentMethods.pix.emv')
                        ?? data_get($json, 'pix.emv'),
                'qrcode_image' => data_get($json, 'paymentMethods.pix.qrcode_image')
                        ?? data_get($json, 'pix.qrcode_image')
                        ?? data_get($json, 'paymentMethods.pix.qrcode_image_base64')
                        ?? data_get($json, 'pix.qrcode_image_base64'),
            ];

            if (isset($pix['qrcode_image']) && is_string($pix['qrcode_image']) && $pix['qrcode_image'] !== '' && !str_starts_with($pix['qrcode_image'], 'data:image')) {
                $pix['qrcode_image'] = 'data:image/png;base64,' . $pix['qrcode_image'];
            }

            return response()->json([
                'success' => true,
                'invoice' => $json,
                'pix' => $pix,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erro Lytex Pix avulso:', ['msg' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Erro de conexao com o servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function lytexSubscriptionDetailUrl(string $subscriptionId): string
    {
        // Se você setar LYTEX_SUBSCRIPTION_DETAIL_URL com "{id}", ele usa.
        $tpl = config('services.lytex.subscription_detail_url');
        if (is_string($tpl) && $tpl !== '') {
            return str_replace(['{id}', ':id'], $subscriptionId, $tpl);
        }

        // Caso contrário, monta usando subscriptions_url
        $base = rtrim((string) config('services.lytex.subscriptions_url'), '/');
        return $base . '/' . $subscriptionId;
    }

    private function lytexInvoiceDetailUrl(string $invoiceId): string
    {
        $tpl = config('services.lytex.invoice_detail_url');
        if (is_string($tpl) && $tpl !== '') {
            return str_replace(['{id}', ':id'], $invoiceId, $tpl);
        }

        $base = rtrim((string) (config('services.lytex.invoice_url') ?: config('services.lytex.api_url')), '/');
        return $base . '/' . $invoiceId;
    }

    private function normalizeDigits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    private function normalizePhone(?string $v): string
    {
        $cell = $this->normalizeDigits($v);
        if (strlen($cell) > 11) {
            if (str_starts_with($cell, '55')) $cell = substr($cell, 2);
            if (strlen($cell) > 11) $cell = substr($cell, -11);
        }
        return $cell;
    }

    private function stripNullsRecursive($arr)
    {
        if (!is_array($arr)) return $arr;
        foreach ($arr as $k => $v) {
            if (is_array($v)) $arr[$k] = $this->stripNullsRecursive($v);
            if (array_key_exists($k, $arr) && $arr[$k] === null) unset($arr[$k]);
        }
        return $arr;
    }

    /**
     * Tenta extrair um subscriptionId de respostas diferentes
     */
    private function extractSubscriptionId(array $json): ?string
    {
        $candidates = [
            data_get($json, '_id'),
            data_get($json, 'id'),
            data_get($json, 'subscriptionId'),
            data_get($json, 'data._id'),
            data_get($json, 'data.id'),
            data_get($json, 'data.subscriptionId'),
        ];

        foreach ($candidates as $id) {
            if (is_string($id) && trim($id) !== '') return trim($id);
        }
        return null;
    }

    /**
     * Tenta extrair um invoiceId dentro de uma resposta de assinatura/detalhe
     */
    private function extractInvoiceIdFromSubscription(array $json): ?string
    {
        // Alguns retornos comuns:
        $candidates = [
            data_get($json, 'lastInvoice._invoiceId'),
            data_get($json, 'lastInvoice._id'),
            data_get($json, 'lastInvoice.id'),
            data_get($json, 'lastInvoice.invoiceId'),
            data_get($json, 'lastInvoice.invoice_id'),
            data_get($json, 'invoice._id'),
            data_get($json, 'invoice.id'),
            data_get($json, 'invoices.0._id'),
            data_get($json, 'invoices.0.id'),
            data_get($json, 'data.lastInvoice._id'),
            data_get($json, 'data.lastInvoice.id'),
            data_get($json, 'data.lastInvoice._invoiceId'),
            data_get($json, 'data.invoices.0._id'),
        ];

        foreach ($candidates as $id) {
            if (is_string($id) && trim($id) !== '') return trim($id);
        }
        return null;
    }

    /**
     * Faz parse do PIX a partir de um objeto de invoice (fatura)
     * (os nomes variam: qrcode/emv/qrCode/url/base64...)
     */
    private function parsePixFromInvoice(array $invoice): array
    {
        $emv = data_get($invoice, 'paymentMethods.pix.emv')
            ?? data_get($invoice, 'paymentMethods.pix.copyPaste')
            ?? data_get($invoice, 'paymentMethods.pix.copiaecola')
            ?? data_get($invoice, 'paymentMethods.boleto.qrCode.emv')
            ?? data_get($invoice, 'pix.emv')
            ?? data_get($invoice, 'transactions.0.pix.qrcode')
            ?? data_get($invoice, 'transactions.0.pix.qrCode')
            ?? null;

        $txId = data_get($invoice, 'paymentMethods.pix.txId')
            ?? data_get($invoice, 'paymentMethods.boleto.qrCode.txId')
            ?? data_get($invoice, 'pix.txId')
            ?? data_get($invoice, 'transactions.0.pix.txId')
            ?? null;

        // Pode vir como string base64 OU url OU payload
        $qrImage = data_get($invoice, 'paymentMethods.pix.qrcode')
            ?? data_get($invoice, 'paymentMethods.pix.qrCode')
            ?? data_get($invoice, 'paymentMethods.pix.qrcode_image')
            ?? data_get($invoice, 'paymentMethods.pix.qrcode_image_base64')
            ?? data_get($invoice, 'paymentMethods.boleto.qrCode.url')
            ?? data_get($invoice, 'pix.qrcode')
            ?? data_get($invoice, 'pix.qrcode_image')
            ?? data_get($invoice, 'pix.qrcode_image_base64')
            ?? data_get($invoice, 'transactions.0.pix.qrcode_image')
            ?? data_get($invoice, 'transactions.0.pix.qrcodeImage')
            ?? null;

        // Se vier base64 puro, prefixa
        if (is_string($qrImage) && $qrImage !== '' && !str_starts_with($qrImage, 'http') && !str_starts_with($qrImage, 'data:image')) {
            // assume base64 PNG
            $qrImage = 'data:image/png;base64,' . $qrImage;
        }

        return [
            'txId' => is_string($txId) ? $txId : null,
            'emv' => is_string($emv) ? $emv : null,
            'qrcode_image' => is_string($qrImage) ? $qrImage : null,
            'link_checkout' => data_get($invoice, 'linkCheckout')
                ?? data_get($invoice, 'lastInvoice.linkCheckout')
                ?? data_get($invoice, 'payment.link')
                ?? null,
        ];
    }

    /**
     * Busca detalhes da assinatura (GET /v2/subscriptions/{id})
     */
    private function fetchSubscriptionDetail(string $subscriptionId): array
    {
        $url = $this->lytexSubscriptionDetailUrl($subscriptionId);

        $res = Http::timeout(30)
            ->withHeaders($this->lytexHeaders())
            ->get($url);

        if ($res->status() === 401) {
            Cache::forget('lytex.token');
            $res = Http::timeout(30)->withHeaders($this->lytexHeaders())->get($url);
        }

        if ($res->failed()) {
            throw new \RuntimeException("Lytex subscription detail failed ({$res->status()}): " . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * Busca detalhes da fatura (GET /v2/invoices/{id})
     */
    private function fetchInvoiceDetail(string $invoiceId): array
    {
        $url = $this->lytexInvoiceDetailUrl($invoiceId);

        $res = Http::timeout(30)
            ->withHeaders($this->lytexHeaders())
            ->get($url);

        if ($res->status() === 401) {
            Cache::forget('lytex.token');
            $res = Http::timeout(30)->withHeaders($this->lytexHeaders())->get($url);
        }

        if ($res->failed()) {
            throw new \RuntimeException("Lytex invoice detail failed ({$res->status()}): " . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * PIX RECORRENTE (ASSINATURA) – cria assinatura e depois busca a invoice para retornar QR/EMV
     */
    public function createPixRecurring(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_cents' => 'required|integer|min:200',
            'cpf'          => 'required|string',
            'email'        => 'required|email',
            'cellphone'    => 'required|string',
            'periodicity'  => 'nullable|in:weekly,monthly,quarterly,semi_annual,annual',
            'name'         => 'nullable|string|min:2',
            'next_due_date'=> 'nullable|date_format:Y-m-d',
            'duration_months' => 'nullable|integer|min:1|max:1200',
            'reference_id' => 'nullable|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Missing or invalid fields',
                'details' => $validator->errors()
            ], 422);
        }

        $taxId = $this->normalizeDigits($request->input('cpf'));
        $isCpf  = strlen($taxId) === 11 && $this->isValidCpf($taxId);
        $isCnpj = strlen($taxId) === 14 && $this->isValidCnpj($taxId);
        if (!$isCpf && !$isCnpj) {
            return response()->json(['error' => 'Invalid payer tax ID'], 422);
        }

        $apiUrl = config('services.lytex.subscriptions_url');
        if (!$apiUrl) {
            return response()->json(['error' => 'Lytex subscriptions URL not configured'], 500);
        }

        $periodicity = (string) ($request->input('periodicity') ?: 'monthly');
        $name = (string) $request->input('name', 'Doador');
        $email = (string) $request->input('email');
        $cell = $this->normalizePhone($request->input('cellphone'));

        $nextDueDate = (string) ($request->input('next_due_date') ?: now()->addDays(1)->toDateString());
        $referenceId = $request->input('reference_id');
        $description = (string) ($request->input('description') ?: 'Doação Mensal');

        $amount = (int) $request->input('amount_cents');

        // duration é obrigatório na Lytex. Para "sem prazo", use um valor alto.
        $durationMonths = (int) ($request->input('duration_months') ?: 1200); // 100 anos

        $payload = [
            "client" => [
                "type"      => $isCnpj ? "pj" : "pf",
                "name"      => $name,
                "cpfCnpj"   => $taxId,
                "email"     => $email,
                "cellphone" => $cell,
                "referenceId" => $referenceId,
                // Se sua conta exigir address, inclua aqui.
                // "address" => [...]
            ],
            "description" => $description,
            "totalValue"  => $amount,
            "type"        => $periodicity,
            "nextDueDate" => $nextDueDate,
            "dueDateDays" => 1,

            "items" => [[
                "name"     => $description,
                "quantity" => 1,
                "value"    => $amount,
            ]],

            "paymentMethods" => [
                "pix"        => ["enable" => true],
                "boleto"     => ["enable" => false],
                "creditCard" => ["enable" => false],
            ],

            "referenceId" => $referenceId,
        ];

        $payload["duration"] = [
            "unit" => "months",
            "value" => max(1, $durationMonths),
        ];

        $payload = $this->stripNullsRecursive($payload);

        try {
            $headers = $this->lytexHeaders();

            Log::info('Lytex Pix recorrente - payload', $payload);

            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post($apiUrl, $payload);

            if ($res->status() === 401) {
                Cache::forget('lytex.token');
                $res = Http::timeout(30)->withHeaders($this->lytexHeaders())->post($apiUrl, $payload);
            }

            if ($res->failed()) {
                Log::error('Lytex Pix recorrente - erro', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                return response()->json([
                    'status'  => $res->status(),
                    'error'   => $res->json() ?? $res->body(),
                    'message' => 'Erro ao criar assinatura Pix (recorrente)',
                ], $res->status());
            }

            $subscription = $res->json() ?? [];
            Log::info('Lytex Pix recorrente - resposta', [
                'status' => $res->status(),
                'body' => $subscription,
            ]);
            $subscriptionId = $this->extractSubscriptionId($subscription);

            // 1) tenta pegar invoiceId direto do retorno (se já vier lastInvoice)
            $invoiceId = $this->extractInvoiceIdFromSubscription($subscription);

            $invoice = null;
            $pix = ['txId' => null, 'emv' => null, 'qrcode_image' => null];

            // 2) Se não vier invoiceId, consulta Detalhes da Assinatura (pode demorar alguns segundos)
            if (!$invoiceId && $subscriptionId) {
                for ($i = 0; $i < 3 && !$invoiceId; $i++) {
                    if ($i > 0) usleep(1000000); // 1s
                    $detail = $this->fetchSubscriptionDetail($subscriptionId);
                    Log::info('Lytex Pix recorrente - subscription detail', [
                        'subscriptionId' => $subscriptionId,
                        'attempt' => $i + 1,
                        'body' => $detail,
                    ]);
                    $invoiceId = $this->extractInvoiceIdFromSubscription($detail);

                    // às vezes o detalhe já traz o objeto lastInvoice completo
                    $maybeInvoiceObj = data_get($detail, 'lastInvoice');
                    if (is_array($maybeInvoiceObj)) {
                        $invoice = $maybeInvoiceObj;
                        $pix = $this->parsePixFromInvoice($invoice);
                    }
                }
            }

            // 3) Se ainda não temos invoice completa ou pix, busca detalhe da invoice
            if ($invoiceId && (!$invoice || (empty($pix['emv']) && empty($pix['qrcode_image'])))) {
                $invoice = $this->fetchInvoiceDetail($invoiceId);
                Log::info('Lytex Pix recorrente - invoice detail', [
                    'invoiceId' => $invoiceId,
                    'body' => $invoice,
                ]);
                $pix = $this->parsePixFromInvoice($invoice);

                // 4) Retry curto: às vezes o Pix demora alguns segundos para aparecer
                if (empty($pix['emv']) && empty($pix['qrcode_image'])) {
                    usleep(1500000); // 1.5s
                    $invoice = $this->fetchInvoiceDetail($invoiceId);
                    Log::info('Lytex Pix recorrente - invoice detail (retry)', [
                        'invoiceId' => $invoiceId,
                        'body' => $invoice,
                    ]);
                    $pix = $this->parsePixFromInvoice($invoice);
                }
            }

            // Retorno final para o front
            return response()->json([
                'success' => true,
                'subscriptionId' => $subscriptionId,
                'subscription' => $subscription,
                'invoiceId' => $invoiceId,
                'invoice' => $invoice,
                'pix' => $pix, // <-- aqui vai chegar o EMV e QR no front (quando existir)
                'note' => (empty($pix['emv']) && empty($pix['qrcode_image']))
                    ? 'Assinatura criada, mas a Lytex não retornou PIX ainda. Confira o invoiceId e consulte novamente em alguns segundos.'
                    : null,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erro Lytex Pix recorrente (subscriptions):', [
                'msg' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Erro de conexao com o servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createInvoice(Request $request)
    {
        $data = $request->all();

        $amountCents = (int) (
            data_get($data, 'amountCents')
            ?? data_get($data, 'amount_cents')
            ?? 0
        );

        $recurrence = (string) (data_get($data, 'recurrence') ?? 'once'); // once | monthly
        $method     = (string) (data_get($data, 'method') ?? '');

        $isRecurring = ($recurrence === 'monthly') || ($method === 'credit_card_recurring');

        $holderType = (string) data_get($data, 'creditCardHolder.type', 'pf');
        $holderName = (string) data_get($data, 'creditCardHolder.name', '');
        $holderCpf  = preg_replace('/\D+/', '', (string) data_get($data, 'creditCardHolder.cpfCnpj', ''));
        $holderMail = trim((string) data_get($data, 'creditCardHolder.email', ''));
        $holderCell = $this->normalizePhone((string) data_get($data, 'creditCardHolder.cellphone', ''));
        $donorEmail = trim((string) data_get($data, 'email', ''));

        $emailFallback = 'nao-informado@lusa-payments.com';
        $resolveDomain = function (?string $emailLanguage): bool {
            if (!is_string($emailLanguage) || $emailLanguage === '') return false;
            $parts = explode('@', $emailLanguage);
            if (count($parts) !== 2) return false;
            $domain = trim($parts[1]);
            if ($domain === '') return false;
            if (!function_exists('checkdnsrr')) {
                return true;
            }
            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        };

        $useHolderEmail = function (string $value) use ($resolveDomain): bool {
            return filter_var($value, FILTER_VALIDATE_EMAIL) && $resolveDomain($value);
        };

        if (!$useHolderEmail($holderMail)) {
            if ($useHolderEmail($donorEmail)) {
                $holderMail = $donorEmail;
            } else {
                $holderMail = $emailFallback;
            }
        }

        $ccNumber = preg_replace('/\D+/', '', (string) data_get($data, 'creditCard.number', ''));
        $ccHolder = (string) data_get($data, 'creditCard.holder', $holderName);
        $ccExpiry = (string) data_get($data, 'creditCard.expiry', '');
        $ccCvc    = preg_replace('/\D+/', '', (string) data_get($data, 'creditCard.cvc', ''));

        $customId = (string) (
            data_get($data, 'custom_id')
            ?? data_get($data, 'customId')
            ?? data_get($data, 'referenceId')
            ?? ''
        );
        $customId = trim($customId);

        $pageUrl = (string) (data_get($data, 'page_url') ?? data_get($data, 'pageUrl') ?? '');
        $pageUrl = trim($pageUrl);

        if ($amountCents <= 0) {
            return response()->json(['status' => 422, 'message' => 'amountCents inválido'], 422);
        }

        if ($holderName === '' || $holderCpf === '' || $holderMail === '' || $holderCell === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Dados do creditCardHolder incompletos (name/cpfCnpj/email/cellphone)'
            ], 422);
        }

        if ($ccNumber === '' || $ccExpiry === '' || $ccCvc === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Dados do cartão incompletos (number/expiry/cvc)'
            ], 422);
        }

        $address = [
            "street" => "Praça da Sé",
            "number" => "1",
            "zone"   => "Sé",
            "city"   => "São Paulo",
            "state"  => "SP",
            "zip"    => "01001000",
        ];

        // ✅ remove nulls recursivo (sem remover 0/false/"")
        $stripNulls = function ($arr) use (&$stripNulls) {
            if (!is_array($arr)) return $arr;
            foreach ($arr as $k => $v) {
                if (is_array($v)) $arr[$k] = $stripNulls($v);
                if (array_key_exists($k, $arr) && $arr[$k] === null) unset($arr[$k]);
            }
            return $arr;
        };

        // ✅ primeira data de vencimento (serve pro avulso e pro recorrente)
        $firstDue = now();
        $dueDateIso = $firstDue->endOfDay()->toIso8601String(); // ex: 2026-01-14T23:59:59.999-03:00
        $nextDueYmd = $firstDue->toDateString();                // ex: 2026-01-14

        $payload = [
            "client" => [
                "type"      => $holderType,
                "name"      => $holderName,
                "cpfCnpj"   => $holderCpf,
                "email"     => $holderMail,
                "cellphone" => $holderCell,
                "address"   => $address,
                "referenceId" => $customId !== '' ? $customId : null,
            ],

            // ✅ NÃO envie description se tiver items
            "items" => [
                [
                    "_productId" => $data['_productId'] ?? null,
                    "name"       => $isRecurring ? "Doação Mensal" : "Doação",
                    "quantity"   => 1,
                    "value"      => $amountCents,
                ]
            ],

            "totalValue" => $amountCents,

            // ✅ FIX: Lytex está exigindo dueDate TAMBÉM no recorrente
            "dueDate" => $dueDateIso,

            "paymentMethods" => [
                "pix"        => ["enable" => false],
                "boleto"     => ["enable" => false],
                "creditCard" => ["enable" => true, "maxParcels" => 1],
            ],

            "creditCard" => [
                "number" => $ccNumber,
                "holder" => $ccHolder,
                "expiry" => $ccExpiry,
                "cvc"    => $ccCvc,
            ],

            "creditCardHolder" => [
                "type"      => $holderType,
                "name"      => $holderName,
                "cpfCnpj"   => $holderCpf,
                "email"     => $holderMail,
                "cellphone" => $holderCell,
                "address"   => $address,
            ],

            "referenceId" => $customId !== '' ? $customId : null,

            "integration" => [
                "enable" => true,
                "customFields" => array_values(array_filter([
                    $method !== '' ? ["name" => "method", "value" => $method] : null,
                    $recurrence !== '' ? ["name" => "recurrence", "value" => $recurrence] : null,
                    $pageUrl !== '' ? ["name" => "page_url", "value" => $pageUrl] : null,
                    $customId !== '' ? ["name" => "custom_id", "value" => $customId] : null,
                ])),
            ],

            "observation" => $isRecurring ? "Doação mensal (cartão)" : "Doação (cartão)",
            "async" => false,
        ];

        // ✅ bloco recorrente
        if ($isRecurring) {
            $lytexType = (string) (data_get($data, 'lytexType') ?? 'monthly');
            $durationMonths = (int) (data_get($data, 'durationMonths') ?? 12);
            $dueDateDays = (int) (data_get($data, 'dueDateDays') ?? 1);

            $payload["type"]        = $lytexType;
            $payload["nextDueDate"] = $nextDueYmd;
            $payload["dueDateDays"] = max(1, $dueDateDays);
            $payload["duration"] = [
                "unit"  => "infinity",
                // "value" => max(1, $durationMonths),
            ];
        }

        $payload = $stripNulls($payload);

        try {
            $headers = $this->lytexHeaders();

            $invoiceUrl = config('services.lytex.invoice_url');
            $subscriptionsUrl = config('services.lytex.subscriptions_url');
            $apiUrl = $isRecurring ? ($subscriptionsUrl ?: $invoiceUrl) : $invoiceUrl;

            Log::info("==== ENVIANDO PARA LYTEX ({$apiUrl}) ====");
            Log::info("Payload enviado:", $payload);

            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post($apiUrl, $payload);

            if ($res->status() === 401) {
                Cache::forget('lytex.token');
                $headers = $this->lytexHeaders();
                $res = Http::timeout(30)->withHeaders($headers)->post($apiUrl, $payload);
            }

            Log::info("==== RESPOSTA LYTEX ====");
            Log::info("Status:", [$res->status()]);
            Log::info("Body:", [$res->body()]);

            if ($res->failed()) {
                return response()->json([
                    "status"  => $res->status(),
                    "error"   => $res->json() ?? $res->body(),
                    "message" => $res->status() == 401 ? "Credenciais inválidas" : "Erro ao criar invoice",
                ], $res->status());
            }

            return response()->json([
                "status" => $res->status(),
                "body"   => $res->json()
            ]);
        } catch (\Exception $e) {
            Log::error("Erro de conexão:", [$e->getMessage()]);
            return response()->json([
                "status"  => 500,
                "error"   => $e->getMessage(),
                "message" => "Erro de conexão ou interno"
            ], 500);
        }
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info("Lytex Webhook Recebido", $data);

        $statusRaw = strtolower((string) data_get($data, 'data.status', ''));
        $isPaid = in_array($statusRaw, ['paid', 'approved', 'liquidated'], true)
            || str_contains($statusRaw, 'paid')
            || str_contains($statusRaw, 'liquid');

        $fullName = (string) data_get($data, 'data.client.name', data_get($data, 'client.name', ''));
        $cpf = data_get($data, 'data.client.cpfCnpj', data_get($data, 'client.cpfCnpj', ''));
        $amountCents = (int) (data_get($data, 'data.invoiceValue', data_get($data, 'data.payedValue', 0)) ?? 0);
        $amount = $amountCents ? $amountCents / 100 : (float) data_get($data, 'amount', 0);
        $eventTime = data_get($data, 'data.payedAt') ? strtotime((string) data_get($data, 'data.payedAt')) : time();

        if ($fullName === '') {
            Log::warning('Webhook Lytex sem nome do pagador', $data);
            return response()->json([
                'ok' => false,
                'message' => 'Nome do pagador nao informado'
            ], 200);
        }

        $nameParts = explode(' ', trim($fullName));
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);

        $dados = Dados::orderBy('created_at', 'desc')
            ->take(200)
            ->get();

        $firstNameNormalized = strtolower($firstName);
        $dado = $dados->first(function ($item) use ($firstNameNormalized) {
            return strtolower($item->first_name) === $firstNameNormalized;
        });

        if ($dado) {
            $dado->first_name = ucwords(strtolower($firstName));
            $dado->last_name = ucwords(strtolower($lastName));
            $dado->status = $isPaid ? 'paid' : ($dado->status ?? 'pending');
            $dado->cpf = $cpf ?: $dado->cpf;
            $dado->amount = $amount ?: $dado->amount;
            $dado->amount_cents = $amountCents ?: $dado->amount_cents;
            $dado->method = $dado->method ?: (string) data_get($data, 'data.paymentMethod', 'credit_card');
            $dado->event_time = $eventTime;
            $dado->save();
        } else {
            $dado = Dados::create([
                'first_name' => ucwords(strtolower($firstName)),
                'last_name' => ucwords(strtolower($lastName)),
                'cpf' => $cpf,
                'status' => $isPaid ? 'paid' : 'pending',
                'amount' => $amount,
                'amount_cents' => $amountCents,
                'event_time' => $eventTime,
                'method' => (string) data_get($data, 'data.paymentMethod', 'credit_card'),
                'utm_source' => 'lytex',
                'utm_campaign' => 'lytex',
                'utm_medium' => 'credit_card',
                'utm_content' => 'lytex',
                'utm_term' => 'lytex',
                'page_url' => data_get($data, 'data.page_url', data_get($data, 'page_url', null)),
            ]);
        }

        $payload = [
            'status' => $dado->status,
            'amount' => $dado->amount,
            'amount_cents' => $dado->amount_cents,
            'payer_name' => trim(($dado->first_name ?? '') . ' ' . ($dado->last_name ?? '')),
            'payer_document' => $dado->cpf ?? '',
            'confirmed_at' => isset($dado->event_time) ? date('c', $dado->event_time) : now()->toIso8601String(),
            'email' => $dado->email,
            'phone' => $dado->phone,
            'ip' => $dado->ip ?? null,
            'method' => $dado->method,
            'event_time' => $dado->event_time,
            'page_url' => $dado->page_url,
            'client_user_agent' => $dado->client_user_agent ?? null,
            'fbp' => $dado->fbp ?? null,
            'fbc' => $dado->fbc ?? null,
            'fbclid' => $dado->fbclid ?? null,
            'utm_source' => $dado->utm_source,
            'utm_campaign' => $dado->utm_campaign,
            'utm_medium' => $dado->utm_medium,
            'utm_content' => $dado->utm_content,
            'utm_term' => $dado->utm_term,
        ];

        $capiPayload = null;
        $utmPayload = null;

        if ($payload['status'] === 'paid' && ($payload['amount'] ?? 0) >= 1) {
            $normalize = fn($str) => strtolower(trim($str));

            $hashedEmail = $dado->email ? hash('sha256', $normalize($dado->email)) : null;
            $cleanPhone = $dado->phone ? preg_replace('/\D+/', '', $dado->phone) : null;
            $hashedPhone = $cleanPhone ? hash('sha256', $cleanPhone) : null;
            $externalBase = ($dado->email ? $normalize($dado->email) : '') . ($cleanPhone ?: '');
            $hashedExternalId = $externalBase ? hash('sha256', $externalBase) : null;

            $userData = array_filter([
                'em' => $hashedEmail ? [$hashedEmail] : null,
                'ph' => $hashedPhone ? [$hashedPhone] : null,
                'fn' => $dado->first_name ? hash('sha256', $normalize($dado->first_name)) : null,
                'ln' => $dado->last_name ? hash('sha256', $normalize($dado->last_name)) : null,
                'external_id' => $hashedExternalId ?: null,
                'client_ip_address' => $dado->ip ?? null,
                'client_user_agent' => $dado->client_user_agent ?? null,
                'fbc' => $dado->fbc ?? null,
                'fbp' => $dado->fbp ?? null,
            ]);

            $customData = [
                'value' => $dado->amount,
                'currency' => 'BRL',
                'contents' => [['id' => 'credit_card_donation', 'quantity' => 1]],
                'content_type' => 'product',
                'utm_source' => $dado->utm_source,
                'utm_campaign' => $dado->utm_campaign,
                'utm_medium' => $dado->utm_medium,
                'utm_content' => $dado->utm_content,
                'utm_term' => $dado->utm_term,
                'lead_id' => $dado->fbclid ?? null
            ];

            $generateEventId = fn() => bin2hex(random_bytes(16));
            $eventsToSend = ['Purchase'];
            $dataEvents = [];
            foreach ($eventsToSend as $eventName) {
                $dataEvents[] = [
                    'event_name' => $eventName,
                    'event_time' => $dado->event_time ?? time(),
                    'action_source' => 'website',
                    'event_id' => $generateEventId(),
                    'event_source_url' => $dado->page_url,
                    'user_data' => $userData,
                    'custom_data' => $customData
                ];
            }

            // APENAS UM PIXEL
            $serviceKey = 'facebook_capi_siulsan_resgate'; // <- sua única chave agora (exemplo)

            $pixelId  = config("services.{$serviceKey}.pixel_id");
            $apiToken = config("services.{$serviceKey}.access_token");
            
            if (!$pixelId || !$apiToken) {
                Log::warning("PIXEL_ID ou FACEBOOK_ACCESS_TOKEN não configurados (single)", [
                    'service'  => $serviceKey,
                    'pixelId'  => $pixelId,
                    'hasToken' => !empty($apiToken),
                ]);
                // Se não tem config, para aqui.
                return;
            }
            
            $capiPayload = [
                'data' => $dataEvents,
                'access_token' => $apiToken,
            ];
            
            Log::info("CAPI Payload recebido (single)", [
                'pixel_id' => $pixelId,
            ]);
            
            $res = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);
            
            Log::info("Facebook CAPI response (single)", [
                'pixel_id' => $pixelId,
                'status'   => $res->status(),
                'body'     => $res->body(),
            ]);

            // DOIS PIXEL
            // $targets = [];
            // $camp = (string) ($dado->utm_campaign ?? '');

            // if (stripos($camp, 'B1S') !== false) {
            //     $targets = ['b1s' => 'facebook_capi_siulsan_resgate_b1s'];
            // } elseif (stripos($camp, 'B2S') !== false) {
            //     $targets = ['b2s' => 'facebook_capi_siulsan_resgate_b2s'];
            // } else {
            //     $targets = [
            //         'b1s' => 'facebook_capi_siulsan_resgate_b1s',
            //         'b2s' => 'facebook_capi_siulsan_resgate_b2s',
            //     ];

            //     Log::warning('utm_campaign sem B1S/B2S', [
            //         'utm_campaign' => $dado->utm_campaign ?? null,
            //         'id' => $dado->id ?? null,
            //     ]);
            // }

            // foreach ($targets as $label => $serviceKey) {
            //     $pixelId = config("services.{$serviceKey}.pixel_id");
            //     $apiToken = config("services.{$serviceKey}.access_token");

            //     if (!$pixelId || !$apiToken) {
            //         Log::warning("PIXEL_ID ou FACEBOOK_ACCESS_TOKEN nao configurados ({$label})", [
            //             'service' => $serviceKey,
            //             'pixelId' => $pixelId,
            //             'hasToken' => !empty($apiToken),
            //         ]);
            //         continue;
            //     }

            //     $capiPayload = [
            //         'data' => $dataEvents,
            //         'access_token' => $apiToken,
            //     ];

            //     $res = Http::withHeaders(['Content-Type' => 'application/json'])
            //         ->post("https://graph.facebook.com/v17.0/{$pixelId}/events", $capiPayload);

            //     Log::info("Facebook CAPI response (Lytex paid) - {$label}", [
            //         'pixel_id' => $pixelId,
            //         'status' => $res->status(),
            //         'body' => $res->body(),
            //     ]);
            // }
        }

        if ($payload['status'] === 'paid') {
            $utmPayload = [
                'orderId' => 'ord_' . substr(bin2hex(random_bytes(4)), 0, 8),
                'platform' => 'Checkout',
                'paymentMethod' => 'credit_card',
                'status' => 'paid',
                'createdAt' => $payload['event_time'] ? date('c', $payload['event_time']) : now()->toIso8601String(),
                'approvedDate' => $payload['confirmed_at'],
                'refundedAt' => null,
                'customer' => [
                    'name' => $payload['payer_name'] ?? '',
                    'email' => $payload['email'] ?? '',
                    'phone' => $payload['phone'] ?? '',
                    'document' => $payload['payer_document'] ?? '',
                    'country' => 'BR',
                    'ip' => $payload['ip'] ?? ''
                ],
                'products' => [
                    [
                        'id' => '01',
                        'name' => "SR R$ {$payload['amount']}",
                        'planId' => (string) $payload['amount'],
                        'planName' => (string) $payload['amount'],
                        'quantity' => 1,
                        'priceInCents' => $payload['amount_cents']
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
                    'currency' => 'BRL'
                ],
                'isTest' => false
            ];

            $utmUrl = config('services.utmify.url');
            $utmKey = config('services.utmify.api_key');

            if ($utmUrl && $utmKey) {
                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-token' => $utmKey,
                ])->post($utmUrl, $utmPayload);

                Log::info("Utmify response (Lytex paid)", [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
            } else {
                Log::warning('UTMFY_URL ou UTMFY_API_KEY nao configurados', [
                    'utmUrl' => $utmUrl,
                    'utmKey' => !empty($utmKey),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Webhook recebido com sucesso',
            'received' => [
                'capi' => $capiPayload,
                'utm' => $utmPayload,
            ]
        ], 200);
    }
}
