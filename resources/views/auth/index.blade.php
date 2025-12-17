<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gestão Siulsan / Susan - Login</title>
    <link rel="stylesheet" href="css/login.css">
    {{-- bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>

<body id="login">
    <div class="login-wrap">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-12 col-sm-10 col-md-7 col-lg-4">

                    <div class="card login-card border-0 overflow-hidden">
                        <div class="px-4 pt-4 pb-3 text-center login-head">
                            <img id="logo-login" src="{{ asset('img/logos/siulsan-resgate.png') }}"
                                alt="Logo Siulsan Resgate" class="mb-3" />

                            <h5 class="mb-1 login-title">Entrar no painel</h5>
                            <div class="small login-subtitle">Acesse com suas credenciais</div>
                        </div>

                        <div class="card-body p-4">
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

                            <form method="POST" action="{{ route('login.post') }}" class="mt-2">
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label">E-mail</label>
                                    <div class="input-group">
                                        <span class="input-group-text" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" viewBox="0 0 16 16">
                                                <path
                                                    d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383-6.477 3.886a1 1 0 0 1-1.046 0L1 5.383V12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5.383z" />
                                            </svg>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="{{ old('email') }}" placeholder="Digite seu e-mail" required
                                            autofocus autocomplete="email" inputmode="email">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" viewBox="0 0 16 16">
                                                <path
                                                    d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6V4a2 2 0 1 0-4 0v3h4z" />
                                            </svg>
                                        </span>

                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Digite sua senha" required autocomplete="current-password">

                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                                            aria-label="Mostrar senha">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                fill="currentColor" viewBox="0 0 16 16">
                                                <path
                                                    d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM8 12a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-1.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold text-white">
                                    Entrar
                                </button>

                                <div class="text-center small mt-3 login-footer">
                                    © {{ date('Y') }} — Siulsan Resgate
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Mostrar/ocultar senha
        (function() {
            const btn = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            if (!btn || !input) return;

            btn.addEventListener('click', () => {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                btn.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
            });
        })();
    </script>

    {{-- bootstrap --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>
</body>

</html>
