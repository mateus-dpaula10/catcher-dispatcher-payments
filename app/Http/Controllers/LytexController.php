<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LytexController extends Controller
{
    public function createInvoice(Request $request)
    {
        $data = $request->all();

        // Montar payload final para Lytex
        $payload = [
            "client" => [
                "type" => $data['creditCardHolder']['type'],
                "name" => $data['creditCardHolder']['name'],
                "cpfCnpj" => $data['creditCardHolder']['cpfCnpj'],
                "email" => $data['creditCardHolder']['email'],
                "cellphone" => $data['creditCardHolder']['cellphone'],
                "address" => [
                    "street" => "Praça da Sé",
                    "number" => "1",
                    "zone"   => "Sé",
                    "city"   => "São Paulo",
                    "state"  => "SP",
                    "zip"    => "01001000"
                ],
            ],
            "items" => [
                [
                    "_productId" => $data['_productId'] ?? null,
                    "name" => "Doação",
                    "quantity" => 1,
                    "value" => $data['amountCents'] ?? 500
                ]
            ],
            "totalValue" => $data['amountCents'] ?? 500,
            "dueDate" => now()->addDay()->toIso8601String(),
            "paymentMethods" => [
                "pix" => ["enable" => false],
                "boleto" => ["enable" => false],
                "creditCard" => ["enable" => true, "maxParcels" => 1]
            ],
            "creditCard" => [
                "number" => $data['creditCard']['number'],
                "holder" => $data['creditCard']['holder'],
                "expiry" => $data['creditCard']['expiry'],
                "cvc"    => $data['creditCard']['cvc']
            ],
            "creditCardHolder" => [
                "type" => $data['creditCardHolder']['type'],
                "name" => $data['creditCardHolder']['name'],
                "cpfCnpj" => $data['creditCardHolder']['cpfCnpj'],
                "email" => $data['creditCardHolder']['email'],
                "cellphone" => $data['creditCardHolder']['cellphone'],
                "address" => [
                    "street" => "Praça da Sé",
                    "number" => "1",
                    "zone"   => "Sé",
                    "city"   => "São Paulo",
                    "state"  => "SP",
                    "zip"    => "01001000"
                ],
            ],        
            "async" => false
        ];
        
        try {
            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => 'Bearer ' . env('LYTEX_API_TOKEN')
            ];

            \Log::info("==== ENVIANDO PARA LYTEX ====");
            \Log::info("Headers enviados:", $headers);
            \Log::info("Payload enviado:", $payload);

            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->post("https://api-pay.lytex.com.br/v2/invoices", $payload);

            \Log::info("==== RESPOSTA LYTEX ====");
            \Log::info("Status:", [$res->status()]);
            \Log::info("Headers:", $res->headers());
            \Log::info("Body:", [$res->body()]);

            if ($res->failed()) {
                return response()->json([
                    "status" => $res->status(),
                    "error" => $res->json() ?? $res->body(),
                    "message" => $res->status() == 401 ? "Credenciais inválidas" : "Erro ao criar invoice"
                ], $res->status());
            }

            return response()->json([
                "status" => $res->status(),
                "body" => $res->json()
            ]);
        } catch (\Exception $e) {
            \Log::error("Erro de conexão:", [$e->getMessage()]);

            return response()->json([
                "status" => 500,
                "error" => $e->getMessage(),
                "message" => "Erro de conexão ou interno"
            ], 500);
        }
    }
}
