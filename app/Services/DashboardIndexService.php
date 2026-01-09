<?php

namespace App\Services;

use App\Models\Dados;
use App\Models\DadosSusanPetRescue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DashboardIndexService
{
    public function handle(Request $request): array
    {
        $tab = (string) $request->get('tab', 'geral');
        $model = $this->resolveModel($tab);

        // 1) BASE (data/período + horário + search)
        $base = $model::query();
        $this->applyDateTimeFilter($base, $request);
        $this->applySearch($base, $request, $tab);

        // 2) filtros (status/method/amount)
        $filters = $this->parseFilters($request);

        // 3) facets
        [$statusOptions, $statusCounts, $methodOptions, $methodCounts, $amountOptionsCents, $amountCounts]
            = $this->buildFacets($base, $filters);

        // 4) query final
        $query = $this->applyFilters(clone $base, $filters, null);

        $totaisFiltrados = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents,
                COALESCE(SUM(CASE WHEN status = 'initiate_checkout' THEN 1 ELSE 0 END), 0) AS initiate_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
            ")
            ->first();

        // (mantive sua lógica: totais gerais sem base/filtro)
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

        $usdBrlAvg7d = ($tab === 'susan') ? $this->usdBrlAvg7d() : null;

        return compact(
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
        );
    }

    private function resolveModel(string $tab): string
    {
        return match ($tab) {
            'susan' => DadosSusanPetRescue::class,
            default => Dados::class,
        };
    }

    private function applyDateTimeFilter(Builder $q, Request $request): void
    {
        $useRange = $request->boolean('periodo');

        $horaIni = $request->input('hora_ini') ?: '00:00';
        $horaFim = $request->input('hora_fim') ?: '23:59';

        if ($useRange) {
            $start = $request->input('data');
            $end   = $request->input('data_fim');

            if (!$start && !$end) return;

            if (!$start) $start = $end;
            if (!$end)   $end   = $start;

            $dtStart = Carbon::createFromFormat('Y-m-d H:i', $start.' '.$horaIni)->startOfMinute();
            $dtEnd   = Carbon::createFromFormat('Y-m-d H:i', $end.' '.$horaFim)->endOfMinute();

            if ($start === $end && $dtEnd->lt($dtStart)) {
                $dtEnd->addDay();
            }

            $q->whereBetween('created_at', [$dtStart, $dtEnd]);
            return;
        }

        if ($request->filled('data')) {
            $dtStart = Carbon::createFromFormat('Y-m-d H:i', $request->data.' '.$horaIni)->startOfMinute();
            $dtEnd   = Carbon::createFromFormat('Y-m-d H:i', $request->data.' '.$horaFim)->endOfMinute();

            if ($dtEnd->lt($dtStart)) {
                $dtEnd->addDay();
            }

            $q->whereBetween('created_at', [$dtStart, $dtEnd]);
        }
    }

    private function applySearch(Builder $q, Request $request, string $tab): void
    {
        if (!$request->filled('search')) return;

        $search = trim((string) $request->search);

        $q->where(function ($qq) use ($search, $tab) {
            $qq->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('cpf', 'like', "%{$search}%");

            if ($tab === 'susan') {
                $qq->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhere('give_payment_id', 'like', "%{$search}%");
            }
        });
    }

    private function parseFilters(Request $request): array
    {
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

        $useStatusFilter = $request->boolean('f_status') || !empty($selectedStatus);
        $useMethodFilter = $request->boolean('f_method') || !empty($selectedMethod);
        $useAmountFilter = $request->boolean('f_amount') || !empty($selectedAmounts);

        return compact(
            'statusAll',
            'methodAll',
            'amountAll',
            'selectedStatus',
            'selectedMethod',
            'selectedAmounts',
            'useStatusFilter',
            'useMethodFilter',
            'useAmountFilter',
        );
    }

    private function applyFilters(Builder $q, array $f, ?string $skip = null): Builder
    {
        if ($skip !== 'status' && $f['useStatusFilter'] && !$f['statusAll'] && !empty($f['selectedStatus'])) {
            $q->whereIn('status', $f['selectedStatus']);
        }

        if ($skip !== 'method' && $f['useMethodFilter'] && !$f['methodAll'] && !empty($f['selectedMethod'])) {
            $q->whereIn('method', $f['selectedMethod']);
        }

        if ($skip !== 'amount' && $f['useAmountFilter'] && !$f['amountAll'] && !empty($f['selectedAmounts'])) {
            $q->whereIn('amount_cents', $f['selectedAmounts']);
        }

        return $q;
    }

    private function buildFacets(Builder $base, array $filters): array
    {
        // STATUS
        $statusFacetBase = $this->applyFilters(clone $base, $filters, 'status');
        $statusAgg = $statusFacetBase
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $statusOptions = $statusAgg->pluck('status')->values()->all();
        $statusCounts  = $statusAgg->pluck('total', 'status')->toArray();

        // METHOD
        $methodFacetBase = $this->applyFilters(clone $base, $filters, 'method');
        $methodAgg = $methodFacetBase
            ->select('method', DB::raw('COUNT(*) as total'))
            ->whereNotNull('method')
            ->where('method', '<>', '')
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $methodOptions = $methodAgg->pluck('method')->values()->all();
        $methodCounts  = $methodAgg->pluck('total', 'method')->toArray();

        // AMOUNT
        $amountFacetBase = $this->applyFilters(clone $base, $filters, 'amount');
        $amountAgg = $amountFacetBase
            ->select('amount_cents', DB::raw('COUNT(*) as total'))
            ->whereNotNull('amount_cents')
            ->where('amount_cents', '>', 0)
            ->groupBy('amount_cents')
            ->orderBy('amount_cents')
            ->get();

        $amountOptionsCents = $amountAgg->pluck('amount_cents')->map(fn($v) => (int) $v)->values()->all();
        $amountCounts       = $amountAgg->pluck('total', 'amount_cents')->toArray();

        return [$statusOptions, $statusCounts, $methodOptions, $methodCounts, $amountOptionsCents, $amountCounts];
    }

    private function usdBrlAvg7d(): ?float
    {
        return Cache::remember('usd_brl_avg7d', 60 * 60, function () {
            try {
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
}