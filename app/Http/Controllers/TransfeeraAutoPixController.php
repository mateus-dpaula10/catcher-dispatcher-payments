<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\AutomaticPixAuthorization;
use App\Services\TransfeeraAuthService;

class TransfeeraAutoPixController extends Controller
{
    public function createAuthorization(Request $request)
    {
        // Validação dos campos obrigatórios
        $validator = Validator::make($request->all(), [
            'amount_cents'    => 'required|integer|min:1',
            'cpf'             => 'required|string',
            'email'           => 'required|email',
            'cellphone'       => 'required|string',
            'periodicity'     => 'nullable|in:weekly,monthly,quarterly,semi_annual,annual,yearly',
            'name'            => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Missing or invalid fields',
                'details' => $validator->errors()
            ], 400);
        }

        $rawPeriodicity = $request->input('periodicity', 'monthly');
        $frequency = match ($rawPeriodicity) {
            'weekly'           => 'weekly',
            'monthly'          => 'monthly',
            'quarterly'        => 'quarterly',
            'semi_annual'      => 'semi_annual',
            'annual'           => 'annual',
            default            => 'monthly',
        };

        $authorization = AutomaticPixAuthorization::create([
            'amount_cents' => $request->input('amount_cents'),
            'cpf'          => preg_replace('/\D/', '', $request->input('cpf')),
            'email'        => $request->input('email'),
            'cellphone'    => preg_replace('/\D/', '', $request->input('cellphone')),
            'periodicity'  => $frequency,
            'status'       => 'pending'
        ]);

        $startDate = now()->format('Y-m-d');   

        $transfeeraPayload = [
            "type"             => "authorization_qrcode",
            "frequency"        => $frequency,
            "retry_policy"     => "not_allowed",

            "start_date"   => $startDate,
            
            "fixed_amount"     => $authorization->amount_cents,            
            "identifier"       => (string) $authorization->id,
            "description"      => "DOACAO_SITE",
            
            "debtor" => [
                "name"   => $request->name,
                "tax_id" => $authorization->cpf
            ]
        ];
        
        try {
            $token = TransfeeraAuthService::getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ])->post('https://api.transfeera.com/pix/automatic/authorizations', $transfeeraPayload);

            if ($response->successful()) {
                $data = $response->json();

                $remoteAuthorization = $data['authorization'] ?? $data;

                $authorization->update([
                    'transfeera_id' => $remoteAuthorization['id']    ?? null,
                    'status'        => $remoteAuthorization['status'] ?? 'pending'
                ]);

                $qrCodeImage = $remoteAuthorization['qrcode_image_base64'] ?? null;

                if ($qrCodeImage && !str_starts_with($qrCodeImage, 'data:image')) {
                    $qrCodeImage = 'data:image/png;base64,' . $qrCodeImage;
                }

                $qrCodeText = $remoteAuthorization['qrcode_payload'] ?? null;

                return response()->json([
                    'success'         => true,
                    'authorization'   => $remoteAuthorization,
                    'qr_code_image'   => $qrCodeImage, 
                    'qr_code_text'  => $qrCodeText
                ], 200);
            }

            return response()->json([
                'error'   => 'Erro ao criar Pix automático na Transfeera',
                'details' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Erro de conexão com o servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
