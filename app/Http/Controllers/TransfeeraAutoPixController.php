<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\AutomaticPixAuthorization;
use App\Services\TransfeeraAuthService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransfeeraAutoPixController extends Controller
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

    public function createAuthorization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_cents' => 'required|integer|min:1',
            'cpf'          => 'required|string',
            'email'        => 'required|email',
            'cellphone'    => 'required|string',
            'periodicity'  => 'nullable|in:weekly,monthly,quarterly,semi_annual,annual',
            'name'         => 'nullable|string',
            'end_date'     => 'nullable|date',
            'expiration'   => 'nullable|integer|min:60', // <- no print, expiration é inteiro (segundos)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Missing or invalid fields',
                'details' => $validator->errors()
            ], 400);
        }

        $cpfDigits = preg_replace('/\D+/', '', (string) $request->input('cpf'));
        $isCpf  = strlen($cpfDigits) === 11 && $this->isValidCpf($cpfDigits);
        $isCnpj = strlen($cpfDigits) === 14 && $this->isValidCnpj($cpfDigits);
        if (!$isCpf && !$isCnpj) {
            return response()->json(['error' => 'Invalid payer tax ID'], 400);
        }

        $authorization = AutomaticPixAuthorization::create([
            'amount_cents' => (int) $request->input('amount_cents'),
            'cpf'          => $cpfDigits,
            'email'        => $request->input('email'),
            'cellphone'    => preg_replace('/\D/', '', (string) $request->input('cellphone')),
            'periodicity'  => 'monthly',
            'status'       => 'pending',
        ]);

        $now = Carbon::now();

        // 🔥 muitos provedores exigem start_date no futuro (você já estava colocando +1 week)
        $startDate = $now->format('Y-m-d');

        $donorName = trim((string) $request->input('name', ''));
        if ($donorName === '') $donorName = 'Doador';

        // ✅ PIX KEY do recebedor (coloque no .env)
        $pixKey = env('TRANSFEERA_PIX_KEY', 'siulsan.resgate@gmail.com');

        // ✅ expiration em segundos (se não vier, padrão 86400)
        $expirationSeconds = (int) ($request->input('expiration') ?: 86400);

        // ✅ amount integer (use o mesmo padrão do seu sistema: cents)
        $amount = (int) $authorization->amount_cents;

        $transfeeraPayload = [
            "type"         => "immediate_payment_authorization",
            "frequency"    => "monthly",
            "retry_policy" => "allow_three_in_seven_days",

            "start_date"  => $startDate,
            "identifier"  => "AUTH_" . (string) $authorization->id,
            "description" => "DOACAO_SITE",

            // ✅ ERRO ANTERIOR PEDIA ESTES CAMPOS NO ROOT
            "fixed_amount"     => $amount,
            "max_amount_floor" => $amount,

            "debtor" => [
                "name"   => $donorName,
                "tax_id" => (string) $authorization->cpf,
            ],

            // ✅ PRINT CONFIRMA: payment é obrigatório
            "payment" => [
                "expiration" => $expirationSeconds,
                "modality"   => "fixed_amount", // allowed: fixed_amount | modifiable_amount
                "pix_key"    => $pixKey,
                "amount"     => $amount,
                // opcionais do print:
                // "txid" => "...",
                // "integration_id" => "...",
            ],
        ];

        $endDate = $request->input('end_date');
        if ($endDate) {
            $transfeeraPayload['end_date'] = $endDate;
        }

        try {
            $token = TransfeeraAuthService::getAccessToken();

            Log::info('Transfeera payload', $transfeeraPayload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ])->post('https://api.transfeera.com/pix/automatic/authorizations', $transfeeraPayload);

            if ($response->successful()) {
                $data = $response->json();
                $remoteAuthorization = $data['authorization'] ?? $data;

                $authorization->update([
                    'transfeera_id' => $remoteAuthorization['id'] ?? null,
                    'status'        => $remoteAuthorization['status'] ?? 'pending',
                ]);

                $qrCodeImage = $remoteAuthorization['qrcode_image_base64'] ?? null;
                if ($qrCodeImage && !str_starts_with($qrCodeImage, 'data:image')) {
                    $qrCodeImage = 'data:image/png;base64,' . $qrCodeImage;
                }

                $qrCodeText = $remoteAuthorization['qrcode_payload'] ?? null;

                return response()->json([
                    'success'                     => true,
                    'authorization'               => $remoteAuthorization,
                    'authorization_qr_code_image' => $qrCodeImage,
                    'authorization_qr_code_text'  => $qrCodeText,
                ], 200);
            }

            return response()->json([
                'error'   => 'Erro ao criar Pix automático na Transfeera',
                'details' => $response->body(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Erro de conexão com o servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
