<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;

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
            \Log::info('Payload recebido do front:', $data);

            // Chaves que queremos capturar
            $utmKeys = ['utm_source','utm_campaign','utm_medium','utm_content','utm_term','utm_id','fbclid','fbp','fbc'];

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

            $ip = getRealIp();

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
            \Log::error('Erro ao salvar dados:', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}