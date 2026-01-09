@extends('main')

@section('title', 'Dashboard')

@section('section')
    <div class="container-fluid" id="dashboard_index">
        <div class="row">
            <div class="col-12 px-0">
                <div class="card" style="min-height: 100vh">
                    <div class="card-body px-0">
                        @php
                            $baseQuery = request()->except('page');
                            $currentTab = $tab ?? request('tab', 'geral');
                            $currency = $currentTab === 'susan' ? '$' : 'R$';
                        @endphp

                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item">
                                <a class="nav-link {{ ($tab ?? request('tab', 'geral')) === 'geral' ? 'active' : '' }}"
                                    href="{{ route('dashboard.index', array_merge($baseQuery, ['tab' => 'geral'])) }}">
                                    Siulsan Resgate
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ ($tab ?? request('tab', 'geral')) === 'susan' ? 'active' : '' }}"
                                    href="{{ route('dashboard.index', array_merge($baseQuery, ['tab' => 'susan'])) }}">
                                    Susan Pet Rescue
                                </a>
                            </li>
                        </ul>

                        <form method="get" class="mb-3">
                            <input type="hidden" name="tab" value="{{ $tab ?? request('tab', 'geral') }}">

                            @php
                                $useRange = request()->boolean('periodo');

                                $selectedStatus = (array) request('status_in', []);
                                $selectedMethod = (array) request('method_in', []);
                                $selectedAmount = (array) request('amount_in', []);

                                $useStatusFilter = request()->boolean('f_status') || !empty($selectedStatus);
                                $useMethodFilter = request()->boolean('f_method') || !empty($selectedMethod);
                                $useAmountFilter = request()->boolean('f_amount') || !empty($selectedAmount);

                                $statusAll = request()->boolean('status_all');
                                $methodAll = request()->boolean('method_all');
                                $amountAll = request()->boolean('amount_all');

                                $currency = ($tab ?? request('tab', 'geral')) === 'susan' ? '$' : 'R$';
                            @endphp

                            <div class="row g-2 align-items-end">
                                {{-- Data base --}}
                                <div class="col-md-2">
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data" class="form-control" value="{{ request('data') }}">
                                </div>

                                {{-- Hora início --}}
                                <div class="col-md-2">
                                    <label class="form-label">Hora início</label>
                                    <input type="time" name="hora_ini" class="form-control"
                                        value="{{ request('hora_ini') }}">
                                </div>

                                {{-- Hora fim --}}
                                <div class="col-md-2">
                                    <label class="form-label">Hora fim</label>
                                    <input type="time" name="hora_fim" class="form-control"
                                        value="{{ request('hora_fim') }}">
                                </div>

                                {{-- Checkbox período --}}
                                <div class="col-md-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="periodo" value="1"
                                            id="chkPeriodo" {{ $useRange ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkPeriodo">Período</label>
                                    </div>
                                </div>

                                {{-- Data fim (só se período) --}}
                                <div class="col-md-2" id="col-data-fim" style="{{ $useRange ? '' : 'display:none;' }}">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control"
                                        value="{{ request('data_fim') }}">
                                </div>

                                {{-- Busca --}}
                                <div class="col-md-2">
                                    <label class="form-label">Pesquisar</label>
                                    <input type="text" name="search" class="form-control" placeholder="Buscar..."
                                        value="{{ request('search') }}">
                                </div>

                                <div class="col-md-1 d-grid">
                                    <button class="btn btn-primary">Buscar</button>
                                </div>
                            </div>

                            <div class="row g-2 mt-3">
                                <div class="col-12 d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="f_status" value="1"
                                            id="chkStatusFilter" {{ $useStatusFilter ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkStatusFilter">Filtrar status</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="f_method" value="1"
                                            id="chkMethodFilter" {{ $useMethodFilter ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkMethodFilter">Filtrar method</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="f_amount" value="1"
                                            id="chkAmountFilter" {{ $useAmountFilter ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkAmountFilter">Filtrar amount</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-end mt-2" id="wrapStatus"
                                style="{{ $useStatusFilter ? '' : 'display:none;' }}">
                                <div class="col-12" id="col-status">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="status_all"
                                                value="1" id="chkStatusAll"
                                                {{ $statusAll || (!$statusAll && empty($selectedStatus)) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="chkStatusAll">Todos</label>
                                        </div>
                                        <div class="small text-muted">Selecione 1+ status para filtrar</div>
                                    </div>

                                    <div class="border rounded p-2" style="max-height:140px; overflow:auto;">
                                        <div class="row g-1">
                                            @foreach ($statusOptions ?? [] as $st)
                                                @php $qtd = (int) (($statusCounts[$st] ?? 0)); @endphp
                                                <div class="col-md-3 col-6">
                                                    <div class="form-check d-flex align-items-center gap-2">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input class="form-check-input chk-status-item"
                                                                type="checkbox" name="status_in[]"
                                                                value="{{ $st }}" id="st_{{ md5($st) }}"
                                                                {{ in_array($st, $selectedStatus, true) ? 'checked' : '' }}>
                                                            <label class="form-check-label mb-0"
                                                                for="st_{{ md5($st) }}">{{ $st }}</label>
                                                        </div>
                                                        <span class="badge bg-secondary">{{ $qtd }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-end mt-2" id="wrapMethod"
                                style="{{ $useMethodFilter ? '' : 'display:none;' }}">
                                <div class="col-12" id="col-method">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="method_all"
                                                value="1" id="chkMethodAll"
                                                {{ $methodAll || (!$methodAll && empty($selectedMethod)) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="chkMethodAll">Todos</label>
                                        </div>
                                        <div class="small text-muted">Selecione 1+ methods para filtrar</div>
                                    </div>

                                    <div class="border rounded p-2" style="max-height:140px; overflow:auto;">
                                        <div class="row g-1">
                                            @foreach ($methodOptions ?? [] as $m)
                                                @php $qtd = (int) (($methodCounts[$m] ?? 0)); @endphp
                                                <div class="col-md-4 col-6">
                                                    <div class="form-check d-flex align-items-center gap-2">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input class="form-check-input chk-method-item"
                                                                type="checkbox" name="method_in[]"
                                                                value="{{ $m }}" id="m_{{ md5($m) }}"
                                                                {{ in_array($m, $selectedMethod, true) ? 'checked' : '' }}>
                                                            <label class="form-check-label mb-0"
                                                                for="m_{{ md5($m) }}">{{ $m }}</label>
                                                        </div>
                                                        <span class="badge bg-secondary">{{ $qtd }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-end mt-2" id="wrapAmount"
                                style="{{ $useAmountFilter ? '' : 'display:none;' }}">
                                <div class="col-12" id="col-amount">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="amount_all"
                                                value="1" id="chkAmountAll"
                                                {{ $amountAll || (!$amountAll && empty($selectedAmount)) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="chkAmountAll">Todos</label>
                                        </div>
                                        <div class="small text-muted">Selecione 1+ valores para filtrar</div>
                                    </div>

                                    <div class="border rounded p-2" style="max-height:160px; overflow:auto;">
                                        <div class="row g-1">
                                            @foreach ($amountOptionsCents ?? [] as $cents)
                                                @php
                                                    $centsInt = (int) $cents;
                                                    $val = number_format($centsInt / 100, 2, ',', '.');
                                                    $qtd =
                                                        (int) ($amountCounts[$centsInt] ??
                                                            ($amountCounts[(string) $centsInt] ?? 0));
                                                @endphp
                                                <div class="col-md-3 col-6">
                                                    <div class="form-check d-flex align-items-center gap-2">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input class="form-check-input chk-amount-item"
                                                                type="checkbox" name="amount_in[]"
                                                                value="{{ $centsInt }}" id="a_{{ $centsInt }}"
                                                                {{ in_array((string) $centsInt, array_map('strval', $selectedAmount), true) ? 'checked' : '' }}>
                                                            <label class="form-check-label mb-0"
                                                                for="a_{{ $centsInt }}">{{ $currency }}
                                                                {{ $val }}</label>
                                                        </div>
                                                        <span class="badge bg-secondary">{{ $qtd }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @if (isset($totaisGerais, $totaisFiltrados))
                            @php
                                $isUSD = ($currency ?? 'R$') === '$';
                                $usdAvg = $usdBrlAvg7d ?? null;

                                $fmtMoney = fn($cents) => number_format(((int) ($cents ?? 0)) / 100, 2, ',', '.');
                                $fmtInt = fn($n) => number_format((int) ($n ?? 0), 0, ',', '.');

                                $toBRL = function ($cents) use ($usdAvg) {
                                    if (!$usdAvg) {
                                        return null;
                                    }
                                    $usd = ((int) ($cents ?? 0)) / 100;
                                    return $usd * (float) $usdAvg;
                                };
                                $fmtBRL = fn($v) => number_format((float) $v, 2, ',', '.');
                            @endphp

                            <div class="row g-3 mb-3">
                                {{-- Totais gerais --}}
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Initiate Checkout (GERAL)</div>

                                            <div class="fw-bold">
                                                {{ $currency }} {{ $fmtMoney($totaisGerais->initiate_cents ?? 0) }}
                                            </div>

                                            <div class="small text-muted">
                                                {{ $fmtInt($totaisGerais->initiate_count ?? 0) }} registro(s)
                                            </div>

                                            @if ($isUSD && $usdAvg)
                                                @php $brl = $toBRL($totaisGerais->initiate_cents ?? 0); @endphp
                                                @if (!is_null($brl))
                                                    <div class="small text-muted">
                                                        ≈ R$ {{ $fmtBRL($brl) }} <span class="opacity-75">(média 7d:
                                                            {{ number_format($usdAvg, 4, ',', '.') }})</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Paid (GERAL)</div>

                                            <div class="fw-bold text-success">
                                                {{ $currency }} {{ $fmtMoney($totaisGerais->paid_cents ?? 0) }}
                                            </div>

                                            <div class="small text-muted">
                                                {{ $fmtInt($totaisGerais->paid_count ?? 0) }} registro(s)
                                            </div>

                                            @if ($isUSD && $usdAvg)
                                                @php $brl = $toBRL($totaisGerais->paid_cents ?? 0); @endphp
                                                @if (!is_null($brl))
                                                    <div class="small text-muted">
                                                        ≈ R$ {{ $fmtBRL($brl) }} <span class="opacity-75">(média 7d:
                                                            {{ number_format($usdAvg, 4, ',', '.') }})</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Totais filtrados --}}
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Initiate Checkout (FILTRO)</div>

                                            <div class="fw-bold">
                                                {{ $currency }} {{ $fmtMoney($totaisFiltrados->initiate_cents ?? 0) }}
                                            </div>

                                            <div class="small text-muted">
                                                {{ $fmtInt($totaisFiltrados->initiate_count ?? 0) }} registro(s) — baseado
                                                nos resultados atuais
                                            </div>

                                            @if ($isUSD && $usdAvg)
                                                @php $brl = $toBRL($totaisFiltrados->initiate_cents ?? 0); @endphp
                                                @if (!is_null($brl))
                                                    <div class="small text-muted">
                                                        ≈ R$ {{ $fmtBRL($brl) }} <span class="opacity-75">(média 7d:
                                                            {{ number_format($usdAvg, 4, ',', '.') }})</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Paid (FILTRO)</div>

                                            <div class="fw-bold text-success">
                                                {{ $currency }} {{ $fmtMoney($totaisFiltrados->paid_cents ?? 0) }}
                                            </div>

                                            <div class="small text-muted">
                                                {{ $fmtInt($totaisFiltrados->paid_count ?? 0) }} registro(s) — baseado nos
                                                resultados atuais
                                            </div>

                                            @if ($isUSD && $usdAvg)
                                                @php $brl = $toBRL($totaisFiltrados->paid_cents ?? 0); @endphp
                                                @if (!is_null($brl))
                                                    <div class="small text-muted">
                                                        ≈ R$ {{ $fmtBRL($brl) }} <span class="opacity-75">(média 7d:
                                                            {{ number_format($usdAvg, 4, ',', '.') }})</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-striped" id="tabelaDados">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>created_at</th>
                                        <th>updated_at</th>
                                        <th>status</th>
                                        <th>amount</th>
                                        <th>amount_cents</th>
                                        <th>first_name</th>
                                        <th>last_name</th>
                                        <th>method</th>
                                        <th>email</th>
                                        <th>phone</th>
                                        <th>cpf</th>
                                        <th>ip</th>
                                        <td>country</td>
                                        <th>event_time</th>
                                        <th>utm_campaign</th>
                                        <th>page_url</th>
                                        <th>client_user_agent</th>
                                        <th>fbp</th>
                                        <th>fbc</th>
                                        <th>fbclid</th>
                                        <th>utm_source</th>
                                        <th>utm_medium</th>
                                        <th>utm_content</th>
                                        <th>utm_term</th>
                                        <th>Chave pix</th>
                                        <th>Descrição pix</th>
                                        @if ($currentTab === 'susan')
                                            <th>external_id</th>
                                            <th>give_payment_id</th>
                                            <th>currency</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dados as $index => $dado)
                                        <tr class="{{ $dado->amount >= 200 ? 'table-success' : '' }}">
                                            <td>{{ $dados->firstItem() + $index }}</td>
                                            <td>{{ $dado->created_at ?? 'N/A' }}</td>
                                            <td>{{ $dado->updated_at ?? 'N/A' }}</td>
                                            <td>{{ $dado->status ?? 'N/A' }}</td>
                                            <td>{{ $dado->amount ?? 'N/A' }}</td>
                                            <td>{{ $dado->amount_cents ?? 'N/A' }}</td>
                                            <td>{{ $dado->first_name ?? 'N/A' }}</td>
                                            <td>{{ $dado->last_name ?? 'N/A' }}</td>
                                            <td>{{ $dado->method ?? 'N/A' }}</td>
                                            <td>{{ $dado->email ?? 'N/A' }}</td>
                                            <td>{{ $dado->phone ?? 'N/A' }}</td>
                                            <td>{{ $dado->cpf ?? 'N/A' }}</td>
                                            <td>{{ $dado->ip ?? 'N/A' }}</td>
                                            <td>{{ $dado->_country ?? 'N/A' }}</td>
                                            <td>{{ $dado->event_time ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_campaign ?? 'N/A' }}</td>
                                            <td>{{ $dado->page_url ?? 'N/A' }}</td>
                                            <td>{{ $dado->client_user_agent ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbp ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbc ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbclid ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_source ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_medium ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_content ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_term ?? 'N/A' }}</td>
                                            <td>{{ $dado->pix_key ?? 'N/A' }}</td>
                                            <td>{{ $dado->pix_description ?? 'N/A' }}</td>
                                            @if ($currentTab === 'susan')
                                                <td>{{ $dado->external_id ?? 'N/A' }}</td>
                                                <td>{{ $dado->give_payment_id ?? 'N/A' }}</td>
                                                <td>{{ $dado->currency ?? 'N/A' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach

                                    <style>
                                        .row-amount-hight {
                                            background-color: yellow;
                                        }
                                    </style>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                @if ($dados->total() > 0)
                                    Mostrando {{ $dados->firstItem() }}–{{ $dados->lastItem() }} de {{ $dados->total() }}
                                    registro(s)
                                @else
                                    Nenhum registro encontrado.
                                @endif
                            </div>

                            <div class="pagination-wrapper d-flex justify-content-end mt-3">
                                {{ $dados->onEachSide(1)->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@push('scripts')
    <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                // =========================
                // 1) PERÍODO (data_fim)
                // =========================
                const chkPeriodo = document.getElementById('chkPeriodo');
                const colFim = document.getElementById('col-data-fim');

                const applyPeriodo = () => {
                    if (!chkPeriodo || !colFim) return;

                    colFim.style.display = chkPeriodo.checked ? '' : 'none';

                    if (!chkPeriodo.checked) {
                        const df = document.querySelector('input[name="data_fim"]');
                        if (df) df.value = '';
                    }
                };

                if (chkPeriodo && colFim) {
                    chkPeriodo.addEventListener('change', applyPeriodo);
                    applyPeriodo();
                }

                // =========================
                // 2) SHOW/HIDE (filtros)
                // =========================
                const bindShowHide = (chkId, colId, onHideClearFn) => {
                    const chk = document.getElementById(chkId);
                    const col = document.getElementById(colId);
                    if (!chk || !col) return;

                    const apply = () => {
                        const show = !!chk.checked;
                        col.style.display = show ? '' : 'none';

                        // se esconder, limpa seleções (opcional)
                        if (!show && typeof onHideClearFn === 'function') {
                            onHideClearFn();
                        }
                    };

                    chk.addEventListener('change', apply);
                    apply();
                };

                // =========================
                // 3) "TODOS" TOGGLE
                // =========================
                const bindAllToggle = (allId, itemsSelector) => {
                    const all = document.getElementById(allId);
                    const items = Array.from(document.querySelectorAll(itemsSelector));
                    if (!all || !items.length) return;

                    const apply = () => {
                        if (all.checked) {
                            items.forEach(i => {
                                i.checked = false;
                                i.disabled = true;
                            });
                        } else {
                            items.forEach(i => {
                                i.disabled = false;
                            });
                        }
                    };

                    // se marcar qualquer item, desmarca "Todos"
                    items.forEach(i => {
                        i.addEventListener('change', () => {
                            if (i.checked) all.checked = false;
                            apply();
                        });
                    });

                    all.addEventListener('change', apply);
                    apply();
                };

                // =========================
                // 4) HELPERS (limpar quando recolhe)
                // =========================
                const clearGroup = (allId, itemsSelector) => {
                    const all = document.getElementById(allId);
                    const items = Array.from(document.querySelectorAll(itemsSelector));

                    // volta para "Todos" marcado (default) e limpa seleções
                    if (all) all.checked = true;
                    items.forEach(i => {
                        i.checked = false;
                        i.disabled = true;
                    });
                };

                // =========================
                // 5) BIND DOS 3 FILTROS
                // =========================
                // A) Status
                bindShowHide('chkStatusFilter', 'wrapStatus', () => clearGroup('chkStatusAll',
                    '.chk-status-item'));
                bindAllToggle('chkStatusAll', '.chk-status-item');

                // B) Method
                bindShowHide('chkMethodFilter', 'wrapMethod', () => clearGroup('chkMethodAll',
                    '.chk-method-item'));
                bindAllToggle('chkMethodAll', '.chk-method-item');

                // C) Amount
                bindShowHide('chkAmountFilter', 'wrapAmount', () => clearGroup('chkAmountAll',
                    '.chk-amount-item'));
                bindAllToggle('chkAmountAll', '.chk-amount-item');
            });
        })();
    </script>
@endpush
