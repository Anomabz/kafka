<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    <title>@yield('title', 'SAPTO v1.0 - UNSRI — Sistem Akreditasi Perguruan Tinggi Online Universitas Sriwijaya')</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    @include('components.seo-meta')
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
                if (m) m.setAttribute('content', t === 'dark' ? '#0a0a0c' : '#e7ecf5');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Self-contained intro splash styling: not routed through Tailwind/Vite
         so it always renders even if compiled assets are stale. --}}
    <style>
        .intro-splash {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #050a12;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.6s ease, visibility 0.6s ease, filter 0.6s ease;
        }

        .intro-splash.intro-hide {
            opacity: 0;
            visibility: hidden;
            filter: blur(6px);
            pointer-events: none;
        }

        .intro-aurora {
            position: absolute;
            inset: -10%;
            z-index: 0;
            filter: blur(70px);
            opacity: 0.8;
        }

        .intro-aurora span {
            position: absolute;
            border-radius: 50%;
            mix-blend-mode: screen;
        }

        .intro-aurora span:nth-child(1) {
            width: 30rem; height: 30rem;
            top: -6%; left: -8%;
            background: radial-gradient(circle, #4338ca, transparent 65%);
            animation: intro-drift-a 9s ease-in-out infinite;
        }

        .intro-aurora span:nth-child(2) {
            width: 26rem; height: 26rem;
            bottom: -10%; right: -6%;
            background: radial-gradient(circle, #a5b4fc, transparent 65%);
            animation: intro-drift-b 11s ease-in-out infinite;
        }

        @keyframes intro-drift-a {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(6%, 8%) scale(1.2); }
        }

        @keyframes intro-drift-b {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-6%, -8%) scale(1.15); }
        }

        .intro-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.4rem;
        }

        .intro-logo-wrap {
            position: relative;
            display: grid;
            place-items: center;
            width: 7.5rem;
            height: 7.5rem;
        }

        .intro-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1.5px solid rgba(129, 140, 248, 0.35);
            opacity: 0;
            animation: intro-ring-pulse 2.1s 0.5s ease-out infinite;
        }

        .intro-ring.intro-ring-2 {
            animation-delay: 1s;
        }

        @keyframes intro-ring-pulse {
            0% { transform: scale(0.55); opacity: 0; }
            15% { opacity: 0.85; }
            100% { transform: scale(1.35); opacity: 0; }
        }

        .intro-logo {
            position: relative;
            z-index: 1;
            width: 4.75rem;
            height: 4.75rem;
            object-fit: contain;
            opacity: 0;
            transform: scale(0.4) rotate(-8deg);
            animation: intro-logo-in 0.85s 0.1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            filter: drop-shadow(0 8px 26px rgba(0, 0, 0, 0.45));
        }

        @keyframes intro-logo-in {
            60% { opacity: 1; }
            to { opacity: 1; transform: scale(1) rotate(0deg); }
        }

        .intro-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.55rem;
        }

        .intro-title {
            display: flex;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            color: #f8fdfc;
        }

        .intro-title span {
            display: inline-block;
            opacity: 0;
            transform: translateY(16px);
            animation: intro-letter-in 0.5s ease forwards;
        }

        @keyframes intro-letter-in {
            to { opacity: 1; transform: translateY(0); }
        }

        .intro-sub {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(243, 250, 248, 0.68);
            opacity: 0;
            transform: translateY(8px);
            animation: intro-rise 0.6s 1.15s ease forwards;
        }

        @keyframes intro-rise {
            to { opacity: 1; transform: translateY(0); }
        }

        .intro-bar {
            width: 8rem;
            height: 2px;
            margin-top: 0.3rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            overflow: hidden;
            opacity: 0;
            animation: intro-rise 0.4s 1.3s ease forwards;
        }

        .intro-bar span {
            display: block;
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: linear-gradient(90deg, #4338ca, #a5b4fc);
            animation: intro-bar-fill 1.2s 1.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes intro-bar-fill {
            to { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .intro-logo, .intro-title span, .intro-sub, .intro-bar, .intro-bar span, .intro-ring, .intro-aurora span {
                animation: none !important;
                opacity: 1;
                transform: none;
            }
            .intro-ring { display: none; }
            .intro-bar span { width: 100%; }
        }
    </style>
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    {{-- show_intro adalah session flash (di-set oleh SsoController tepat
         setelah login berhasil, lihat callback()) -- otomatis kebaca cuma
         sekali di request
         berikutnya lalu hilang sendiri. Jadi intro ini muncul tiap kali
         login, bukan tiap kali tab/browser baru buka halaman. --}}
    @if (session('show_intro'))
        <div id="intro-splash" class="intro-splash" aria-hidden="true">
            <div class="intro-aurora"><span></span><span></span></div>
            <div class="intro-inner">
                <div class="intro-logo-wrap">
                    <span class="intro-ring"></span>
                    <span class="intro-ring intro-ring-2"></span>
                    <img src="{{ asset('images/logo-unsri.webp') }}" alt="" class="intro-logo">
                </div>
                <div class="intro-text">
                    <span class="intro-title">
                        @foreach (str_split('SAPTO') as $i => $char)
                            <span style="animation-delay: {{ 0.5 + $i * 0.07 }}s">{{ $char }}</span>
                        @endforeach
                    </span>
                    <span class="intro-sub">Universitas Sriwijaya</span>
                    <span class="intro-bar"><span></span></span>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var el = document.getElementById('intro-splash');
                if (!el) return;

                setTimeout(function () {
                    el.classList.add('intro-hide');
                    el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
                }, 2400);
            })();
        </script>
    @endif

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

    <div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="false"></div>

    @yield('scripts')
</body>
</html>
