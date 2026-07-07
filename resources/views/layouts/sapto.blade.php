<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SAPTO')</title>
    {{-- Set the theme before first paint to avoid a light/dark flash. Only an
         explicit user choice is stored; otherwise the OS preference drives it
         through the prefers-color-scheme rules in app.css. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sapto-theme');
                if (t !== 'dark' && t !== 'light') {
                    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <div id="app-shell">

        @include('components.sidebar', ['tables' => $tables, 'activeTableId' => $activeTableId ?? 'dosen-pembimbing-ta'])

        <div id="sidebar-backdrop" class="sidebar-backdrop lg:hidden"></div>

        <div class="flex min-w-0 flex-col">
            @include('components.topbar')

            <main class="px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('overlays')

    @yield('scripts')
</body>
</html>
