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
    private const PAGE_URL_FILTERS = [
        [
            'label' => 'siulsanresgate.org',
            'pattern' => 'siulsanresgate.org',
            'excludes' => [],
            'tabs' => ['geral'],
        ],
        [
            'label' => 'sosanimalhelp.org',
            'pattern' => 'sosanimalhelp.org',
            'excludes' => [],
            'tabs' => ['geral', 'susan'],
        ],
        [
            'label' => 'susanpetrescue.org/about-us',
            'pattern' => 'susanpetrescue.org/about-us',
            'excludes' => [],
            'tabs' => ['susan'],
        ],
        [
            'label' => 'susanpetrescue.org',
            'pattern' => 'susanpetrescue.org',
            'excludes' => ['susanpetrescue.org/about-us'],
            'tabs' => ['susan'],
        ],
    ];

    public function handle(Request $request): array
    {
        $tab = (string) $request->get('tab', 'susan');
        $model = $this->resolveModel($tab);

        // 1) BASE (data/período + horário + search)
        $base = $model::query();
        $this->applyDateTimeFilter($base, $request);
        $this->applySearch($base, $request, $tab);

        // 2) filtros (status/method/amount)
        $filters = $this->parseFilters($request, $tab);

        // 3) facets
        [$statusOptions, $statusCounts, $methodOptions, $methodCounts, $amountOptionsCents, $amountCounts, $pageUrlCounts, $popupBackredirectOptions, $popupBackredirectCounts]
            = $this->buildFacets($base, $filters, $tab);

        // 4) query final
        $query = $this->applyFilters(clone $base, $filters, null, $tab);

        $totaisFiltrados = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(status) = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN amount_cents ELSE 0 END), 0) AS failed_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'initiate_checkout' THEN 1 ELSE 0 END), 0) AS initiate_count,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count
            ")
            ->first();

        // (mantive sua lógica: totais gerais sem base/filtro)
        $totaisGerais = $model::query()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(status) = 'initiate_checkout' THEN amount_cents ELSE 0 END), 0) AS initiate_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'paid' THEN amount_cents ELSE 0 END), 0) AS paid_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN amount_cents ELSE 0 END), 0) AS failed_cents,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'initiate_checkout' THEN 1 ELSE 0 END), 0) AS initiate_count,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count
            ")
            ->first();

        $dados = $query
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        $usdBrlAvg7d = ($tab === 'susan') ? $this->usdBrlAvg7d() : null;

        $allowedPageUrlTab = in_array($tab, ['susan', 'geral'], true);
        $pageUrlOptions = $allowedPageUrlTab ? $this->getPageUrlOptionLabels($tab) : [];
        if (!$allowedPageUrlTab) {
            $pageUrlCounts = [];
        }

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
            'usdBrlAvg7d',
            'pageUrlOptions',
            'pageUrlCounts',
            'popupBackredirectOptions',
            'popupBackredirectCounts'
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
                ->orWhere('pix_key', 'like', "%{$search}%")
                ->orWhere('page_url', 'like', "%{$search}%")
                ->orWhere('method', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('cpf', 'like', "%{$search}%");

            if ($tab === 'susan') {
                $qq->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhere('give_payment_id', 'like', "%{$search}%");
            }
        });
    }

    private function parseFilters(Request $request, string $tab): array
    {
        $statusAll = $request->boolean('status_all');
        $methodAll = $request->boolean('method_all');
        $amountAll = $request->boolean('amount_all');
        $pageUrlAll = $request->boolean('page_url_all');
        $popupBackredirectAll = $request->boolean('popup_backredirect_all');

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

        $selectedPageUrls = array_values(array_filter(
            (array) $request->input('page_url_in', []),
            fn($v) => is_string($v) && $this->isValidPageUrlOption($v, $tab)
        ));

        $selectedPopupBackredirect = array_values(array_filter(
            (array) $request->input('popup_backredirect_in', []),
            fn($v) => $v !== null && $v !== ''
        ));
        $selectedPopupBackredirect = array_map('intval', $selectedPopupBackredirect);

        $useStatusFilter = $request->boolean('f_status') || !empty($selectedStatus);
        $useMethodFilter = $request->boolean('f_method') || !empty($selectedMethod);
        $useAmountFilter = $request->boolean('f_amount') || !empty($selectedAmounts);
        $allowedPageUrlTab = in_array($tab, ['susan', 'geral'], true);
        $usePageUrlFilter = $allowedPageUrlTab && ($request->boolean('f_page_url') || !empty($selectedPageUrls));
        $allowedPopupBackredirectTab = $tab === 'susan';
        $usePopupBackredirectFilter = $allowedPopupBackredirectTab && ($request->boolean('f_popup_backredirect') || !empty($selectedPopupBackredirect));

        return compact(
            'statusAll',
            'methodAll',
            'amountAll',
            'pageUrlAll',
            'popupBackredirectAll',
            'selectedStatus',
            'selectedMethod',
            'selectedAmounts',
            'selectedPageUrls',
            'selectedPopupBackredirect',
            'useStatusFilter',
            'useMethodFilter',
            'useAmountFilter',
            'usePageUrlFilter',
            'usePopupBackredirectFilter',
        );
    }

    private function applyFilters(Builder $q, array $f, ?string $skip = null, string $tab = 'susan'): Builder
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

        if (
            in_array($tab, ['susan', 'geral'], true) &&
            $skip !== 'page_url' &&
            $f['usePageUrlFilter'] &&
            !$f['pageUrlAll'] &&
            !empty($f['selectedPageUrls'])
        ) {
            $q->where(function (Builder $qq) use ($f, $tab) {
                foreach ($f['selectedPageUrls'] as $label) {
                    $this->applyPageUrlMatch($qq, $label, $tab);
                }
            });
        }

        if (
            $tab === 'susan' &&
            $skip !== 'popup_backredirect' &&
            $f['usePopupBackredirectFilter'] &&
            !$f['popupBackredirectAll'] &&
            !empty($f['selectedPopupBackredirect'])
        ) {
            $q->whereIn('popup_5dol', $f['selectedPopupBackredirect']);
        }

        return $q;
    }

    private function buildFacets(Builder $base, array $filters, string $tab): array
    {
        // STATUS
        $statusFacetBase = $this->applyFilters(clone $base, $filters, 'status', $tab);
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
        $methodFacetBase = $this->applyFilters(clone $base, $filters, 'method', $tab);
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
        $amountFacetBase = $this->applyFilters(clone $base, $filters, 'amount', $tab);
        $amountAgg = $amountFacetBase
            ->select('amount_cents', DB::raw('COUNT(*) as total'))
            ->whereNotNull('amount_cents')
            ->where('amount_cents', '>', 0)
            ->groupBy('amount_cents')
            ->orderBy('amount_cents')
            ->get();

        $amountOptionsCents = $amountAgg->pluck('amount_cents')->map(fn($v) => (int) $v)->values()->all();
        $amountCounts       = $amountAgg->pluck('total', 'amount_cents')->toArray();

        $pageUrlCounts = [];
        if (in_array($tab, ['susan', 'geral'], true)) {
            $pageUrlFacetBase = $this->applyFilters(clone $base, $filters, 'page_url', $tab);
            foreach ($this->getPageUrlFiltersForTab($tab) as $option) {
                $cloned = clone $pageUrlFacetBase;
                $this->applyPageUrlMatch($cloned, $option['label'], $tab);
                $pageUrlCounts[$option['label']] = $cloned->count();
            }
        }

        $popupBackredirectOptions = [];
        $popupBackredirectCounts = [];
        if ($tab === 'susan') {
            $popupFacetBase = $this->applyFilters(clone $base, $filters, 'popup_backredirect', $tab);
            foreach (['1', '0'] as $value) {
                $cloned = clone $popupFacetBase;
                $cloned->where('popup_5dol', (int) $value);
                $popupBackredirectCounts[$value] = $cloned->count();
            }
            $popupBackredirectOptions = ['1', '0'];
        }

        return [$statusOptions, $statusCounts, $methodOptions, $methodCounts, $amountOptionsCents, $amountCounts, $pageUrlCounts, $popupBackredirectOptions, $popupBackredirectCounts];
    }

    private function isValidPageUrlOption(string $label, string $tab): bool
    {
        return $this->getPageUrlOptionConfig($label, $tab) !== null;
    }

    private function getPageUrlFiltersForTab(string $tab): array
    {
        return array_values(array_filter(self::PAGE_URL_FILTERS, fn($option) => in_array($tab, $option['tabs'], true)));
    }

    private function getPageUrlOptionConfig(string $label, string $tab): ?array
    {
        foreach ($this->getPageUrlFiltersForTab($tab) as $option) {
            if ($option['label'] === $label) {
                return $option;
            }
        }

        return null;
    }

    private function getPageUrlOptionLabels(string $tab): array
    {
        return array_column($this->getPageUrlFiltersForTab($tab), 'label');
    }

    private function applyPageUrlMatch(Builder $q, string $label, string $tab): Builder
    {
        $config = $this->getPageUrlOptionConfig($label, $tab);
        if (!$config) {
            return $q;
        }

        $q->where('page_url', 'like', "%{$config['pattern']}%");
        foreach ($config['excludes'] as $exclude) {
            $q->where('page_url', 'not like', "%{$exclude}%");
        }

        return $q;
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
