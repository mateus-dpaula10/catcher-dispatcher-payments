<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NuveiController extends Controller
{
    public function openOrder(Request $request)
    {
        $nuvei = config('services.nuvei', []);
        $merchantId = (string) ($nuvei['merchant_id'] ?? '');
        $merchantSiteId = (string) ($nuvei['merchant_site_id'] ?? '');
        $secretKey = (string) ($nuvei['secret_key'] ?? '');

        if (!$merchantId || !$merchantSiteId || !$secretKey) {
            return response()->json(['message' => 'Nuvei configuration is missing'], 500);
        }

        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            return response()->json(['message' => 'Invalid amount'], 422);
        }

        $currency = strtoupper(trim((string) $request->input('currency', 'USD')));
        $externalId = (string) $request->input('external_id', '');
        $firstName = (string) $request->input('first_name', '');
        $lastName = (string) $request->input('last_name', '');
        $email = (string) $request->input('email', '');
        $country = strtoupper(trim((string) $request->input('country_code', 'US')));
        $period = (string) $request->input('period', 'one_time');

        $amountString = number_format($amount, 2, '.', '');
        $clientUniqueId = $externalId ?: Str::uuid()->toString();
        $clientRequestId = Str::uuid()->toString();
        $userTokenId = $email ?: $clientUniqueId;
        $timeStamp = now()->format('YmdHis');

        $checksum = $this->calculateChecksum(
            $merchantId,
            $merchantSiteId,
            $clientRequestId,
            $amountString,
            $currency,
            $timeStamp,
            $secretKey
        );

        $payload = [
            'merchantId' => $merchantId,
            'merchantSiteId' => $merchantSiteId,
            'clientRequestId' => $clientRequestId,
            'clientUniqueId' => $clientUniqueId,
            'userTokenId' => $userTokenId,
            'currency' => $currency,
            'amount' => $amountString,
            'timeStamp' => $timeStamp,
            'checksum' => $checksum,
            'language' => 'EN',
            'transactionType' => 'Auth',
            'country' => $country,
            'customerFirstName' => $firstName ?: null,
            'customerLastName' => $lastName ?: null,
            'billingAddress' => [
                'country' => $country,
                'email' => $email ?: null,
            ],
        ];

        $endpoint = $this->resolveNuveiEndpoint(strtolower($nuvei['environment'] ?? 'prod'), $nuvei);
        if (!$endpoint) {
            return response()->json(['message' => 'Nuvei endpoint is not configured'], 500);
        }

        Log::info('Nuvei openOrder payload', [
            'client_request_id' => $clientRequestId,
            'client_unique_id' => $clientUniqueId,
            'amount' => $amountString,
            'currency' => $currency,
            'page_url' => $request->header('referer'),
        ]);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload);

            $body = $response->json();
        } catch (\Throwable $exception) {
            Log::error('Nuvei openOrder request failed', [
                'error' => $exception->getMessage(),
                'payload' => $payload,
            ]);
            return response()->json(['message' => 'Nuvei request failed'], 500);
        }

        if (empty($body['sessionToken'])) {
            Log::warning('Nuvei openOrder response missing sessionToken', [
                'status' => $response->status() ?? null,
                'body' => $body,
            ]);
            return response()->json(['message' => 'Nuvei did not return a wallet session'], 502);
        }

        return response()->json([
            'sessionToken' => $body['sessionToken'],
            'merchantId' => $merchantId,
            'merchantSiteId' => $merchantSiteId,
            'clientRequestId' => $clientRequestId,
            'clientUniqueId' => $clientUniqueId,
            'currency' => $currency,
            'amount' => $amountString,
            'env' => strtolower($nuvei['environment'] ?? 'prod'),
            'countryCode' => $country,
            'period' => $period,
        ]);
    }

    private function calculateChecksum(string $merchantId, string $merchantSiteId, string $clientRequestId, string $amount, string $currency, string $timeStamp, string $secret): string
    {
        return hash('sha256', "{$merchantId}{$merchantSiteId}{$clientRequestId}{$amount}{$currency}{$timeStamp}{$secret}");
    }

    private function resolveNuveiEndpoint(string $env, array $nuvei): ?string
    {
        if ($env === 'int' || $env === 'test' || $env === 'sandbox') {
            return $nuvei['endpoint_int'] ?? null;
        }
        return $nuvei['endpoint_prod'] ?? null;
    }
}
