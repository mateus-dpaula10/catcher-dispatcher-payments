<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\AutomaticPixAuthorization;

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
        'name'            => 'nullable|string',
        'bank_ispb'       => 'required|string',
        'branch'          => 'required|string',
        'account'         => 'required|string',
        'expiration_date' => 'nullable|date_format:Y-m-d H:i:s',
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
        'annual', 'yearly' => 'annual',
        default            => 'monthly',
    };

    $startDate      = now()->addDay()->format('Y-m-d');          
    $expirationDate = now()->format('Y-m-d H:i:s'); 

    $authorization = AutomaticPixAuthorization::create([
        'amount_cents'    => $request->input('amount_cents'),
        'cpf'             => $request->input('cpf'),
        'email'           => $request->input('email'),
        'cellphone'       => $request->input('cellphone'),
        'periodicity'     => $frequency, 
        'status'          => 'pending',
        'start_date'      => $startDate,
        'expiration_date' => $expirationDate,
    ]);

    $amount = $authorization->amount_cents;

    $transfeeraPayload = [
        "type"             => "authorization",
        "frequency"        => $frequency,
        "retry_policy"     => "not_allowed",

        "start_date"      => $startDate,
        "expiration_date" => $expirationDate,
        
        "fixed_amount"     => $amount,
        
        "identifier"       => (string) $authorization->id,
        "description"      => "Doação via site",

        "payer" => [
            "name"      => $request->input('name', 'Doador Anônimo'),
            "tax_id"    => preg_replace('/\D/', '', $authorization->cpf),
            "bank_ispb" => $request->input('bank_ispb'),
            "branch"    => $request->input('branch'),
            "account"   => $request->input('account'),
        ],
    ];

    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TRANSFEERA_API_KEY'),
            'Content-Type'  => 'application/json'
        ])->post(
            'https://api.transfeera.com/pix/automatic/authorizations',
            $transfeeraPayload
        );

        if ($response->successful()) {
            $data = $response->json();

            $authorization->update([
                'transfeera_id' => $data['id']    ?? null,
                'status'        => $data['status'] ?? 'pending'
            ]);

            return response()->json($data, 200);
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
