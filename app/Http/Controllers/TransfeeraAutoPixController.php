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
            'amount_cents' => 'required|integer|min:1',
            'cpf' => 'required|string',
            'email' => 'required|email',
            'cellphone' => 'required|string',
            'periodicity' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Missing or invalid fields',
                'details' => $validator->errors()
            ], 400);
        }

         $authorization = AutomaticPixAuthorization::create([
            'amount_cents' => $request->input('amount_cents'),
            'cpf' => $request->input('cpf'),
            'email' => $request->input('email'),
            'cellphone' => $request->input('cellphone'),
            'periodicity' => $request->input('periodicity', 'once'),
            'status' => 'pending'
        ]);

        $transfeeraPayload = [
            "type" => "authorization",
            "frequency" => $authorization->periodicity,
            "retry_policy" => "not_allowed",
            "payer" => [
                "name" => $request->input('name', 'Doador Anônimo'),
                "tax_id" => preg_replace('/\D/', '', $authorization->cpf),
            ],
            "start_date" => now()->format('Y-m-d'),
            "expiration_date" => null,
            "fixed_amount" => $authorization->amount_cents,
            "max_amount_floor" => $authorization->amount_cents,
            "identifier" => (string) $authorization->id,
            "description" => "Doação via site"
        ];

        return response()->json($transfeeraPayload, 200);

        // try {
        //     $response = Http::withHeaders([
        //         'Authorization' => 'Bearer ' . env('TRANSFEERA_API_KEY'),
        //         'Content-Type' => 'application/json'
        //     ])->post('https://api.transfeera.com/pix/automatic/authorizations', $transfeeraPayload);

        //     if ($response->successful()) {
        //         $data = $response->json();

        //         $authorization->update([
        //             'transfeera_id' => $data['id'] ?? null,
        //             'status' => $data['status'] ?? 'pending'
        //         ]);

        //         return response()->json($data, 200);
        //     } else {
        //         return response()->json([
        //             'error' => 'Erro ao criar Pix automático na Transfeera',
        //             'details' => $response->body()
        //         ], $response->status());
        //     }
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'error' => 'Erro de conexão com o servidor',
        //         'message' => $e->getMessage()
        //     ], 500);
        // }
    }
}
