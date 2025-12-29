<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gestão Siulsan / Susan - @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    {{-- bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="{{ asset('img/logos/favicon.png') }}">
    @stack('styles')
</head>

<body id="dashboard">
    <aside>
        <div class="aside-head">
            <a href="{{ route('dashboard.index') }}" class="aside-logo">
                <img src="{{ asset('img/logos/siulsan-resgate-no-bg.png') }}" alt="Logo Siulsan Resgate">
            </a>

            <button type="button" class="aside-toggle" id="asideToggle" aria-label="Abrir menu" aria-expanded="false">
                ☰
            </button>
        </div>

        <ul class="aside-nav" id="asideNav">
            <li>
                <a href="{{ route('dashboard.index') }}">Dashboard</a>
            </li>
            <li>
                <a href="{{ route('t.index') }}">E-mail tracking</a>
            </li>
            <li>
                <a href="{{ route('profile.index') }}">Perfil</a>
            </li>
            @if (auth()->user()->level === 'admin')
                <li>
                    <a href="{{ route('users.index') }}">Usuários</a>
                </li>
            @endif
        </ul>

        <div class="aside-foot" id="asideFoot">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn btn-primary" type="submit">Sair</button>
            </form>
        </div>
    </aside>

    <main>
        @yield('section')
    </main>

    {{-- bootstrap --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>
    <script>
        (function() {
            const aside = document.querySelector('aside');
            const btn = document.getElementById('asideToggle');
            if (!aside || !btn) return;

            function setState(open) {
                aside.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                btn.textContent = open ? '✕' : '☰';
            }

            btn.addEventListener('click', () => {
                const open = !aside.classList.contains('is-open');
                setState(open);
            });

            // opcional: ao clicar em um link, fecha o menu no mobile
            aside.addEventListener('click', (e) => {
                const a = e.target.closest('a');
                if (!a) return;
                if (window.matchMedia('(max-width: 900px)').matches) setState(false);
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
