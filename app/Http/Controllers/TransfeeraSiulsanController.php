<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;
use Illuminate\Support\Facades\Http;

class TransfeeraSiulsanController extends Controller
{
    public function receive(Request $request)
    {
        $data = $request->all();

        \Log::info("Transfeera Siulsan Webhook Recebido", $data);

        $payer = $data['data']['payer'] ?? [];
        $fullName = $payer['name'] ?? null;
        $cpf = $payer['document'] ?? null;
        $amount = $data['data']['value'] ?? null;
        $amountCents = $amount !== null ? intval($amount * 100) : null;
        $eventTime = isset($data['date']) ? strtotime($data['date']) : time();

        if (!$fullName) {
            \Log::warning('Payload sem nome do pagador', $data);
            return response()->json([
                'ok' => false,
                'message' => 'Nome do pagador não informado'
            ], 200);
        }

        $nameParts = explode(' ', trim($fullName));
        $firstName = array_shift($nameParts); 
        $lastName = implode(' ', $nameParts); 

        $dados = Dados::orderBy('created_at', 'desc')
                     ->take(2)
                     ->get();

        $dado = $dados->firstWhere('first_name', $firstName);

        if ($dado) {
            $dado->status = 'paid';
            $dado->cpf = $cpf;
            $dado->last_name = $lastName;          
            $dado->amount = $amount;               
            $dado->amount_cents = $amountCents; 
            $dado->save();

            \Log::info("Registro existente atualizado para paid", [
                'id' => $dado->id,
                'first_name' => $dado->first_name,
                'cpf' => $cpf
            ]);
        } else {
            $dado = Dados::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'cpf' => $cpf,
                'status' => 'paid',
                'pix_key' => $data['data']['pix_key'] ?? null,
                'amount' => $amount,
                'amount_cents' => $amountCents,
                'event_time' => $eventTime,
                'method'         => 'chave pix',
                'utm_source'     => 'chave pix',
                'utm_campaign'   => 'chave pix',
                'utm_medium'     => 'chave pix',
                'utm_content'    => 'chave pix',
                'utm_term'       => 'chave pix',
                'page_url'       => 'https://chavepix.com.br'
            ]);

            \Log::info("Registro criado a partir do payload", [
                'id' => $dado->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'cpf' => $cpf,
                'statys' => 'paid',
                'pix_key' => $data['data']['pix_key'] ?? null,
                'amount' => $amount,
                'amount_cents' => $amountCents
            ]);
        }

        $payload = [
            'status'            => $dado->status,
            'amount'            => $dado->amount,
            'amount_cents'      => $dado->amount_cents,
            'first_name'        => $dado->first_name,
            'last_name'         => $dado->last_name,

            'payer_name'        => trim(($dado->first_name ?? '') . ' ' . ($dado->last_name ?? '')),
            'payer_document'    => $dado->cpf ?? '',
            'confirmed_at'      => isset($dado->event_time) ? date('c', $dado->event_time) : now()->toIso8601String(),

            'email'             => $dado->email,
            'phone'             => $dado->phone,
            'cpf'               => $dado->cpf,
            'ip'                => $dado->ip ?? null,
            'method'            => $dado->method,
            'event_time'        => $dado->event_time,
            'page_url'          => $dado->page_url,
            'client_user_agent' => $dado->client_user_agent ?? null,
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
        ];

        if ($dado->status === 'paid' && $dado->amount >= 1) {
            // -----------------------------
            // Gerar event_id
            // -----------------------------
            $baseId = ($dado->amount ?? 0) . '_' . ($dado->cpf ?? 'no_doc') . '_' . ($dado->event_time ?? now()->timestamp);
            $eventId = preg_replace("/[^a-zA-Z0-9_-]/", "", $baseId) . "_" . substr(bin2hex(random_bytes(3)), 0, 6);

            // -----------------------------
            // Campos hashados para user_data
            // -----------------------------
            $normalize = fn($str) => strtolower(trim($str));
            
            $hashedEmail = $dado->email ? hash('sha256', $normalize($dado->email)) : null;
            $cleanPhone = $dado->phone ? preg_replace('/\D/', '', $dado->phone) : null;
            $hashedPhone = $cleanPhone ? hash('sha256', $cleanPhone) : null;

            $externalBase = ($dado->email ? $normalize($dado->email) : '') . ($cleanPhone ?? '');
            $hashedExternalId = $externalBase ? hash('sha256', $externalBase) : null;

            $userData = [
                'em' => $hashedEmail ? [$hashedEmail] : null,
                'ph' => $hashedPhone ? [$hashedPhone] : null,
                'fn' => $dado->first_name ? [hash('sha256', $normalize($dado->first_name))] : null,
                'ln' => $dado->last_name ? [hash('sha256', $normalize($dado->last_name))] : null,
                'external_id' => $hashedExternalId ? [$hashedExternalId] : null,
                'client_ip_address' => $dado->ip ?? null,
                'client_user_agent' => $dado->client_user_agent ?? null,
                'fbc' => $dado->fbc ?? null,
                'fbp' => $dado->fbp ?? null
            ];
            $userData = array_filter($userData, fn($v) => $v !== null);

            // -----------------------------
            // custom_data
            // -----------------------------
            $customData = [
                'value' => $dado->amount,
                'currency' => 'BRL',
                'contents' => [['id' => 'pix_donation', 'quantity' => 1]],
                'content_type' => 'product',
                'utm_source' => $dado->utm_source,
                'utm_campaign' => $dado->utm_campaign,
                'utm_medium' => $dado->utm_medium,
                'utm_content' => $dado->utm_content,
                'utm_term' => $dado->utm_term,
                'lead_id' => $dado->fbclid ?? null
            ];

            // -----------------------------
            // payload final
            // -----------------------------
            $capiPayload = [
                'data' => [
                    [
                        'event_name' => 'AddToCart',
                        'event_time' => $dado->event_time ?? time(),
                        'action_source' => 'website',
                        'event_id' => $eventId,
                        'event_source_url' => $dado->page_url,
                        'user_data' => $userData,
                        'custom_data' => $customData
                    ],
                    [
                        'event_name' => 'Purchase',
                        'event_time' => $dado->event_time ?? time(),
                        'action_source' => 'website',
                        'event_id' => $eventId,
                        'event_source_url' => $dado->page_url,
                        'user_data' => $userData,
                        'custom_data' => $customData
                    ]
                ],
                'access_token' => env('FACEBOOK_ACCESS_TOKEN')
            ];

            // -----------------------------
            // envio para Facebook CAPI
            // -----------------------------
            $res = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://graph.facebook.com/v17.0/" . env('PIXEL_ID') . "/events", $capiPayload);

            \Log::info("Facebook CAPI response", [
                'status' => $res->status(),
                'body' => $res->body()
            ]);
        }

        if ($payload['status'] === 'paid') {
            $utmPayload = [
                'orderId' => 'ord_' . substr(bin2hex(random_bytes(4)), 0, 8),
                'platform' => 'Checkout',
                'paymentMethod' => 'pix',
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
                        'planId' => (string)$payload['amount'],
                        'planName' => (string)$payload['amount'],
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

            $res = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-token' => env('UTMFY_API_KEY')
            ])->post(env('UTMFY_URL'), $utmPayload);

            \Log::info("Utmify response", ['status' => $res->status(), 'body' => $res->body()]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Webhook recebido com sucesso',
            'received' => $capiPayload, $utmPayload
        ], 200);
    }
}
