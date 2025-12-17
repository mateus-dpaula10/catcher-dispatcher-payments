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
                            $currentTab = ($tab ?? request('tab','geral'));
                            $currency = $currentTab === 'susan' ? '$' : 'R$';
                        @endphp

                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item">
                                <a class="nav-link {{ ($tab ?? request('tab','geral')) === 'geral' ? 'active' : '' }}"
                                    href="{{ route('dashboard.index', array_merge($baseQuery, ['tab' => 'geral'])) }}">
                                    Siulsan Resgate
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ ($tab ?? request('tab','geral')) === 'susan' ? 'active' : '' }}"
                                    href="{{ route('dashboard.index', array_merge($baseQuery, ['tab' => 'susan'])) }}">
                                    Susan Pet Rescue
                                </a>
                            </li>
                        </ul>
                        
                        <form method="get" class="mb-3">
                            <input type="hidden" name="tab" value="{{ $tab ?? request('tab','geral') }}">

                            @php
                                $useRange = request()->boolean('periodo');
                            @endphp

                            <div class="row g-2 align-items-end">
                                {{-- Data base --}}
                                <div class="col-md-3">
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data" class="form-control"
                                        value="{{ request('data') }}">
                                </div>

                                {{-- Checkbox período --}}
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="periodo" value="1"
                                            id="chkPeriodo" {{ $useRange ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkPeriodo">
                                            Período
                                        </label>
                                    </div>
                                </div>

                                {{-- Data fim (só se período) --}}
                                <div class="col-md-3" id="col-data-fim" style="{{ $useRange ? '' : 'display:none;' }}">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control"
                                        value="{{ request('data_fim') }}">
                                </div>

                                {{-- Busca --}}
                                <div class="col-md-3">
                                    <label class="form-label">Pesquisar</label>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Buscar..." value="{{ request('search') }}">
                                </div>

                                <div class="col-md-1 d-grid">
                                    <button class="btn btn-primary">Buscar</button>
                                </div>
                            </div>
                        </form>

                        @if(isset($totaisGerais, $totaisFiltrados))
                            <div class="row g-3 mb-3">
                                {{-- Totais gerais --}}
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Initiate Checkout (GERAL)</div>
                                            <div class="fw-bold">
                                                {{ $currency }} {{ number_format(($totaisGerais->initiate_cents ?? 0) / 100, 2, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Paid (GERAL)</div>
                                            <div class="fw-bold text-success">
                                                {{ $currency }} {{ number_format(($totaisGerais->paid_cents ?? 0) / 100, 2, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Totais filtrados --}}
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Initiate Checkout (FILTRO)</div>
                                            <div class="fw-bold">
                                                {{ $currency }} {{ number_format(($totaisFiltrados->initiate_cents ?? 0) / 100, 2, ',', '.') }}
                                            </div>
                                            <div class="small text-muted">
                                                baseado nos resultados atuais
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body d-flex flex-column justify-content-center py-2">
                                            <div class="text-muted small">Paid (FILTRO)</div>
                                            <div class="fw-bold text-success">
                                                {{ $currency }} {{ number_format(($totaisFiltrados->paid_cents ?? 0) / 100, 2, ',', '.') }}
                                            </div>
                                            <div class="small text-muted">
                                                baseado nos resultados atuais
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle" id="tabelaDados">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>created_at</th>
                                        <th>updated_at</th>
                                        @if($currentTab === 'susan')
                                            <th>external_id</th>
                                            <th>give_payment_id</th>
                                            <th>currency</th>
                                        @endif
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
                                        <th>event_time</th>
                                        <th>page_url</th>
                                        <th>client_user_agent</th>
                                        <th>fbp</th>
                                        <th>fbc</th>
                                        <th>fbclid</th>
                                        <th>utm_source</th>
                                        <th>utm_campaign</th>
                                        <th>utm_medium</th>
                                        <th>utm_content</th>
                                        <th>utm_term</th>
                                        <th>Chave pix</th>
                                        <th>Descrição pix</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dados as $index => $dado)
                                        <tr>
                                            <td>{{ $dados->firstItem() + $index }}</td>
                                            <td>{{ $dado->created_at ?? 'N/A' }}</td>
                                            <td>{{ $dado->updated_at ?? 'N/A' }}</td>
                                            @if($currentTab === 'susan')
                                                <td>{{ $dado->external_id ?? 'N/A' }}</td>
                                                <td>{{ $dado->give_payment_id ?? 'N/A' }}</td>
                                                <td>{{ $dado->currency ?? 'N/A' }}</td>
                                            @endif
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
                                            <td>{{ $dado->event_time ?? 'N/A' }}</td>
                                            <td>{{ $dado->page_url ?? 'N/A' }}</td>
                                            <td>{{ $dado->client_user_agent ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbp ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbc ?? 'N/A' }}</td>
                                            <td>{{ $dado->fbclid ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_source ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_campaign ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_medium ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_content ?? 'N/A' }}</td>
                                            <td>{{ $dado->utm_term ?? 'N/A' }}</td>
                                            <td>{{ $dado->pix_key ?? 'N/A' }}</td>
                                            <td>{{ $dado->pix_description ?? 'N/A' }}</td>
                                        </tr>                                    
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                @if ($dados->total() > 0)
                                    Mostrando {{ $dados->firstItem() }}–{{ $dados->lastItem() }} de {{ $dados->total() }} registro(s)
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
        (function(){
            const chk = document.getElementById('chkPeriodo');
            const colFim = document.getElementById('col-data-fim');

            if(!chk || !colFim) return;

            chk.addEventListener('change', function(){
                colFim.style.display = this.checked ? '' : 'none';

                if (!this.checked) {
                const df = document.querySelector('input[name="data_fim"]');
                if (df) df.value = '';
                }
            });
        })();
    </script>
@endpush