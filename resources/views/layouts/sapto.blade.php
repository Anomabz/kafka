<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SAPTO')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">

        @include('components.sidebar', ['tables' => $tables, 'activeTableId' => $activeTableId ?? 'dosen-pembimbing-ta'])

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
