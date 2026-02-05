@extends('main')

@section('title', 'Ongs')

@section('section')
    <div class="container-fluid" id="ongs_index">
        <div class="row">
            <div class="col-12 px-0">
                <div class="card" style="min-height: 100vh; padding-bottom: 50px">
                    <div class="card-body px-0">
                        <div class="d-flex align-items-center justify-content-between mb-3 px-3">
                            <div>
                                <div class="h5 mb-0">Gestao das ONGS</div>
                                <div class="small text-muted">Visualize e edite as Ongs cadastradas</div>
                            </div>

                            <a href="{{ route('dashboard.index') }}" class="btn btn-outline-light btn-sm">
                                Voltar
                            </a>
                        </div>

                        @if ($ongs->isEmpty())
                            <div class="px-3 py-5 text-center text-muted">
                                Nenhuma ONG cadastrada ainda.
                            </div>
                        @else
                            <div class="table-responsive px-3">
                                <table class="table table-hover align-middle" id="tabelaOngs">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Foto</th>
                                            <th scope="col">Nome</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">CNPJ</th>
                                            <th scope="col">Telefone</th>
                                            <th scope="col">CEP</th>
                                            <th scope="col">Qtd. animais</th>
                                            <th scope="col">Qtd. cuidadores</th>
                                            <th scope="col">Fundação</th>
                                            <th scope="col">Custos</th>
                                            <th scope="col">Criado em</th>
                                            <th scope="col">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($ongs as $index => $ong)
                                            @php
                                                $totalCosts = collect([
                                                    $ong->portion_value,
                                                    $ong->medicines_value,
                                                    $ong->veterinarian_value,
                                                    $ong->collaborators_value,
                                                    $ong->other_costs_value,
                                                ])->filter(fn($value) => !is_null($value))->sum();
                                                $firstPhoto = collect($ong->photo_urls ?? [])->first();
                                                 $editPayload = array_merge($ong->only([
                                                     'name','email','cnpj','cnpj_not_available','phone','animal_count','caregiver_count',
                                                     'description','street','number','complement','district','city','state','zip',
                                                     'facebook','facebook_not_available','instagram','instagram_not_available',
                                                     'website','website_not_available','portion_value','medicines_value',
                                                    'veterinarian_value','collaborators_value','other_costs_value','other_costs_description','photo_urls','source_tag',
                                                 ]), [
                                                     'foundation_date' => optional($ong->foundation_date)->format('Y-m-d'),
                                                 ]);
                                             @endphp
                                            <tr>
                                                <th scope="row">{{ $ongs->firstItem() + $index }}</th>
                                                <td style="width:70px;">
                                                    @if ($firstPhoto)
                                                        <div class="rounded" style="width:48px; height:48px; overflow:hidden; background:#f0f0f0;">
                                                            <img src="{{ $firstPhoto }}" alt="Foto" class="w-100 h-100" style="object-fit:cover;" />
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">--</span>
                                                    @endif
                                                </td>
                                                <td>{{ $ong->name }}</td>
                                                <td>{{ $ong->email }}</td>
                                                <td>{{ $ong->cnpj ?: '--' }}</td>
                                                <td>{{ $ong->phone ?: '--' }}</td>
                                                <td>{{ $ong->zip ?: '--' }}</td>
                                                <td>{{ $ong->animal_count ?? '--' }}</td>
                                                <td>{{ $ong->caregiver_count ?? '--' }}</td>
                                                <td>{{ optional($ong->foundation_date)->format('d/m/Y') ?: '--' }}</td>
                                                <td>R$ {{ number_format($totalCosts, 2, ',', '.') }}</td>
                                                <td>{{ optional($ong->created_at)->format('d/m/Y H:i') }}</td>
                                                <td class="text-nowrap">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-action="edit"
                                                        data-route="{{ route('ongs.update', $ong) }}"
                                                        data-ong='@json($editPayload)'
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#ongEditModal"
                                                    >
                                                        Editar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        data-action="delete"
                                                        data-route="{{ route('ongs.destroy', $ong) }}"
                                                        data-name="{{ $ong->name }}"
                                                    >
                                                        Excluir
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex align-items-center justify-content-between px-3 mt-3">
                                <div class="small text-muted">
                                    Exibindo {{ $ongs->firstItem() ?? 0 }}-{{ $ongs->lastItem() ?? 0 }} de {{ $ongs->total() }} ONG(s)
                                </div>
                                {{ $ongs->withQueryString()->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ongEditModal" tabindex="-1" aria-labelledby="ongEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form id="ong-edit-form" class="needs-validation" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source_tag" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ongEditModalLabel">Editar ONG</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                             <div class="col-md-6">
                                 <label class="form-label">CNPJ</label>
                                 <input type="hidden" name="cnpj_not_available" value="0">
                                 <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0000-00" maxlength="18">
                                 <div id="cnpj-feedback-modal" class="invalid-feedback"></div>
                                 <div class="form-check mt-1">
                                     <input class="form-check-input" type="checkbox" value="1" id="cnpj_not_available_modal" name="cnpj_not_available">
                                     <label class="form-check-label small" for="cnpj_not_available_modal">
                                         Não tenho CNPJ
                                     </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="phone" class="form-control" placeholder="(11) 99999-9999">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Data de Fundacao</label>
                                <input type="date" name="foundation_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantidade de animais</label>
                                <input type="number" min="0" name="animal_count" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantidade de cuidadores</label>
                                <input type="number" min="0" name="caregiver_count" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descricao</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <hr>
                                <div class="fw-semibold">Endereco</div>
                            </div>
                             <div class="col-md-4">
                                 <label class="form-label">CEP</label>
                                 <input type="text" name="zip" class="form-control" placeholder="00000-000" maxlength="9">
                                 <div id="cep-feedback-modal" class="form-text"></div>
                             </div>
                            <div class="col-md-4">
                                <label class="form-label">Rua</label>
                                <input type="text" name="street" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Numero</label>
                                <input type="text" name="number" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complement" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="district" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="city" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                <input type="text" name="state" class="form-control" maxlength="2">
                            </div>
                            <div class="col-12">
                                <hr>
                                <div class="fw-semibold">Redes sociais</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Facebook</label>
                                <input type="hidden" name="facebook_not_available" value="0">
                                <input type="text" name="facebook" class="form-control">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" value="1" id="facebook_not_available" name="facebook_not_available">
                                    <label for="facebook_not_available" class="form-check-label small">Não tenho Facebook</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instagram</label>
                                <input type="hidden" name="instagram_not_available" value="0">
                                <input type="text" name="instagram" class="form-control">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" value="1" id="instagram_not_available" name="instagram_not_available">
                                    <label for="instagram_not_available" class="form-check-label small">Não tenho Instagram</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Website</label>
                                <input type="hidden" name="website_not_available" value="0">
                                <input type="url" name="website" class="form-control">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" value="1" id="website_not_available" name="website_not_available">
                                    <label for="website_not_available" class="form-check-label small">Não tenho Website</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <hr>
                                <div class="fw-semibold">Custos mensais</div>
                            </div>
                             <div class="col-md-6 col-lg-3">
                                 <label class="form-label">Ração</label>
                                 <input type="text" name="portion_value" class="form-control" placeholder="Ex.: R$ 1.200,00" inputmode="decimal">
                             </div>
                             <div class="col-md-6 col-lg-3">
                                 <label class="form-label">Medicamentos</label>
                                 <input type="text" name="medicines_value" class="form-control" placeholder="Ex.: R$ 350,00" inputmode="decimal">
                             </div>
                             <div class="col-md-6 col-lg-3">
                                 <label class="form-label">Veterinário</label>
                                 <input type="text" name="veterinarian_value" class="form-control" placeholder="Ex.: R$ 800,00" inputmode="decimal">
                             </div>
                             <div class="col-md-6 col-lg-3">
                                 <label class="form-label">Colaboradores</label>
                                 <input type="text" name="collaborators_value" class="form-control" placeholder="Ex.: R$ 500,00" inputmode="decimal">
                             </div>
                             <div class="col-md-6 col-lg-3">
                                 <label class="form-label">Outros custos</label>
                                 <input type="text" name="other_costs_value" class="form-control" placeholder="Ex.: R$ 250,00" inputmode="decimal">
                             </div>
                             <div class="col-12">
                                 <label class="form-label">Especifique os outros custos</label>
                                 <textarea name="other_costs_description" class="form-control" rows="2" placeholder="Ex.: aluguel, energia, água, transporte..."></textarea>
                             </div>
                             <div class="col-12">
                                 <hr>
                                 <div class="fw-semibold">Midias</div>
                                 <div class="mt-2">
                                    <label class="form-label mb-1">Fotos atuais</label>
                                    <div id="ongExistingPhotos" class="ong-photo-preview" aria-live="polite"></div>
                                    <input type="hidden" name="photo_urls[]" value="">
                                    <div id="ongExistingPhotosEmpty" class="small text-muted">Nenhuma foto cadastrada.</div>
                                    <div class="small text-muted mt-1">Clique no X para remover. A remoção só será aplicada ao salvar.</div>
                                </div>
                                <hr class="mt-3">
                                <label class="form-label">Adicionar mídias</label>
                                <input type="file" name="photo_files[]" accept="image/*" multiple class="form-control">
                                <div class="small text-muted">Selecione arquivos novos para anexar.</div>
                                <div class="mt-2">
                                    <label class="form-label mb-1">Preview das novas fotos</label>
                                    <div id="ongNewPhotos" class="ong-photo-preview" aria-live="polite"></div>
                                    <div id="ongNewPhotosEmpty" class="small text-muted">Nenhuma foto selecionada.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div id="editModalMessage" class="text-danger small me-auto"></div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ongs.css') }}">
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value ||
                '';

            const editModalEl = document.getElementById('ongEditModal');
            const editModal = editModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(editModalEl) : null;
            const editForm = document.getElementById('ong-edit-form');
            const editMessage = document.getElementById('editModalMessage');
            const existingPhotosList = document.getElementById('ongExistingPhotos');
            const existingPhotosEmpty = document.getElementById('ongExistingPhotosEmpty');
             const newPhotosList = document.getElementById('ongNewPhotos');
             const newPhotosEmpty = document.getElementById('ongNewPhotosEmpty');
             const cnpjFeedback = document.getElementById('cnpj-feedback-modal');
             const cepFeedback = document.getElementById('cep-feedback-modal');

             function registerOptionalField(checkbox, input) {
                 if (!checkbox || !input) return () => {};
                 const apply = () => {
                     const checked = checkbox.checked;
                    input.readOnly = checked;
                    if (checked) {
                        input.value = '';
                    }
                };
                checkbox.addEventListener('change', apply);
                apply();
                 return apply;
             }

             function maskDigits(v) { return (v || '').toString().replace(/\D+/g, ''); }

             function maskPhone(value) {
                 const d = maskDigits(value).slice(0, 11);
                 if (d.length <= 2) return d;
                 if (d.length <= 6) return `(${d.slice(0,2)}) ${d.slice(2)}`;
                 if (d.length <= 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
                 return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
             }

             function maskCnpj(value) {
                 const d = maskDigits(value).slice(0, 14);
                 return d
                     .replace(/^(\d{2})(\d)/, "$1.$2")
                     .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
                     .replace(/\.(\d{3})(\d)/, ".$1/$2")
                     .replace(/(\d{4})(\d)/, "$1-$2");
             }

             function maskCep(value) {
                 const d = maskDigits(value).slice(0, 8);
                 return d.replace(/^(\d{5})(\d)/, "$1-$2");
             }

             function parseBrlMoney(value) {
                 const raw = (value || '').toString().trim();
                 if (!raw) return null;

                 const cleaned = raw.replace(/[^\d.,-]+/g, '');
                 if (!/\d/.test(cleaned)) return null;

                 const hasComma = cleaned.includes(',');
                 const hasDot = cleaned.includes('.');
                 let normalized = cleaned;

                 if (hasComma) {
                     normalized = normalized.replace(/\./g, '').replace(',', '.');
                 } else if (hasDot) {
                     if (/^\d{1,3}(\.\d{3})+$/.test(normalized)) {
                         normalized = normalized.replace(/\./g, '');
                     } else {
                         const parts = normalized.split('.');
                         const last = parts[parts.length - 1] || '';
                         if (parts.length > 2 && last.length === 3) {
                             normalized = parts.join('');
                         } else if (parts.length > 2) {
                             normalized = parts.slice(0, -1).join('') + '.' + last;
                         }
                     }
                 }

                 const n = Number(normalized);
                 return Number.isFinite(n) ? n : null;
             }

             function formatBrlMoney(n) {
                 return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
             }

             function formatPlainPtBrMoney(n) {
                 return Number(n).toFixed(2).replace('.', ',');
             }

             function registerMoneyInput(input) {
                 if (!input) return;

                 input.addEventListener('input', () => {
                     const raw = input.value || '';
                     const cleaned = raw.replace(/[^\d.,]+/g, '');
                     if (cleaned !== raw) input.value = cleaned;
                 });

                 input.addEventListener('focus', () => {
                     const n = parseBrlMoney(input.value);
                     if (n === null) return;
                     input.value = formatPlainPtBrMoney(n);
                 });

                 input.addEventListener('blur', () => {
                     const n = parseBrlMoney(input.value);
                     input.value = n === null ? '' : formatBrlMoney(n);
                 });
             }

             function normalizeMoneyField(formData, fieldName, input) {
                 if (!formData || !fieldName || !input) return;
                 const n = parseBrlMoney(input.value);
                 if (n === null) {
                     formData.delete(fieldName);
                     return;
                 }
                 formData.set(fieldName, n.toFixed(2));
             }

             function renderExistingPhotos(urls) {
                 if (!existingPhotosList) return;

                existingPhotosList.innerHTML = '';

                const list = Array.isArray(urls) ? urls : [];
                const normalized = list
                    .filter(url => typeof url === 'string')
                    .map(url => url.trim())
                    .filter(Boolean);

                if (existingPhotosEmpty) {
                    existingPhotosEmpty.classList.toggle('d-none', normalized.length > 0);
                }

                normalized.forEach(url => {
                    const item = document.createElement('div');
                    item.className = 'ong-photo-item';

                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Foto';
                    img.loading = 'lazy';

                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'photo_urls[]';
                    hidden.value = url;

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'ong-photo-remove';
                    remove.setAttribute('aria-label', 'Remover foto');
                    remove.textContent = 'x';
                    remove.addEventListener('click', () => {
                        item.remove();
                        if (existingPhotosEmpty) {
                            existingPhotosEmpty.classList.toggle('d-none', existingPhotosList.children.length > 0);
                        }
                    });

                    item.appendChild(img);
                    item.appendChild(hidden);
                    item.appendChild(remove);
                    existingPhotosList.appendChild(item);
                });
            }

            const fieldRefs = editForm ? {
                source_tag: editForm.querySelector('[name="source_tag"]'),
                name: editForm.querySelector('[name="name"]'),
                email: editForm.querySelector('[name="email"]'),
                cnpj: editForm.querySelector('[name="cnpj"]'),
                cnpjNotAvailable: editForm.querySelector('#cnpj_not_available_modal'),
                phone: editForm.querySelector('[name="phone"]'),
                foundation_date: editForm.querySelector('[name="foundation_date"]'),
                animal_count: editForm.querySelector('[name="animal_count"]'),
                caregiver_count: editForm.querySelector('[name="caregiver_count"]'),
                description: editForm.querySelector('[name="description"]'),
                street: editForm.querySelector('[name="street"]'),
                number: editForm.querySelector('[name="number"]'),
                complement: editForm.querySelector('[name="complement"]'),
                district: editForm.querySelector('[name="district"]'),
                city: editForm.querySelector('[name="city"]'),
                state: editForm.querySelector('[name="state"]'),
                zip: editForm.querySelector('[name="zip"]'),
                facebook: editForm.querySelector('[name="facebook"]'),
                facebookNotAvailable: editForm.querySelector('[name="facebook_not_available"][type="checkbox"]'),
                instagram: editForm.querySelector('[name="instagram"]'),
                instagramNotAvailable: editForm.querySelector('[name="instagram_not_available"][type="checkbox"]'),
                website: editForm.querySelector('[name="website"]'),
                websiteNotAvailable: editForm.querySelector('[name="website_not_available"][type="checkbox"]'),
                portion_value: editForm.querySelector('[name="portion_value"]'),
                medicines_value: editForm.querySelector('[name="medicines_value"]'),
                veterinarian_value: editForm.querySelector('[name="veterinarian_value"]'),
                 collaborators_value: editForm.querySelector('[name="collaborators_value"]'),
                 other_costs_value: editForm.querySelector('[name="other_costs_value"]'),
                 other_costs_description: editForm.querySelector('[name="other_costs_description"]'),
                 photoFiles: editForm.querySelector('[name="photo_files[]"]'),
             } : {};

             [
                 fieldRefs.portion_value,
                 fieldRefs.medicines_value,
                 fieldRefs.veterinarian_value,
                 fieldRefs.collaborators_value,
                 fieldRefs.other_costs_value,
             ].forEach(registerMoneyInput);

             if (fieldRefs.phone) {
                 fieldRefs.phone.addEventListener('input', () => {
                     fieldRefs.phone.value = maskPhone(fieldRefs.phone.value);
                 });
             }

             if (fieldRefs.state) {
                 fieldRefs.state.addEventListener('input', () => {
                     fieldRefs.state.value = (fieldRefs.state.value || '').toUpperCase().slice(0, 2);
                 });
             }

             if (fieldRefs.cnpj) {
                 const syncCnpjValue = () => {
                     if (fieldRefs.cnpjNotAvailable && fieldRefs.cnpjNotAvailable.checked) {
                         fieldRefs.cnpj.value = '';
                         if (cnpjFeedback) cnpjFeedback.textContent = '';
                         fieldRefs.cnpj.classList.remove('is-invalid');
                         return false;
                     }
                     fieldRefs.cnpj.value = maskCnpj(fieldRefs.cnpj.value);
                     return true;
                 };

                 fieldRefs.cnpj.addEventListener('input', () => {
                     syncCnpjValue();
                     if (cnpjFeedback) cnpjFeedback.textContent = '';
                     fieldRefs.cnpj.classList.remove('is-invalid');
                 });

                 fieldRefs.cnpj.addEventListener('blur', () => {
                     if (!syncCnpjValue()) return;
                     const digits = maskDigits(fieldRefs.cnpj.value);
                     const invalid = digits.length > 0 && digits.length !== 14;
                     fieldRefs.cnpj.classList.toggle('is-invalid', invalid);
                     if (cnpjFeedback) cnpjFeedback.textContent = invalid ? 'CNPJ incompleto.' : '';
                 });
             }

             let lastCepQuery = '';
             async function handleCepLookup() {
                 if (!fieldRefs.zip) return;
                 const digits = maskDigits(fieldRefs.zip.value);
                 if (digits.length !== 8 || digits === lastCepQuery) return;
                 lastCepQuery = digits;

                 if (cepFeedback) cepFeedback.textContent = '';
                 try {
                     const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
                     const data = await res.json();
                     if (!res.ok || data.erro) throw new Error('CEP não encontrado');

                     if (fieldRefs.street) fieldRefs.street.value = data.logradouro || '';
                     if (fieldRefs.complement) fieldRefs.complement.value = data.complemento || '';
                     if (fieldRefs.district) fieldRefs.district.value = data.bairro || '';
                     if (fieldRefs.city) fieldRefs.city.value = data.localidade || '';
                     if (fieldRefs.state) fieldRefs.state.value = data.uf || '';
                     fieldRefs.zip.value = maskCep(data.cep || digits);

                     if (cepFeedback) cepFeedback.textContent = '';
                 } catch (e) {
                     if (cepFeedback) cepFeedback.textContent = 'Não foi possível buscar o CEP.';
                 }
             }

             if (fieldRefs.zip) {
                 fieldRefs.zip.addEventListener('input', () => {
                     fieldRefs.zip.value = maskCep(fieldRefs.zip.value);
                     if (cepFeedback) cepFeedback.textContent = '';
                 });
                 fieldRefs.zip.addEventListener('blur', handleCepLookup);
             }

             let selectedNewPhotoFiles = [];

            function syncNewPhotoInputFiles() {
                const input = fieldRefs.photoFiles;
                if (!input) return;

                if (typeof DataTransfer === 'undefined') {
                    input.value = '';
                    return;
                }

                const dt = new DataTransfer();
                selectedNewPhotoFiles.forEach(file => dt.items.add(file));
                input.files = dt.files;
            }

            function renderNewPhotoPreview() {
                if (!newPhotosList) return;

                newPhotosList.innerHTML = '';
                const list = Array.isArray(selectedNewPhotoFiles) ? selectedNewPhotoFiles : [];

                if (newPhotosEmpty) {
                    newPhotosEmpty.classList.toggle('d-none', list.length > 0);
                }

                list.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'ong-photo-item';

                    const img = document.createElement('img');
                    img.alt = file?.name || 'Foto selecionada';

                    const reader = new FileReader();
                    reader.onload = () => { img.src = reader.result; };
                    reader.readAsDataURL(file);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'ong-photo-remove';
                    remove.setAttribute('aria-label', 'Remover foto');
                    remove.textContent = 'x';
                    remove.addEventListener('click', () => {
                        selectedNewPhotoFiles.splice(index, 1);
                        syncNewPhotoInputFiles();
                        renderNewPhotoPreview();
                    });

                    item.appendChild(img);
                    item.appendChild(remove);
                    newPhotosList.appendChild(item);
                });
            }

            const applyCnpj = registerOptionalField(fieldRefs.cnpjNotAvailable, fieldRefs.cnpj);
            const applyFacebook = registerOptionalField(fieldRefs.facebookNotAvailable, fieldRefs.facebook);
            const applyInstagram = registerOptionalField(fieldRefs.instagramNotAvailable, fieldRefs.instagram);
            const applyWebsite = registerOptionalField(fieldRefs.websiteNotAvailable, fieldRefs.website);

            if (fieldRefs.photoFiles) {
                fieldRefs.photoFiles.addEventListener('change', () => {
                    selectedNewPhotoFiles = Array.from(fieldRefs.photoFiles.files || []);
                    renderNewPhotoPreview();
                });
                renderNewPhotoPreview();
            }

            document.querySelectorAll('[data-action="edit"]').forEach(button => {
                button.addEventListener('click', () => {
                    const payload = JSON.parse(button.getAttribute('data-ong') || '{}');

                    if (fieldRefs.source_tag) fieldRefs.source_tag.value = payload.source_tag || 'sos_animal_help';

                    fieldRefs.name.value = payload.name || '';
                    fieldRefs.email.value = payload.email || '';
                    fieldRefs.cnpj.value = payload.cnpj || '';
                    fieldRefs.phone.value = payload.phone || '';
                    fieldRefs.foundation_date.value = payload.foundation_date || '';
                    fieldRefs.animal_count.value = payload.animal_count ?? '';
                    fieldRefs.caregiver_count.value = payload.caregiver_count ?? '';
                    fieldRefs.description.value = payload.description || '';

                    fieldRefs.zip.value = payload.zip || '';
                    fieldRefs.street.value = payload.street || '';
                    fieldRefs.number.value = payload.number || '';
                    fieldRefs.complement.value = payload.complement || '';
                    fieldRefs.district.value = payload.district || '';
                    fieldRefs.city.value = payload.city || '';
                    fieldRefs.state.value = payload.state || '';

                    fieldRefs.facebook.value = payload.facebook || '';
                    fieldRefs.instagram.value = payload.instagram || '';
                     fieldRefs.website.value = payload.website || '';

                     fieldRefs.portion_value.value = payload.portion_value ?? '';
                     fieldRefs.medicines_value.value = payload.medicines_value ?? '';
                     fieldRefs.veterinarian_value.value = payload.veterinarian_value ?? '';
                     fieldRefs.collaborators_value.value = payload.collaborators_value ?? '';
                     fieldRefs.other_costs_value.value = payload.other_costs_value ?? '';
                     fieldRefs.other_costs_description.value = payload.other_costs_description ?? '';

                     [
                         fieldRefs.portion_value,
                         fieldRefs.medicines_value,
                         fieldRefs.veterinarian_value,
                         fieldRefs.collaborators_value,
                         fieldRefs.other_costs_value,
                     ].forEach(input => input?.dispatchEvent(new Event('blur')));

                     if (fieldRefs.cnpj) fieldRefs.cnpj.dispatchEvent(new Event('input'));
                     if (fieldRefs.phone) fieldRefs.phone.dispatchEvent(new Event('input'));
                     if (fieldRefs.zip) fieldRefs.zip.dispatchEvent(new Event('input'));

                     if (fieldRefs.photoFiles) fieldRefs.photoFiles.value = '';
                     selectedNewPhotoFiles = [];
                     renderNewPhotoPreview();

                    fieldRefs.cnpjNotAvailable.checked = Boolean(payload.cnpj_not_available);
                    fieldRefs.facebookNotAvailable.checked = Boolean(payload.facebook_not_available);
                    fieldRefs.instagramNotAvailable.checked = Boolean(payload.instagram_not_available);
                    fieldRefs.websiteNotAvailable.checked = Boolean(payload.website_not_available);
                    applyCnpj();
                    applyFacebook();
                    applyInstagram();
                    applyWebsite();

                    renderExistingPhotos(payload.photo_urls);

                    editForm.dataset.action = button.getAttribute('data-route') || '';
                    if (editMessage) editMessage.textContent = '';
                });
            });

            if (editForm) {
                editForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const targetRoute = editForm.dataset.action;
                    if (!targetRoute) return;

                    if (editMessage) editMessage.textContent = 'Salvando...';

                     const formData = new FormData(editForm);
                     formData.append('_method', 'PUT');

                     normalizeMoneyField(formData, 'portion_value', fieldRefs.portion_value);
                     normalizeMoneyField(formData, 'medicines_value', fieldRefs.medicines_value);
                     normalizeMoneyField(formData, 'veterinarian_value', fieldRefs.veterinarian_value);
                     normalizeMoneyField(formData, 'collaborators_value', fieldRefs.collaborators_value);
                     normalizeMoneyField(formData, 'other_costs_value', fieldRefs.other_costs_value);

                     try {
                         const response = await fetch(targetRoute, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: formData,
                            credentials: 'same-origin',
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                            if (editMessage) editMessage.textContent = firstError || payload.message || 'Não foi possível salvar.';
                            return;
                        }

                        editModal?.hide();
                        location.reload();
                    } catch (err) {
                        console.error(err);
                        if (editMessage) editMessage.textContent = 'Erro de conexão ao salvar.';
                    }
                });

                editModalEl?.addEventListener('hidden.bs.modal', () => {
                    editForm.reset();
                    editForm.dataset.action = '';
                    if (editMessage) editMessage.textContent = '';
                    renderExistingPhotos([]);
                    selectedNewPhotoFiles = [];
                    renderNewPhotoPreview();
                });
            }

            document.querySelectorAll('[data-action="delete"]').forEach(button => {
                button.addEventListener('click', async () => {
                    const route = button.getAttribute('data-route');
                    const name = button.getAttribute('data-name') || 'esta ONG';
                    if (!route || !csrfToken) return;

                    if (!confirm(`Deseja remover ${name}? Essa ação é irreversível.`)) return;

                    try {
                        const response = await fetch(route, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                        });
                        if (response.ok) {
                            location.reload();
                            return;
                        }
                        const payload = await response.json().catch(() => ({}));
                        alert(payload.message || 'Não foi possível excluir.');
                    } catch (err) {
                        console.error(err);
                        alert('Erro ao tentar excluir. Tente novamente.');
                    }
                });
            });
        });
    </script>
@endpush
