@extends('main')

@section('title', 'Dashboard')

@section('section')
    <div class="container-fluid" id="email_tracking_index">
        <div class="row">
            <div class="col-12 px-0">
                <div class="card" style="min-height: 100vh">
                    <div class="card-body px-0">
                        @php
                            $useRange = request()->boolean('periodo');
                        @endphp

                        <div class="d-flex align-items-center justify-content-between mb-3 px-3">
                            <div>
                                <div class="h5 mb-0">Email Tracking</div>
                                <div class="small text-muted">Acompanhe aberturas e cliques dos emails enviados</div>
                            </div>

                            <a href="{{ route('dashboard.index') }}" class="btn btn-outline-light btn-sm">
                                Voltar
                            </a>
                        </div>

                        <form method="get" class="mb-3 px-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data" class="form-control" value="{{ request('data') }}">
                                </div>

                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="periodo" value="1"
                                            id="chkPeriodo" {{ $useRange ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkPeriodo">Período</label>
                                    </div>
                                </div>

                                <div class="col-md-3" id="col-data-fim" style="{{ $useRange ? '' : 'display:none;' }}">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control"
                                        value="{{ request('data_fim') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Pesquisar</label>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Email, external_id, token, subject..." value="{{ request('search') }}">
                                </div>

                                <div class="col-md-1 d-grid">
                                    <button class="btn btn-primary">Buscar</button>
                                </div>
                            </div>
                        </form>

                        @if (isset($totaisGerais, $totaisFiltrados))
                            <div class="row g-3 mb-3 px-3">
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Emails (GERAL)</div>
                                            <div class="fw-bold">{{ (int) ($totaisGerais->total ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Opens (GERAL)</div>
                                            <div class="fw-bold">{{ (int) ($totaisGerais->opens ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Clicks (GERAL)</div>
                                            <div class="fw-bold">{{ (int) ($totaisGerais->clicks ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Emails (FILTRO)</div>
                                            <div class="fw-bold">{{ (int) ($totaisFiltrados->total ?? 0) }}</div>
                                            <div class="small text-muted">baseado nos resultados atuais</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Opens (FILTRO)</div>
                                            <div class="fw-bold">{{ (int) ($totaisFiltrados->opens ?? 0) }}</div>
                                            <div class="small text-muted">baseado nos resultados atuais</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Clicks (FILTRO)</div>
                                            <div class="fw-bold">{{ (int) ($totaisFiltrados->clicks ?? 0) }}</div>
                                            <div class="small text-muted">baseado nos resultados atuais</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive px-3">
                            <table class="table table-striped table-hover align-middle" id="tabelaEmails">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>sent_at</th>
                                        <th>external_id</th>
                                        <th>to_email</th>
                                        <th>subject</th>
                                        <th>open_count</th>
                                        <th>first_opened_at</th>
                                        <th>last_opened_at</th>
                                        <th>click_count</th>
                                        <th>first_clicked_at</th>
                                        <th>last_clicked_at</th>
                                        <th>token</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($messages as $index => $m)
                                        @php
                                            $rowId = 'events_' . $m->id;
                                        @endphp

                                        <tr>
                                            <td>{{ $messages->firstItem() + $index }}</td>
                                            <td>{{ $m->sent_at ?? 'N/A' }}</td>
                                            <td>{{ $m->external_id ?? 'N/A' }}</td>
                                            <td>{{ $m->to_email ?? 'N/A' }}</td>
                                            <td style="max-width:260px;white-space:nowrap;">
                                                {{ $m->subject ?? 'N/A' }}
                                            </td>
                                            <td class="{{ (int) $m->open_count > 0 ? 'text-success fw-bold' : '' }}">
                                                {{ (int) ($m->open_count ?? 0) }}
                                            </td>
                                            <td>{{ $m->first_opened_at ?? '—' }}</td>
                                            <td>{{ $m->last_opened_at ?? '—' }}</td>
                                            <td class="{{ (int) $m->click_count > 0 ? 'text-success fw-bold' : '' }}">
                                                {{ (int) ($m->click_count ?? 0) }}
                                            </td>
                                            <td>{{ $m->first_clicked_at ?? '—' }}</td>
                                            <td>{{ $m->last_clicked_at ?? '—' }}</td>
                                            <td style="white-space:nowrap;">
                                                <span class="token-novo">{{ $m->token }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                            <div class="text-muted small">
                                @if ($messages->total() > 0)
                                    Mostrando {{ $messages->firstItem() }}–{{ $messages->lastItem() }} de
                                    {{ $messages->total() }} registro(s)
                                @else
                                    Nenhum registro encontrado.
                                @endif
                            </div>

                            <div class="pagination-wrapper d-flex justify-content-end mt-3">
                                {{ $messages->onEachSide(1)->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/email_tracking_dashboard.css') }}">
@endpush

@push('scripts')
    <script>
        (function() {
            const chk = document.getElementById('chkPeriodo');
            const colFim = document.getElementById('col-data-fim');
            if (!chk || !colFim) return;
            chk.addEventListener('change', () => {
                colFim.style.display = chk.checked ? '' : 'none';
            });
        })();
    </script>
@endpush
