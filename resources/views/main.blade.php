<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gestão Siulsan / Susan - @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    {{-- bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="{{ asset('img/logos/favicon.png') }}">
    @stack('styles')
</head>
<body id="dashboard">
    <aside>               
        <ul>
            <div>
                <a href="{{ route('dashboard.index') }}">
                    <img src="{{ asset('img/logos/siulsan-resgate-no-bg.png') }}" alt="Logo Siulsan Resgate">
                </a>

                <li>
                    <a href="{{ route('dashboard.index') }}">Dashboard</a>
                </li>
                {{-- <li>
                    <a href="">Link 2</a>
                </li> --}}
                <li>
                    <a href="{{ route('profile.index') }}">Perfil</a>
                </li>
            </div>

            <div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-primary" type="submit">Sair</button>
                </form>
            </div>
        </ul>
    </aside>

    <main>
        @yield('section')
    </main>
    
    {{-- bootstrap --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>