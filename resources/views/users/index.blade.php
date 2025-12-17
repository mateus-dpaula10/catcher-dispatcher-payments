@extends('main')

@section('title', 'Perfil')

@section('section')
    <div class="container-fluid" id="users_index">
        <div class="row">
            <div class="col-12 px">
                <div class="card card-glass" style="min-height: 100vh">
                    <div class="px-4 pt-4 pb-3 border-bottom" style="border-color: rgba(255,255,255,.10)!important;">
                        <h5 class="mb-1 section-title">Gerenciar usuários</h5>
                        <div class="small section-subtitle">Atualize, adione ou exclua usuários</div>
                    </div>

                    <div class="card-body">
                        @php
                            $isAdmin = match (true) {
                                (auth()->user()->role ?? '') === 'admin' => true,
                                (auth()->user()->level ?? '') === 'admin' => true,
                                (bool) (auth()->user()->is_admin ?? false) => true,
                                default => false,
                            };

                            $currentLevel = old('level', auth()->user()->level ?? (auth()->user()->role ?? 'user'));

                            $levelLabel = match ($currentLevel) {
                                'admin' => 'Admin',
                                default => 'Usuário',
                            };
                        @endphp

                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2 py-2"
                                role="alert">
                                <div>
                                    <div class="fw-semibold">Pronto!</div>
                                    <div class="small">{{ session('success') }}</div>
                                </div>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"
                                    aria-label="Fechar"></button>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2 py-2"
                                role="alert">
                                <div>
                                    <div class="fw-semibold">Ops!</div>
                                    <div class="small">{{ $errors->first() }}</div>
                                </div>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"
                                    aria-label="Fechar"></button>
                            </div>
                        @endif

                        {{-- ADMIN: ADICIONAR USUÁRIO --}}
                        @if ($isAdmin)
                            {{-- ADMIN: LISTAR / EDITAR USUÁRIOS (SEM MODAL) --}}
                            <div class="mt-4">
                                <div class="card-glass" style="border-radius:16px;">
                                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom"
                                        style="border-color: rgba(255,255,255,.10)!important;">
                                        <div>
                                            <div class="fw-semibold text-white">Usuários do sistema</div>
                                            <div class="small section-subtitle">Edite usuários e permissões</div>
                                        </div>
                                    </div>

                                    <div class="p-4">
                                        <div class="accordion" id="usersAccordion">
                                            @forelse($users ?? [] as $u)
                                                @php
                                                    $uLevel = $u->level ?? ($u->role ?? 'user');
                                                    $uLevelLabel = match ($uLevel) {
                                                        'admin' => 'Admin',
                                                        default => 'Usuário',
                                                    };
                                                    $uAvatar = $u->avatar_path
                                                        ? Storage::disk('public')->url($u->avatar_path)
                                                        : asset('img/user_default.png');

                                                    // não permitir excluir a si mesmo (recomendado)
                                                    $isSelf = auth()->id() === $u->id;
                                                @endphp

                                                <div class="accordion-item mb-2"
                                                    style="background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.10); border-radius:14px; overflow:hidden;">
                                                    <h2 class="accordion-header" id="headingUser{{ $u->id }}">
                                                        <button
                                                            class="accordion-button collapsed d-flex align-items-center gap-3"
                                                            style="background: transparent; color:#fff;" type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapseUser{{ $u->id }}"
                                                            aria-expanded="false"
                                                            aria-controls="collapseUser{{ $u->id }}">

                                                            <img src="{{ $uAvatar }}"
                                                                alt="Avatar de {{ $u->name }}"
                                                                style="width:40px;height:40px;border-radius:100%;object-fit:cover; flex:0 0 auto;">

                                                            <div class="flex-grow-1">
                                                                <div class="fw-semibold">{{ $u->name }}</div>
                                                                <div class="small section-subtitle">{{ $u->email }}
                                                                </div>
                                                            </div>

                                                            <span
                                                                class="badge {{ $uLevel === 'admin' ? 'text-bg-danger' : 'text-bg-secondary' }}">
                                                                {{ $uLevelLabel }}
                                                            </span>
                                                        </button>
                                                    </h2>

                                                    <div id="collapseUser{{ $u->id }}"
                                                        class="accordion-collapse collapse"
                                                        aria-labelledby="headingUser{{ $u->id }}"
                                                        data-bs-parent="#usersAccordion">
                                                        <div class="accordion-body" style="background: rgba(14,26,43,.35);">

                                                            {{-- FORM EDITAR --}}
                                                            <form method="POST"
                                                                action="{{ route('users.updateUser', $u->id) }}"
                                                                enctype="multipart/form-data">
                                                                @csrf
                                                                @method('PUT')

                                                                <div class="row g-4">
                                                                    {{-- FOTO --}}
                                                                    <div class="col-12 col-lg-4">
                                                                        <label class="form-label">Foto</label>

                                                                        <div class="d-flex align-items-center gap-3">
                                                                            <div class="avatar">
                                                                                <img id="editAvatarPreview-{{ $u->id }}"
                                                                                    src="{{ $uAvatar }}"
                                                                                    alt="Avatar">
                                                                            </div>

                                                                            <div class="flex-grow-1">
                                                                                <input class="form-control" type="file"
                                                                                    name="avatar"
                                                                                    id="editAvatarInput-{{ $u->id }}"
                                                                                    accept="image/*">
                                                                                <div class="small section-subtitle mt-2">
                                                                                    Opcional</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    {{-- DADOS --}}
                                                                    <div class="col-12 col-lg-8">
                                                                        <div class="row g-3">
                                                                            <div class="col-12">
                                                                                <label class="form-label">Nome</label>
                                                                                <input type="text" class="form-control"
                                                                                    name="name" required
                                                                                    value="{{ old('name_' . $u->id, $u->name) }}">
                                                                            </div>

                                                                            <div class="col-12">
                                                                                <label class="form-label">E-mail</label>
                                                                                <input type="email" class="form-control"
                                                                                    name="email" required
                                                                                    inputmode="email"
                                                                                    value="{{ old('email_' . $u->id, $u->email) }}">
                                                                            </div>

                                                                            <div class="col-12">
                                                                                <label class="form-label">Nível</label>
                                                                                <select class="form-select" name="level"
                                                                                    required>
                                                                                    <option value="user"
                                                                                        {{ $uLevel === 'user' ? 'selected' : '' }}>
                                                                                        Usuário</option>
                                                                                    <option value="admin"
                                                                                        {{ $uLevel === 'admin' ? 'selected' : '' }}>
                                                                                        Admin</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    {{-- SENHA --}}
                                                                    <div class="col-12">
                                                                        <div class="card-glass"
                                                                            style="padding:16px; border-radius:16px;">
                                                                            <div
                                                                                class="d-flex justify-content-between align-items-center mb-2">
                                                                                <div>
                                                                                    <div class="fw-semibold text-white">
                                                                                        Senha</div>
                                                                                    <div class="small section-subtitle">
                                                                                        Deixe em branco para não alterar
                                                                                    </div>
                                                                                </div>

                                                                                <button type="button"
                                                                                    class="btn btn-outline-soft btn-sm btnGenUserPass"
                                                                                    data-user="{{ $u->id }}">
                                                                                    Gerar senha
                                                                                </button>
                                                                            </div>

                                                                            <div class="row g-3">
                                                                                <div class="col-12 col-md-6">
                                                                                    <label class="form-label">Nova
                                                                                        senha</label>
                                                                                    <input type="password"
                                                                                        class="form-control userPassInput"
                                                                                        id="editPassword-{{ $u->id }}"
                                                                                        name="password"
                                                                                        autocomplete="new-password"
                                                                                        placeholder="Nova senha (opcional)">
                                                                                    <div class="strength-wrap">
                                                                                        <div class="strength-bar">
                                                                                            <div class="strength-fill"
                                                                                                id="editStrengthFill-{{ $u->id }}">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="strength-text"
                                                                                            id="editStrengthText-{{ $u->id }}">
                                                                                            Força: —</div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-12 col-md-6">
                                                                                    <label class="form-label">Confirmar
                                                                                        senha</label>
                                                                                    <input type="password"
                                                                                        class="form-control"
                                                                                        id="editPasswordConfirmation-{{ $u->id }}"
                                                                                        name="password_confirmation"
                                                                                        autocomplete="new-password"
                                                                                        placeholder="Confirme (opcional)">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    {{-- AÇÕES --}}
                                                                    <div class="col-12 d-flex gap-2 flex-wrap justify-content-end">
                                                                        <button type="submit"
                                                                            class="btn btn-brand text-white">
                                                                            Salvar alterações
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </form>

                                                            {{-- FORM EXCLUIR --}}
                                                            <div class="d-flex justify-content-end mt-3">
                                                                <form method="POST"
                                                                    action="{{ route('users.destroy', $u->id) }}"
                                                                    onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                                                    @csrf
                                                                    @method('DELETE')

                                                                    <button class="btn btn-outline-danger"
                                                                        {{ $isSelf ? 'disabled' : '' }}
                                                                        title="{{ $isSelf ? 'Você não pode excluir seu próprio usuário.' : '' }}">
                                                                        Excluir usuário
                                                                    </button>
                                                                </form>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-center text-muted py-3">Nenhum usuário encontrado.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ADMIN: ADICIONAR USUÁRIOS --}}
                            <div class="mt-4">
                                <div class="card-glass" style="border-radius:16px;">
                                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom"
                                        style="border-color: rgba(255,255,255,.10)!important;">
                                        <div>
                                            <div class="fw-semibold text-white">Adicionar usuário</div>
                                            <div class="small section-subtitle">Crie um novo usuário para acessar o painel
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-soft btn-sm" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseAddUser"
                                            aria-expanded="false" aria-controls="collapseAddUser">
                                            Abrir
                                        </button>
                                    </div>

                                    <div class="collapse" id="collapseAddUser">
                                        <div class="p-4">
                                            {{-- Ajuste a rota conforme seu projeto --}}
                                            <form method="POST" action="{{ route('users.store') }}"
                                                enctype="multipart/form-data">
                                                @csrf

                                                <div class="row g-4">
                                                    <div class="col-12 col-lg-4">
                                                        <label class="form-label">Foto do usuário</label>

                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="avatar">
                                                                <img id="newUserAvatarPreview"
                                                                    src="{{ asset('img/user_default.png') }}"
                                                                    alt="Foto do novo usuário">
                                                            </div>

                                                            <div class="flex-grow-1">
                                                                <input class="form-control" type="file" name="avatar"
                                                                    id="newUserAvatarInput" accept="image/*">
                                                                <div class="small section-subtitle mt-2">
                                                                    PNG/JPG. Recomendado: 400×400.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-8">
                                                        <div class="row g-3">
                                                            <div class="col-12">
                                                                <label class="form-label">Nome</label>
                                                                <input type="text" class="form-control" name="name"
                                                                    required value="{{ old('name_user') }}"
                                                                    placeholder="Nome do usuário">
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label">E-mail</label>
                                                                <input type="email" class="form-control" name="email"
                                                                    required value="{{ old('email_user') }}"
                                                                    placeholder="email@dominio.com" inputmode="email">
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label">Nível</label>
                                                                <select class="form-select" name="level" required>
                                                                    <option value="user">Usuário</option>
                                                                    <option value="admin">Admin</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="card-glass" style="padding:16px; border-radius:16px;">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-2">
                                                                <div>
                                                                    <div class="fw-semibold text-white">Senha do usuário
                                                                    </div>
                                                                    <div class="small section-subtitle">Gere uma senha
                                                                        forte e copie para enviar ao usuário</div>
                                                                </div>

                                                                <button type="button" class="btn btn-outline-soft btn-sm"
                                                                    id="btnGeneratePassUser">
                                                                    Gerar senha
                                                                </button>
                                                            </div>

                                                            <div class="row g-3">
                                                                <div class="col-12 col-md-6">
                                                                    <label class="form-label">Senha</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text" aria-hidden="true">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                width="16" height="16"
                                                                                fill="currentColor" viewBox="0 0 16 16">
                                                                                <path
                                                                                    d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6V4a2 2 0 1 0-4 0v3h4z" />
                                                                            </svg>
                                                                        </span>

                                                                        <input type="password" class="form-control"
                                                                            id="newUserPassword" name="password" required
                                                                            autocomplete="new-password"
                                                                            placeholder="Senha do usuário">

                                                                        <button type="button"
                                                                            class="btn btn-outline-soft"
                                                                            id="toggleNewUserPassword"
                                                                            aria-label="Mostrar senha">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                width="18" height="18"
                                                                                fill="currentColor" viewBox="0 0 16 16">
                                                                                <path
                                                                                    d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM8 12a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-1.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z" />
                                                                            </svg>
                                                                        </button>
                                                                    </div>

                                                                    <div class="strength-wrap">
                                                                        <div class="strength-bar">
                                                                            <div class="strength-fill"
                                                                                id="strengthFillUser"></div>
                                                                        </div>
                                                                        <div class="strength-text" id="strengthTextUser">
                                                                            Força: —</div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 col-md-6">
                                                                    <label class="form-label">Confirmar senha</label>
                                                                    <input type="password" class="form-control"
                                                                        id="newUserPasswordConfirmation"
                                                                        name="password_confirmation" required
                                                                        autocomplete="new-password"
                                                                        placeholder="Repita a senha">
                                                                </div>

                                                                <div class="col-12 d-flex gap-2 flex-wrap">
                                                                    <button type="submit"
                                                                        class="btn btn-brand px-4 text-white">
                                                                        Criar usuário
                                                                    </button>

                                                                    <button type="button" class="btn btn-outline-soft"
                                                                        data-bs-toggle="collapse"
                                                                        data-bs-target="#collapseAddUser"
                                                                        aria-expanded="true"
                                                                        aria-controls="collapseAddUser">
                                                                        Cancelar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endpush

@push('scripts')
    <script>
        // ============================
        // Helpers
        // ============================
        function genStrongPassword(len = 14) {
            const lower = "abcdefghijklmnopqrstuvwxyz";
            const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            const nums = "0123456789";
            const sym = "!@#$%^&*()-_=+[]{};:,.?";
            const all = lower + upper + nums + sym;

            let out = "";
            out += lower[Math.floor(Math.random() * lower.length)];
            out += upper[Math.floor(Math.random() * upper.length)];
            out += nums[Math.floor(Math.random() * nums.length)];
            out += sym[Math.floor(Math.random() * sym.length)];

            for (let i = out.length; i < len; i++) {
                out += all[Math.floor(Math.random() * all.length)];
            }

            // shuffle
            return out.split("").sort(() => Math.random() - 0.5).join("");
        }

        function passwordScore(p) {
            if (!p) return 0;

            let s = 0;
            const len = p.length;

            // tamanho
            if (len >= 8) s += 1;
            if (len >= 12) s += 1;
            if (len >= 16) s += 1;

            // variedade
            if (/[a-z]/.test(p)) s += 1;
            if (/[A-Z]/.test(p)) s += 1;
            if (/[0-9]/.test(p)) s += 1;
            if (/[^A-Za-z0-9]/.test(p)) s += 1;

            // penaliza repetição
            if (/^(.)\1+$/.test(p)) s = 1;

            return Math.min(s, 7);
        }

        function renderStrength(passValue, fillEl, textEl) {
            if (!fillEl || !textEl) return;

            const s = passwordScore(passValue);
            const pct = Math.round((s / 7) * 100);

            fillEl.style.width = pct + "%";

            if (!passValue) {
                fillEl.style.backgroundColor = "rgba(255,255,255,.20)";
                textEl.textContent = "Força: —";
                return;
            }

            if (pct <= 30) fillEl.style.backgroundColor = "rgba(220,53,69,.95)";
            else if (pct <= 60) fillEl.style.backgroundColor = "rgba(255,193,7,.95)";
            else fillEl.style.backgroundColor = "rgba(46,160,67,.95)";

            let label = "—";
            if (pct <= 30) label = "Fraca";
            else if (pct <= 60) label = "Média";
            else label = "Forte";

            textEl.textContent = "Força: " + label;
        }

        function bindAvatarPreview(inputId, imgId) {
            const input = document.getElementById(inputId);
            const img = document.getElementById(imgId);
            if (!input || !img) return;

            input.addEventListener("change", () => {
                const file = input.files && input.files[0];
                if (!file) return;
                img.src = URL.createObjectURL(file);
            });
        }

        function bindTogglePassword(btnId, inputId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            if (!btn || !input) return;

            btn.addEventListener("click", () => {
                const isPass = input.type === "password";
                input.type = isPass ? "text" : "password";
                btn.setAttribute("aria-label", isPass ? "Ocultar senha" : "Mostrar senha");
            });
        }

        function bindPasswordGenerator(btnId, passId, confId, strengthFillId, strengthTextId) {
            const btn = document.getElementById(btnId);
            const pass = document.getElementById(passId);
            const conf = document.getElementById(confId);
            const fill = document.getElementById(strengthFillId);
            const text = document.getElementById(strengthTextId);
            if (!btn || !pass) return;

            btn.addEventListener("click", () => {
                const p = genStrongPassword(14);
                pass.value = p;
                if (conf) conf.value = p;

                // atualiza força
                renderStrength(pass.value, fill, text);

                // dispara para listeners existentes
                pass.dispatchEvent(new Event("input"));
                pass.focus();
            });
        }

        function bindStrengthWatcher(passId, strengthFillId, strengthTextId) {
            const pass = document.getElementById(passId);
            const fill = document.getElementById(strengthFillId);
            const text = document.getElementById(strengthTextId);
            if (!pass || !fill || !text) return;

            pass.addEventListener("input", () => renderStrength(pass.value, fill, text));
            renderStrength(pass.value, fill, text);
        }

        // ============================
        // PERFIL (usuário logado)
        // ============================
        bindAvatarPreview("avatarInput", "avatarPreview");
        bindTogglePassword("togglePassword", "password");
        bindPasswordGenerator("btnGeneratePass", "password", "password_confirmation", "strengthFill", "strengthText");
        bindStrengthWatcher("password", "strengthFill", "strengthText");

        // ============================
        // ADMIN - NOVO USUÁRIO
        // ============================
        bindAvatarPreview("newUserAvatarInput", "newUserAvatarPreview");
        bindTogglePassword("toggleNewUserPassword", "newUserPassword");
        bindPasswordGenerator(
            "btnGeneratePassUser",
            "newUserPassword",
            "newUserPasswordConfirmation",
            "strengthFillUser",
            "strengthTextUser"
        );
        bindStrengthWatcher("newUserPassword", "strengthFillUser", "strengthTextUser");

        // ============================
        // ADMIN - EDITAR USUÁRIOS (modais)
        // - suporta quantos usuários existirem
        // IDs esperados:
        //  editAvatarInput-{id}, editAvatarPreview-{id}
        //  editPassword-{id}, editStrengthFill-{id}, editStrengthText-{id}
        // Botão de gerar senha no modal: .btnGenUserPass (com data-targets) [se você usou o bloco que mandei]
        // ============================

        // Preview avatar em qualquer modal (delegação)
        document.addEventListener("change", function(e) {
            const el = e.target;
            if (!el || !el.id) return;

            if (el.id.startsWith("editAvatarInput-")) {
                const userId = el.id.replace("editAvatarInput-", "");
                const img = document.getElementById("editAvatarPreview-" + userId);
                const file = el.files && el.files[0];
                if (img && file) img.src = URL.createObjectURL(file);
            }
        });

        // Força de senha em qualquer modal enquanto digita (delegação)
        document.addEventListener("input", function(e) {
            const el = e.target;
            if (!el || !el.id) return;

            if (el.id.startsWith("editPassword-")) {
                const userId = el.id.replace("editPassword-", "");
                const fill = document.getElementById("editStrengthFill-" + userId);
                const text = document.getElementById("editStrengthText-" + userId);
                renderStrength(el.value, fill, text);
            }
        });

        // Gerar senha em modais (delegação via data-attributes)
        document.addEventListener("click", function(e) {
            const btn = e.target.closest(".btnGenUserPass");
            if (!btn) return;

            const passSel = btn.getAttribute("data-target");
            const confSel = btn.getAttribute("data-target-confirm");
            const fillSel = btn.getAttribute("data-strength-fill");
            const textSel = btn.getAttribute("data-strength-text");

            const pass = passSel ? document.querySelector(passSel) : null;
            const conf = confSel ? document.querySelector(confSel) : null;
            const fill = fillSel ? document.querySelector(fillSel) : null;
            const text = textSel ? document.querySelector(textSel) : null;

            if (!pass) return;

            const p = genStrongPassword(14);
            pass.value = p;
            if (conf) conf.value = p;

            renderStrength(pass.value, fill, text);
            pass.dispatchEvent(new Event("input"));
            pass.focus();
        });
    </script>
@endpush
