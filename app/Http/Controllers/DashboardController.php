<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dados;
use App\Models\DadosSusanPetRescue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'geral');

        $model = match ($tab) {
            'susan' => DadosSusanPetRescue::class,
            default => Dados::class
        };

        // ==================================================
        // 1) BASE QUERY (data/período + search)
        // ==================================================
        $base = $model::query();

        $useRange = $request->boolean('periodo');

        if ($useRange) {
            $start = $request->input('data');
            $end   = $request->input('data_fim');

            if ($start || $end) {
                if (!$start) $start = $end;
                if (!$end)   $end   = $start;

                $base->where('created_at', '>=', $start . ' 00:00:00')
                    ->where('created_at', '<=', $end   . ' 23:59:59');
            }
        } else {
            if ($request->filled('data')) {
                $base->whereDate('created_at', $request->data);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $base->where(function ($q) use ($search, $tab) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")                    
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");

                if ($tab === 'susan') {
                    $q->orWhere('external_id', 'like', "%{$search}%")
                        ->orWhere('give_payment_id', 'like', "%{$search}%");
                }
            });
        }

        // ==================================================
        // 2) Ler filtros selecionados (status/method/amount)
        // ==================================================
        $statusAll = $request->boolean('status_all');
        $methodAll = $request->boolean('method_all');
        $amountAll = $request->boolean('amount_all');

        $selectedStatus = array_values(array_filter(
            (array) $request->input('status_in', []),
            fn($v) => $v !== null && $v !== ''
        ));

        $selectedMethod = array_values(array_filter(
            (array) $request->input('method_in', []),
            fn($v) => $v !== null && $v !== ''
        ));

        $selectedAmounts = array_values(array_filter(
            (array) $request->input('amount_in', []),
            fn($v) => is_numeric($v)
        ));
        $selectedAmounts = array_map('intval', $selectedAmounts);

        // Compatibilidade: só filtra se o toggle estiver marcado OU se tiver seleção
        $useStatusFilter = $request->boolean('f_status') || !empty($selectedStatus);
        $useMethodFilter = $request->boolean('f_method') || !empty($selectedMethod);
        $useAmountFilter = $request->boolean('f_amount') || !empty($selectedAmounts);

        // Aplica filtros selecionados (com opção de "pular" um filtro ao calcular facet)
        $applyFilters = function ($q, ?string $skip = null) use (
            $useStatusFilter,
            $useMethodFilter,
            $useAmountFilter,
            $statusAll,
            $methodAll,
            $amountAll,
            $selectedStatus,
            $selectedMethod,
            $selectedAmounts
        ) {
            if ($skip !== 'status' && $useStatusFilter && !$statusAll && !empty($selectedStatus)) {
                $q->whereIn('status', $selectedStatus);
            }

            if ($skip !== 'method' && $useMethodFilter && !$methodAll && !empty($selectedMethod)) {
                $q->whereIn('method', $selectedMethod);
            }

            if ($skip !== 'amount' && $useAmountFilter && !$amountAll && !empty($selectedAmounts)) {
                $q->whereIn('amount_cents', $selectedAmounts);
            }

            return $q;
        };

        // ==================================================
        // 3) FACETS (COUNTS) considerando os outros filtros
        // ==================================================
        // STATUS: considera method + amount (e base), mas não aplica status
        $statusFacetBase = $applyFilters(clone $base, 'status');
        $statusAgg = $statusFacetBase
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $statusOptions = $statusAgg->pluck('status')->values()->all();
        $statusCounts  = $statusAgg->pluck('total', 'status')->toArray();

        // METHOD: considera status + amount (e base), mas não aplica method
        $methodFacetBase = $applyFilters(clone $base, 'method');
        $methodAgg = $methodFacetBase
            ->select('method', DB::raw('COUNT(*) as total'))
            ->whereNotNull('method')
            ->where('method', '<>', '')
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $methodOptions = $methodAgg->pluck('method')->values()->all();
        $methodCounts  = $methodAgg->pluck('total', 'method')->toArray();

        // AMOUNT: considera status + method (e base), mas não aplica amount
        $amountFacetBase = $applyFilters(clone $base, 'amount');
        $amountAgg = $amountFacetBase
            ->select('amount_cents', DB::raw('COUNT(*) as total'))
            ->whereNotNull('amount_cents')
            ->where('amount_cents', '>', 0)
            ->groupBy('amount_cents')
            ->orderBy('amount_cents')
            ->get();

        $amountOptionsCents = $amountAgg->pluck('amount_cents')->map(fn($v) => (int) $v)->values()->all();
        $amountCounts       = $amountAgg->pluck('total', 'amount_cents')->toArray();

        // ==================================================
        // 4) QUERY FINAL (lista/totais) com TODOS os filtros
        // ==================================================
        $query = $applyFilters(clone $base, null);

        $totaisFiltrados = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents,
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN 1 ELSE 0 END), 0) AS initiate_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
            ")
            ->first();

        $totaisGerais = $model::query()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents,
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN 1 ELSE 0 END), 0) AS initiate_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
            ")
            ->first();

        $dados = $query
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        $usdBrlAvg7d = null;

        // só faz sentido pro tab susan (USD -> BRL)
        if ($tab === 'susan') {
            $usdBrlAvg7d = Cache::remember('usd_brl_avg7d', 60 * 60, function () {
                try {
                    // últimos 7 dias corridos (a API retorna só dias úteis)
                    $end   = Carbon::now('America/Sao_Paulo');
                    $start = (clone $end)->subDays(6);

                    $startStr = $start->format('m-d-Y');
                    $endStr   = $end->format('m-d-Y');

                    $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/"
                        . "CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)"
                        . "?@dataInicial='{$startStr}'&@dataFinalCotacao='{$endStr}'&\$format=json&\$select=cotacaoVenda,dataHoraCotacao";

                    $json = Http::timeout(10)->get($url)->json();
                    $rows = $json['value'] ?? [];

                    if (empty($rows)) return null;

                    $avg = collect($rows)->pluck('cotacaoVenda')->filter()->avg();
                    if (!$avg) return null;

                    return round((float) $avg, 4);
                } catch (\Throwable $e) {
                    return null;
                }
            });
        }

        return view('dashboard.index', compact(
            'dados',
            'totaisFiltrados',
            'totaisGerais',
            'tab',
            'statusOptions',
            'methodOptions',
            'amountOptionsCents',
            'statusCounts',
            'methodCounts',
            'amountCounts',
            'usdBrlAvg7d'
        ));
    }
}
