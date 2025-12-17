@extends('main')

@section('title', 'Perfil')

@section('section')
    <div class="container-fluid" id="profile_index">
        <div class="row">
            <div class="col-12 px">
                <div class="card card-glass" style="min-height: 100vh">
                    <div class="px-4 pt-4 pb-3 border-bottom" style="border-color: rgba(255,255,255,.10)!important;">
                        <h5 class="mb-1 section-title">Editar perfil</h5>
                        <div class="small section-subtitle">Atualize seus dados, foto e senha</div>
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

                        {{-- Ajuste a rota conforme seu projeto --}}
                        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data"
                            class="mt-2">
                            @csrf
                            @method('PUT')

                            <div class="row g-4">
                                {{-- FOTO --}}
                                <div class="col-12 col-lg-4">
                                    <label class="form-label">Foto de perfil</label>

                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar">
                                            @php
                                                $avatar = old('avatar_path', auth()->user()->avatar_path);
                                            @endphp

                                            <img id="avatarPreview"
                                                src="{{ $avatar ? asset('storage/' . $avatar) : asset('img/user_default.png') }}"
                                                alt="Foto do perfil de {{ auth()->user()->name }}">
                                        </div>

                                        <div class="flex-grow-1">
                                            <input class="form-control" type="file" name="avatar" id="avatarInput"
                                                accept="image/*">
                                            <div class="small section-subtitle mt-2">
                                                PNG/JPG. Recomendado: 400×400.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- DADOS --}}
                                <div class="col-12 col-lg-8">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="name" class="form-label">Nome</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                value="{{ old('name', auth()->user()->name) }}" required autocomplete="name"
                                                placeholder="Seu nome">
                                        </div>

                                        <div class="col-12">
                                            <label for="email" class="form-label">E-mail</label>

                                            @if ($isAdmin)
                                                <input type="email" class="form-control" id="email" name="email"
                                                    value="{{ old('email', auth()->user()->email) }}" required
                                                    autocomplete="email" inputmode="email"
                                                    placeholder="seuemail@dominio.com">
                                            @else
                                                <input type="email" class="form-control" id="email"
                                                    value="{{ auth()->user()->email }}" readonly>
                                                <input type="hidden" name="email" value="{{ auth()->user()->email }}">
                                            @endif
                                        </div>

                                        <div class="col-12">
                                            <label for="role" class="form-label">Nível</label>

                                            @if ($isAdmin)
                                                <select class="form-select" id="role" name="level">
                                                    <option value="user" {{ $currentLevel === 'user' ? 'selected' : '' }}>
                                                        Usuário</option>
                                                    <option value="admin"
                                                        {{ $currentLevel === 'admin' ? 'selected' : '' }}>
                                                        Admin</option>
                                                </select>
                                            @else
                                                <input type="text" class="form-control readonly"
                                                    value="{{ $levelLabel }}" readonly>
                                                <input type="hidden" name="level" value="{{ $currentLevel }}">
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- SENHA --}}
                                <div class="col-12">
                                    <div class="card-glass" style="padding:16px; border-radius:16px;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <div class="fw-semibold text-white">Senha</div>
                                                <div class="small section-subtitle">Deixe em branco para não alterar</div>
                                            </div>

                                            <button type="button" class="btn btn-outline-soft btn-sm" id="btnGeneratePass">
                                                Gerar senha
                                            </button>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label for="password" class="form-label">Nova senha</label>
                                                <div class="input-group">
                                                    <span class="input-group-text" aria-hidden="true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16"
                                                            height="16" fill="currentColor" viewBox="0 0 16 16">
                                                            <path
                                                                d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6V4a2 2 0 1 0-4 0v3h4z" />
                                                        </svg>
                                                    </span>

                                                    <input type="password" class="form-control" id="password"
                                                        name="password" autocomplete="new-password"
                                                        placeholder="Digite a nova senha">

                                                    <button type="button" class="btn btn-outline-soft"
                                                        id="togglePassword" aria-label="Mostrar senha">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18"
                                                            height="18" fill="currentColor" viewBox="0 0 16 16">
                                                            <path
                                                                d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM8 12a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-1.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="strength-wrap">
                                                    <div class="strength-bar">
                                                        <div class="strength-fill" id="strengthFill"></div>
                                                    </div>
                                                    <div class="strength-text" id="strengthText">Força: —</div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6">
                                                <label for="password_confirmation" class="form-label">Confirmar
                                                    senha</label>
                                                <input type="password" class="form-control" id="password_confirmation"
                                                    name="password_confirmation" autocomplete="new-password"
                                                    placeholder="Repita a nova senha">
                                            </div>

                                            <div class="col-12 d-flex gap-2 flex-wrap">
                                                <button type="submit" class="btn btn-brand px-4 text-white">
                                                    Salvar alterações
                                                </button>

                                                <a href="{{ route('dashboard.index') }}" class="btn btn-outline-soft">
                                                    Voltar
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        {{-- ADMIN: ADICIONAR USUÁRIO --}}
                        @if ($isAdmin)
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
                                            <form method="POST" action="{{ route('profile.store') }}" enctype="multipart/form-data">
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
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@push('scripts')
    <script>
        // Preview da foto (perfil)
        (function() {
            const input = document.getElementById('avatarInput');
            const img = document.getElementById('avatarPreview');
            if (!input || !img) return;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;
                img.src = URL.createObjectURL(file);
            });
        })();

        // Mostrar/ocultar senha (perfil)
        (function() {
            const btn = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            if (!btn || !input) return;

            btn.addEventListener('click', () => {
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                btn.setAttribute('aria-label', isPass ? 'Ocultar senha' : 'Mostrar senha');
            });
        })();

        // Gerar senha (perfil)
        (function() {
            const btn = document.getElementById('btnGeneratePass');
            const pass = document.getElementById('password');
            const conf = document.getElementById('password_confirmation');
            if (!btn || !pass) return;

            function gen(len = 14) {
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

                for (let i = out.length; i < len; i++) out += all[Math.floor(Math.random() * all.length)];
                return out.split('').sort(() => Math.random() - 0.5).join('');
            }

            btn.addEventListener('click', () => {
                const p = gen(14);
                pass.value = p;
                if (conf) conf.value = p;
                pass.dispatchEvent(new Event('input'));
                pass.focus();
            });
        })();

        // Força de senha (perfil)
        (function() {
            const pass = document.getElementById('password');
            const fill = document.getElementById('strengthFill');
            const text = document.getElementById('strengthText');
            if (!pass || !fill || !text) return;

            function score(p) {
                if (!p) return 0;
                let s = 0;
                const len = p.length;

                if (len >= 8) s += 1;
                if (len >= 12) s += 1;
                if (len >= 16) s += 1;

                if (/[a-z]/.test(p)) s += 1;
                if (/[A-Z]/.test(p)) s += 1;
                if (/[0-9]/.test(p)) s += 1;
                if (/[^A-Za-z0-9]/.test(p)) s += 1;

                if (/^(.)\1+$/.test(p)) s = 1;
                return Math.min(s, 7);
            }

            function render(p) {
                const s = score(p);
                const pct = Math.round((s / 7) * 100);
                fill.style.width = pct + "%";

                if (pct <= 30) fill.style.backgroundColor = "rgba(220,53,69,.95)";
                else if (pct <= 60) fill.style.backgroundColor = "rgba(255,193,7,.95)";
                else fill.style.backgroundColor = "rgba(46,160,67,.95)";

                let label = "—";
                if (!p) label = "—";
                else if (pct <= 30) label = "Fraca";
                else if (pct <= 60) label = "Média";
                else label = "Forte";

                text.textContent = "Força: " + label;
            }

            pass.addEventListener('input', () => render(pass.value));
            render(pass.value);
        })();

        // ---------------------------
        // ADMIN: Preview avatar usuário
        // ---------------------------
        (function() {
            const input = document.getElementById('newUserAvatarInput');
            const img = document.getElementById('newUserAvatarPreview');
            if (!input || !img) return;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;
                img.src = URL.createObjectURL(file);
            });
        })();

        // Admin: mostrar/ocultar senha novo usuário
        (function() {
            const btn = document.getElementById('toggleNewUserPassword');
            const input = document.getElementById('newUserPassword');
            if (!btn || !input) return;

            btn.addEventListener('click', () => {
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                btn.setAttribute('aria-label', isPass ? 'Ocultar senha' : 'Mostrar senha');
            });
        })();

        // Admin: gerar senha novo usuário
        (function() {
            const btn = document.getElementById('btnGeneratePassUser');
            const pass = document.getElementById('newUserPassword');
            const conf = document.getElementById('newUserPasswordConfirmation');
            if (!btn || !pass) return;

            function gen(len = 14) {
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

                for (let i = out.length; i < len; i++) out += all[Math.floor(Math.random() * all.length)];
                return out.split('').sort(() => Math.random() - 0.5).join('');
            }

            btn.addEventListener('click', () => {
                const p = gen(14);
                pass.value = p;
                if (conf) conf.value = p;
                pass.dispatchEvent(new Event('input'));
                pass.focus();
            });
        })();

        // Admin: força de senha novo usuário
        (function() {
            const pass = document.getElementById('newUserPassword');
            const fill = document.getElementById('strengthFillUser');
            const text = document.getElementById('strengthTextUser');
            if (!pass || !fill || !text) return;

            function score(p) {
                if (!p) return 0;
                let s = 0;
                const len = p.length;

                if (len >= 8) s += 1;
                if (len >= 12) s += 1;
                if (len >= 16) s += 1;

                if (/[a-z]/.test(p)) s += 1;
                if (/[A-Z]/.test(p)) s += 1;
                if (/[0-9]/.test(p)) s += 1;
                if (/[^A-Za-z0-9]/.test(p)) s += 1;

                if (/^(.)\1+$/.test(p)) s = 1;
                return Math.min(s, 7);
            }

            function render(p) {
                const s = score(p);
                const pct = Math.round((s / 7) * 100);
                fill.style.width = pct + "%";

                if (pct <= 30) fill.style.backgroundColor = "rgba(220,53,69,.95)";
                else if (pct <= 60) fill.style.backgroundColor = "rgba(255,193,7,.95)";
                else fill.style.backgroundColor = "rgba(46,160,67,.95)";

                let label = "—";
                if (!p) label = "—";
                else if (pct <= 30) label = "Fraca";
                else if (pct <= 60) label = "Média";
                else label = "Forte";

                text.textContent = "Força: " + label;
            }

            pass.addEventListener('input', () => render(pass.value));
            render(pass.value);
        })();
    </script>
@endpush
