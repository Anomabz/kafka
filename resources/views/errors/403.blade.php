<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses ditolak — SAPTO</title>
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
                if (m) m.setAttribute('content', t === 'dark' ? '#0a0a0c' : '#e7ecf5');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <div class="grid min-h-screen place-items-center px-4 py-10">
        <div class="glass w-full max-w-md rounded-3xl px-8 py-10 text-center">
            <img
                src="{{ asset('images/logo-unsri.webp') }}"
                alt="Lambang Universitas Sriwijaya"
                class="mx-auto h-12 w-12 object-contain"
            >

            <p class="mt-6 text-[64px] font-extrabold leading-none tracking-tight text-accent">403</p>

            <h1 class="mt-3 text-[19px] font-bold text-ink">Akses ditolak</h1>
            <p class="mt-2 text-[13px] leading-relaxed text-muted">
                Akun kamu tidak memiliki izin untuk membuka halaman ini.
            </p>

            <div class="mt-7 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <a
                    href="{{ url('/') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-5 py-2.5 text-[13px] font-bold text-white transition hover:opacity-90"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M9 22V12h6v10"/>
                    </svg>
                    Kembali ke Beranda
                </a>
                <button
                    type="button"
                    onclick="history.back()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-[13px] font-bold text-ink transition hover:bg-slate-soft"
                    style="border:1px solid var(--color-line)"
                >
                    Halaman Sebelumnya
                </button>
            </div>

            <p class="mt-7 text-[11px] font-medium text-muted">SAPTO — Sistem Akreditasi Perguruan Tinggi Online</p>
        </div>
    </div>
</body>
</html>
