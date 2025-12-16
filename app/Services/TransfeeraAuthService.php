<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransfeeraAuthService
{
    public static function getAccessToken(): string
    {
        return Cache::remember('transfeera_access_token', 1700, function () {

            $response = Http::withHeaders([
                'User-Agent'   => 'SOS ANIMAL HELP (pagamentos@sosanimalhelp.org)',
                'Content-Type' => 'application/json',
            ])->post('https://login-api.transfeera.com/authorization', [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.transfeera.client_id'),
                'client_secret' => config('services.transfeera.client_secret'),
            ]);

            if (!$response->successful()) {
                Log::error('Erro ao gerar token Transfeera', [
                    'response' => $response->body()
                ]);

                throw new \Exception('Erro ao autenticar na Transfeera');
            }

            $data = $response->json();

            return $data['access_token'];
        });
    }
}
