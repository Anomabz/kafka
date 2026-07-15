<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    <title>@yield('title', 'SAPTO')</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.png') }}" type="image/png">
    {{-- Set the theme before first paint to avoid a light/dark flash. Only an
         explicit user choice is stored; otherwise the OS preference drives it
         through the prefers-color-scheme rules in app.css. Also colours the
         mobile browser chrome (theme-color) to match. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sapto-theme');
                if (t !== 'dark' && t !== 'light') {
                    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
                var m = document.getElementById('theme-color-meta');
                if (m) m.setAttribute('content', t === 'dark' ? '#060a13' : '#e7ecf5');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    @if (session('impersonator_sso_user'))
        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-[12px] font-semibold"
             style="background:var(--color-warn-soft);color:var(--color-warn);border-bottom:1px solid var(--color-warn)">
            <span>Mode uji — kamu login sebagai <strong>{{ session('sso_user.name') }}</strong>.</span>
            <form method="POST" action="{{ route('admin.stop-impersonating') }}">
                @csrf
                <button type="submit" class="underline" style="cursor:pointer;border:0;background:transparent;color:inherit;font:inherit">
                    Kembali ke admin
                </button>
            </form>
        </div>
    @endif

    <div id="app-shell">

        @include('components.sidebar', ['tables' => $tables, 'activeTableId' => $activeTableId ?? 'kerjasama-pendidikan'])

        <div id="sidebar-backdrop" class="sidebar-backdrop lg:hidden"></div>

        <div class="flex min-w-0 flex-col">
            @include('components.topbar')

            <main class="px-3 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-7">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('overlays')

    @yield('scripts')
</body>
</html>
