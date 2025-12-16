<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;
use App\Models\DadosSusanPetRescue;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'geral');

        $model = match ($tab) {
            'susan' => DadosSusanPetRescue::class,
            default => Dados::class
        };

        $query = $model::query();

        // =======================
        // FILTRO DE DATA
        // =======================
        if ($request->filled('data')) {

            // período marcado → data até data_fim
            if ($request->boolean('periodo') && $request->filled('data_fim')) {

                $query->where('created_at', '>=', $request->data . ' 00:00:00')
                    ->where('created_at', '<=', $request->data_fim . ' 23:59:59');

            } else {
                // dia único
                $query->whereDate('created_at', $request->data);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('cpf', 'like', "%{$search}%")
                ->orWhere('amount', 'like', "%{$search}%");
            });
        }
        
        $totaisFiltrados = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents
            ")
            ->first();

        $totaisGerais = $model::selectRaw("
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents
            ")
            ->first();

        $dados = $query
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        return view ('dashboard.index', compact('dados', 'totaisFiltrados', 'totaisGerais', 'tab'));
    }
}
