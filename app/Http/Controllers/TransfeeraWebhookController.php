<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransfeeraWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Remove limite de tempo de execução do PHP
        set_time_limit(0);

        try {
            $event = $request->all();

            Log::info("Transfeera Webhook Recebido", $event);

            // -------------------------------------------------------------------
            // PING da Transfeera → obrigatório retornar 200
            // -------------------------------------------------------------------
            if (isset($event['ping']) && $event['ping'] === true) {
                return response()->json(['ok' => true], 200);
            }

            // -------------------------------------------------------------------
            // Verifica se existe pix_key
            // -------------------------------------------------------------------
            if (!isset($event['data']['pix_key'])) {
                return response()->json([
                    'error' => 'pix_key not found in event',
                    'received' => $event
                ], 200); // nunca retornar 400, para não bloquear webhook
            }

            $pixKey = $event['data']['pix_key'];

            // -------------------------------------------------------------------
            // Verifica a chave e chama o controller correspondente
            // -------------------------------------------------------------------
            switch ($pixKey) {
                case 'abrigoproanimal@gmail.com':
                    $controller = app(\App\Http\Controllers\TransfeeraProAnimalController::class);
                    $fakeRequest = Request::create('/api/proanimal', 'POST', $event);
                    $response = $controller->receive($fakeRequest);
                    break;

                case 'siulsan.resgate@gmail.com':
                    $controller = app(\App\Http\Controllers\TransfeeraSiulsanController::class);
                    $fakeRequest = Request::create('/api/siulsan', 'POST', $event);
                    $response = $controller->receive($fakeRequest);
                    break;

                default:
                    return response()->json([
                        'error' => 'Chave PIX não vinculada a nenhum controller',
                        'pix_key' => $pixKey
                    ], 200);
            }

            return $response; // Retorna o que o controller chamado retornar
        } catch (\Exception $e) {
            Log::error("Erro não tratado no webhook", ['error' => $e->getMessage(), 'payload' => $request->all()]);

            return response()->json([
                'error' => 'Unhandled error',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}