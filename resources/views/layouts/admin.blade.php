<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#eef0f3">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — SAPTO Admin</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sapto-theme');
                if (t !== 'dark' && t !== 'light') {
                    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
                var m = document.getElementById('theme-color-meta');
                if (m) m.setAttribute('content', t === 'dark' ? '#0a0a0c' : '#eef0f3');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /*
         * Kerangka bersama halaman admin (Kelola peran + Sinkronisasi PD UNSRI).
         *
         * Semua tata letak di sini CSS biasa berawalan adm-, bukan class
         * Tailwind baru: bundle public/build tidak bisa di-rebuild di server
         * ini, dan utility yang belum pernah dipakai di tempat lain tidak ikut
         * ter-compile -- diam-diam tidak berlaku. Warna diambil dari token
         * app.css supaya ikut tema terang/gelap, kecuali header yang sengaja
         * tetap (sama seperti panel kiri halaman login).
         */
        [hidden] { display: none !important; }

        /* Hanya untuk pembaca layar -- utility sr-only milik Tailwind tidak
           ada di bundle yang ter-build. */
        .adm-sr {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .adm-wrap {
            width: 100%;
            max-width: 76rem;
            margin: 0 auto;
            padding-inline: 1rem;
        }

        @media (min-width: 640px) {
            .adm-wrap { padding-inline: 1.5rem; }
        }

        /* --- Header ------------------------------------------------------ */
        .adm-hero {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            padding-top: 1.1rem;
            color: #f8fafc;
            background: linear-gradient(165deg, var(--brand-ink) 0%, color-mix(in srgb, var(--brand-ink) 62%, #05070b) 100%);
        }

        .adm-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .adm-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #ffffff;
            text-decoration: none;
        }

        .adm-brand img {
            width: 34px;
            height: 34px;
            padding: 4px;
            box-sizing: border-box;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
        }

        .adm-brand em {
            padding: 0.18rem 0.5rem;
            border-radius: 999px;
            font-size: 10px;
            font-style: normal;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--brand-light);
            background: rgba(var(--brand-soft-rgb), 0.2);
        }

        .adm-back {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            color: rgba(248, 250, 252, 0.9);
            text-decoration: none;
            background: rgba(255, 255, 255, 0.1);
            transition: background 120ms ease;
        }

        .adm-back:hover { background: rgba(255, 255, 255, 0.18); }

        .adm-topbar-end {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .adm-title {
            margin: 1.6rem 0 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        @media (min-width: 640px) {
            .adm-title { font-size: 30px; }
        }

        .adm-subtitle {
            margin: 0.4rem 0 0;
            max-width: 42rem;
            font-size: 13.5px;
            line-height: 1.5;
            color: rgba(226, 232, 240, 0.8);
        }

        /* Tab "map folder": tab aktif berwarna sama dengan latar halaman,
           jadi terlihat menyambung ke isi di bawahnya. */
        .adm-tabs {
            display: flex;
            gap: 0.3rem;
            margin-top: 1.4rem;
            overflow-x: auto;
        }

        .adm-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.1rem;
            border-radius: 0.95rem 0.95rem 0 0;
            font-size: 13.5px;
            font-weight: 800;
            white-space: nowrap;
            color: rgba(226, 232, 240, 0.82);
            text-decoration: none;
            transition: background 120ms ease, color 120ms ease;
        }

        .adm-tab:hover { color: #ffffff; background: rgba(255, 255, 255, 0.08); }

        /* Label dipendekkan di HP (lihat @sm di markup) dan jarak dirapatkan
           supaya ketiga tab muat tanpa digeser -- tab yang tersembunyi di
           luar layar tidak ada petunjuknya bagi pengguna. */
        @media (max-width: 639.98px) {
            .adm-tab { padding-inline: 0.75rem; font-size: 13px; }
        }

        .adm-tab.is-active {
            color: var(--color-ink);
            background: var(--color-canvas);
        }

        .adm-tab-count {
            padding: 0.08rem 0.45rem;
            border-radius: 999px;
            font-size: 11px;
            background: rgba(255, 255, 255, 0.14);
        }

        .adm-tab.is-active .adm-tab-count {
            color: var(--color-accent);
            background: var(--color-accent-soft);
        }

        /* --- Isi ---------------------------------------------------------- */
        .adm-main { padding-block: 1.5rem 3rem; }

        .adm-panel {
            border-radius: 1.15rem;
            overflow: hidden;
        }

        .adm-toast {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin-bottom: 1rem;
            padding: 0.8rem 1rem;
            border-radius: 0.95rem;
            font-size: 13px;
            font-weight: 600;
        }

        .adm-toast svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; }
        .adm-toast ul { margin: 0; padding-left: 1rem; }

        .adm-toast-ok {
            color: var(--color-accent);
            background: var(--color-accent-soft);
            box-shadow: inset 0 0 0 1px var(--color-accent-line);
        }

        .adm-toast-err {
            color: var(--color-warn);
            background: var(--color-warn-soft);
            box-shadow: inset 0 0 0 1px var(--color-warn-line);
        }

        .adm-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1rem 0.75rem;
        }

        .adm-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }

        .adm-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.75rem;
            border: 1px solid var(--input-border);
            border-radius: 999px;
            font: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--color-muted);
            background: var(--input-bg);
            cursor: pointer;
            transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
        }

        .adm-chip:hover { color: var(--color-ink); border-color: var(--color-accent-line); }

        .adm-chip b {
            padding: 0.04rem 0.42rem;
            border-radius: 999px;
            font-size: 11px;
            color: var(--color-accent);
            background: var(--color-accent-soft);
        }

        .adm-chip[aria-pressed="true"] {
            color: #ffffff;
            border-color: transparent;
            background: var(--color-accent);
        }

        .adm-chip[aria-pressed="true"] b { color: #ffffff; background: rgba(255, 255, 255, 0.24); }
        .adm-chip-warn b { color: var(--color-warn); background: var(--color-warn-soft); }

        .adm-search {
            position: relative;
            flex: 1 1 15rem;
            max-width: 26rem;
        }

        .adm-search svg {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            width: 16px;
            height: 16px;
            transform: translateY(-50%);
            color: var(--color-muted);
            pointer-events: none;
        }

        .adm-input {
            width: 100%;
            box-sizing: border-box;
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--input-border);
            border-radius: 0.8rem;
            font: inherit;
            font-size: 13.5px;
            color: var(--color-ink);
            background: var(--input-bg);
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }

        .adm-input::placeholder { color: var(--color-muted); opacity: 0.75; }

        .adm-input:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px var(--color-accent-soft);
        }

        .adm-search .adm-input { padding-left: 2.3rem; }

        .adm-count {
            padding: 0 1rem 0.65rem;
            font-size: 12px;
            font-weight: 700;
            color: var(--color-muted);
        }

        .adm-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            font-size: 13px;
            color: var(--color-muted);
        }

        .adm-empty strong { display: block; margin-bottom: 0.2rem; font-size: 14px; color: var(--color-ink); }

        /* Avatar, badge peran, dan teks orang -- dipakai kedua halaman. */
        .adm-avatar {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .adm-av-rektor { color: #ffffff; background: var(--brand-ink); }
        .adm-av-dekan { color: #ffffff; background: var(--brand); }

        .adm-av-korprodi {
            color: var(--color-accent);
            background: var(--color-accent-soft);
            box-shadow: inset 0 0 0 1px var(--color-accent-line);
        }

        .adm-badge {
            flex-shrink: 0;
            padding: 0.16rem 0.5rem;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .adm-badge-rektor { color: #ffffff; background: var(--brand-ink); }
        .adm-badge-dekan { color: #ffffff; background: var(--brand); }
        .adm-badge-korprodi { color: var(--color-accent); background: var(--color-accent-soft); }
        .adm-badge-warn { color: var(--color-warn); background: var(--color-warn-soft); }

        .adm-name,
        .adm-meta,
        .adm-scope-name,
        .adm-scope-sub {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .adm-name { font-size: 13.5px; font-weight: 700; color: var(--color-ink); }

        .adm-meta {
            margin-top: 0.12rem;
            font-size: 12px;
            color: var(--color-muted);
            font-variant-numeric: tabular-nums;
        }

        .adm-scope-line { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .adm-scope-name { font-size: 13px; font-weight: 600; color: var(--color-ink); }
        .adm-scope-sub { margin-top: 0.12rem; font-size: 11.5px; color: var(--color-muted); }
    </style>
    @stack('styles')
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <header class="adm-hero">
        <div class="adm-wrap">
            <div class="adm-topbar">
                <a href="{{ route('admin.peran') }}" class="adm-brand">
                    <img src="{{ asset('images/logo-unsri.webp') }}" alt="">
                    SAPTO <em>Admin</em>
                </a>
                <div class="adm-topbar-end">
                    @include('components.api-status-badge', ['variant' => 'hero'])
                    <a href="{{ url('/') }}" class="adm-back">← Kembali ke aplikasi</a>
                </div>
            </div>

            <h1 class="adm-title">@yield('title')</h1>
            <p class="adm-subtitle">@yield('subtitle')</p>

            <nav class="adm-tabs" aria-label="Menu admin">
                <a href="{{ route('admin.peran') }}" @class(['adm-tab', 'is-active' => request()->routeIs('admin.peran')])
                   @if (request()->routeIs('admin.peran')) aria-current="page" @endif>
                    Kelola peran
                </a>
                <a href="{{ route('admin.sync-pd-unsri') }}" @class(['adm-tab', 'is-active' => request()->routeIs('admin.sync-pd-unsri')])
                   @if (request()->routeIs('admin.sync-pd-unsri')) aria-current="page" @endif>
                    Sinkronisasi<span class="hidden sm:inline">&nbsp;PD UNSRI</span>
                </a>
                <a href="{{ route('admin.kafka') }}" @class(['adm-tab', 'is-active' => request()->routeIs('admin.kafka')])
                   @if (request()->routeIs('admin.kafka')) aria-current="page" @endif>
                    Event Stream<span class="hidden sm:inline">&nbsp;(Kafka)</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="adm-wrap adm-main">
        @yield('content')
    </main>

    <script>
        (function () {
            /*
             * Filter daftar: satu komponen untuk kedua halaman. Wadah
             * [data-filter] berisi chip [data-chip], input [data-filter-search],
             * item [data-item] (dengan data-search dan data-tags), penghitung
             * [data-filter-count], dan keadaan kosong [data-filter-empty].
             */
            document.querySelectorAll('[data-filter]').forEach(function (root) {
                var input = root.querySelector('[data-filter-search]');
                var items = Array.prototype.slice.call(root.querySelectorAll('[data-item]'));
                var chips = Array.prototype.slice.call(root.querySelectorAll('[data-chip]'));
                var count = root.querySelector('[data-filter-count]');
                var empty = root.querySelector('[data-filter-empty]');
                var satuan = root.getAttribute('data-filter') || 'baris';
                var aktif = 'semua';

                function terapkan() {
                    var q = input ? input.value.trim().toLowerCase() : '';
                    var tampil = 0;
                    var pertama = null;

                    items.forEach(function (el) {
                        var cocokChip = aktif === 'semua' || (' ' + el.dataset.tags + ' ').indexOf(' ' + aktif + ' ') !== -1;
                        var cocokTeks = !q || (el.dataset.search || '').indexOf(q) !== -1;
                        var show = cocokChip && cocokTeks;
                        el.hidden = !show;
                        el.classList.remove('is-first');
                        if (show) {
                            tampil++;
                            if (!pertama) pertama = el;
                        }
                    });

                    if (pertama && q) pertama.classList.add('is-first');
                    if (count) count.textContent = (tampil === items.length ? tampil : tampil + ' dari ' + items.length) + ' ' + satuan;
                    if (empty) empty.hidden = tampil > 0;
                    root.firstVisible = pertama;
                }

                chips.forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        aktif = chip.dataset.chip;
                        chips.forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
                        terapkan();
                    });
                });

                if (input) input.addEventListener('input', terapkan);
                terapkan();
            });

            /*
             * Konfirmasi dua klik untuk aksi yang merusak: klik pertama
             * mengubah tombol jadi "Yakin?" selama 4 detik, klik kedua baru
             * mengirim. Lebih cepat dari dialog confirm() dan tidak mungkin
             * terpicu tidak sengaja oleh satu klik nyasar.
             */
            document.querySelectorAll('[data-arm]').forEach(function (btn) {
                var asli = btn.innerHTML;
                var timer;
                btn.addEventListener('click', function (e) {
                    if (btn.classList.contains('is-armed')) return;
                    e.preventDefault();
                    btn.classList.add('is-armed');
                    btn.textContent = btn.dataset.arm;
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        btn.classList.remove('is-armed');
                        btn.innerHTML = asli;
                    }, 4000);
                });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
