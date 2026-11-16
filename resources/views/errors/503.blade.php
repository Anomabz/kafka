<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPTO sedang tidak tersedia</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Salinan token "Warna merek" dari resources/css/app.css (halaman ini
           tidak memuat app.css): desktop Biru Sriwijaya, HP Merah Marun. */
        :root { --brand: #2b4f8e; --brand-rgb: 43, 79, 142; --brand-ink: #12203a; --brand-ink-rgb: 18, 32, 58; --brand-muted: #4f6fa6; --brand-light: #cddcf6; --brand-soft: #9dbbef; --brand-soft-rgb: 157, 187, 239; }
        @media (max-width: 639.98px) {
            :root { --brand: #8a2a3a; --brand-rgb: 138, 42, 58; --brand-ink: #2a0b11; --brand-ink-rgb: 42, 11, 17; --brand-muted: #a8495a; --brand-light: #f1c9cf; --brand-soft: #eba3ae; --brand-soft-rgb: 235, 163, 174; }
        }
        /*
         * Halaman 503 (dipakai saat maintenance mode / container restart
         * oleh watchdog -- lihat CLAUDE.md, cron 5 menit). Sengaja
         * fixed-dark terlepas dari preferensi tema, sama seperti panel kiri
         * halaman login -- ini bukan bagian dari app shell, jadi tidak ada
         * konteks data-theme user untuk diikuti. Semua warna karena itu
         * hardcoded, bukan var(--color-*). Ini juga satu-satunya halaman
         * yang boleh lebih "hidup" (glow, cincin berdenyut) -- bukan layar
         * dashboard yang dipelototin tiap hari, jadi karakternya boleh lebih
         * berani daripada gaya flat di app shell.
         */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            min-height: 100%;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            color: #e7e9ec;
            -webkit-font-smoothing: antialiased;
        }

        .wrap {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            text-align: center;
            background:
                radial-gradient(46rem 34rem at 50% 8%, rgba(var(--brand-soft-rgb), 0.16), transparent 62%),
                radial-gradient(38rem 30rem at 100% 100%, rgba(var(--brand-soft-rgb), 0.08), transparent 60%),
                #0c0e11;
        }

        /* Medali batik Palembang -- aksen identitas budaya yang sama dengan
           halaman login, dua bentuk besar (bukan ubin di-repeat) supaya
           komposisi halaman penuh yang sengaja lebih lega dari login. */
        .batik-medallion {
            position: absolute;
            pointer-events: none;
            z-index: -1;
        }

        .batik-medallion.br { right: -90px; bottom: -90px; opacity: 0.5; }
        .batik-medallion.tl { left: -110px; top: -110px; opacity: 0.28; transform: rotate(20deg); }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 2.75rem;
        }

        .brand img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .brand span {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #f2f4f2;
        }

        .icon-wrap {
            position: relative;
            display: grid;
            place-items: center;
            width: 108px;
            height: 108px;
            margin-bottom: 2.1rem;
        }

        .ring {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            border: 1.5px solid rgba(var(--brand-soft-rgb), 0.4);
            opacity: 0;
            animation: ringPulse 2.6s 0.2s ease-out infinite;
        }

        .ring.r2 {
            animation-delay: 1.1s;
        }

        @keyframes ringPulse {
            0% { transform: scale(0.6); opacity: 0; }
            20% { opacity: 0.8; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .icon {
            position: relative;
            z-index: 1;
            width: 72px;
            height: 72px;
            border-radius: 1.1rem;
            background: linear-gradient(160deg, rgba(var(--brand-soft-rgb), 0.22), rgba(var(--brand-soft-rgb), 0.08));
            border: 1px solid rgba(var(--brand-soft-rgb), 0.35);
            display: grid;
            place-items: center;
        }

        .icon svg {
            width: 32px;
            height: 32px;
            stroke: var(--brand-soft);
            stroke-width: 1.6;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        h1 {
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            max-width: 30rem;
        }

        .subtitle {
            font-size: 14px;
            line-height: 1.65;
            color: #98a1ac;
            max-width: 27rem;
            margin-bottom: 1rem;
        }

        .status-line {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 12px;
            color: #7c8791;
            margin-bottom: 2.1rem;
        }

        .status-line .dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: var(--brand-soft);
            animation: dotPulse 1.4s ease-in-out infinite;
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 0.35; }
            50% { opacity: 1; }
        }

        .retry-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.8rem 1.5rem;
            border-radius: 0.7rem;
            border: 0;
            background: var(--brand-soft);
            color: #0c0e11;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 140ms ease, background 140ms ease;
        }

        .retry-btn:hover { background: var(--brand-light); }
        .retry-btn:active { transform: scale(0.98); }

        .retry-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .foot {
            position: absolute;
            bottom: 1.25rem;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11.5px;
            color: #52585e;
        }

        @media (max-width: 480px) {
            .icon-wrap { width: 88px; height: 88px; }
            .icon { width: 60px; height: 60px; border-radius: 0.9rem; }
            .icon svg { width: 26px; height: 26px; }
        }
    </style>
</head>
<body>

    <main class="wrap">
        <svg class="batik-medallion br" width="300" height="300" viewBox="0 0 280 280" aria-hidden="true">
            <defs>
                <radialGradient id="fadeBr" cx="35%" cy="35%" r="70%">
                    <stop offset="45%" stop-color="#fff" stop-opacity="1"/>
                    <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
                </radialGradient>
                <mask id="maskBr"><rect width="280" height="280" fill="url(#fadeBr)"/></mask>
            </defs>
            <g mask="url(#maskBr)" fill="none" style="stroke:var(--brand-soft)" stroke-width="1.1">
                <circle cx="140" cy="140" r="118"/>
                <circle cx="140" cy="140" r="96"/>
                <g transform="translate(140,140)">
                    <g stroke-width="1.3">
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(0)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(45)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(90)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(135)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(180)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(225)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(270)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(315)"/>
                    </g>
                    <circle r="16"/>
                    <circle r="8" style="fill:var(--brand-soft)" stroke="none"/>
                </g>
            </g>
        </svg>

        <svg class="batik-medallion tl" width="240" height="240" viewBox="0 0 280 280" aria-hidden="true">
            <defs>
                <radialGradient id="fadeTl" cx="35%" cy="35%" r="70%">
                    <stop offset="45%" stop-color="#fff" stop-opacity="1"/>
                    <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
                </radialGradient>
                <mask id="maskTl"><rect width="280" height="280" fill="url(#fadeTl)"/></mask>
            </defs>
            <g mask="url(#maskTl)" fill="none" style="stroke:var(--brand-soft)" stroke-width="1.1">
                <circle cx="140" cy="140" r="118"/>
                <circle cx="140" cy="140" r="96"/>
                <g transform="translate(140,140)">
                    <g stroke-width="1.3">
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(0)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(45)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(90)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(135)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(180)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(225)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(270)"/>
                        <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(315)"/>
                    </g>
                    <circle r="16"/>
                    <circle r="8" style="fill:var(--brand-soft)" stroke="none"/>
                </g>
            </g>
        </svg>

        <div class="brand">
            <img src="{{ asset('images/logo-unsri.webp') }}" alt="Lambang Universitas Sriwijaya">
            <span>SAPTO</span>
        </div>

        <div class="icon-wrap">
            <span class="ring"></span>
            <span class="ring r2"></span>
            <div class="icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14.7 6.3a4 4 0 0 1-5.6 5.6L4 17v3h3l5.1-5.1a4 4 0 0 1 5.6-5.6l-2.5 2.5-2-2 2.5-2.5Z"/>
                </svg>
            </div>
        </div>

        <h1>Sistem sedang dalam pemeliharaan</h1>
        <p class="subtitle">
            SAPTO sedang tidak bisa diakses sementara karena ada pemeliharaan
            di sisi server. Biasanya cuma beberapa menit.
        </p>

        <div class="status-line" id="status-line">
            <span class="dot"></span>
            <span id="status-text">Mencoba menyambung ulang secara otomatis&hellip;</span>
        </div>

        <button type="button" class="retry-btn" onclick="window.location.reload()">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v5h-5"/></svg>
            Muat ulang halaman
        </button>
    </main>

    <div class="foot">&copy; {{ date('Y') }} SAPTO &mdash; Universitas Sriwijaya</div>

    <script>
        /*
         * Auto-refresh: cek route "/ping" (routes/web.php) tiap beberapa
         * detik. SENGAJA bukan "/up" bawaan Laravel -- "/up" dikecualikan
         * dari PreventRequestsDuringMaintenance jadi selalu 200 walau
         * masih "php artisan down", makanya tidak bisa dipakai buat
         * mendeteksi maintenance mode itu sendiri (lihat komentar di route
         * "/ping"). Begitu "/ping" balik 200 lagi (maintenance mode sudah
         * dicabut lewat "php artisan up"), halaman di-reload otomatis --
         * pengguna tidak perlu tahu atau mengklik apa pun. Interval 8 detik
         * supaya pulihnya ketahuan cepat tanpa membebani server dengan
         * request bertubi-tubi.
         */
        (function () {
            var statusText = document.getElementById('status-text');
            var attempt = 0;

            function checkAndReload() {
                attempt++;
                fetch('/ping', { cache: 'no-store' })
                    .then(function (res) {
                        if (res.ok) {
                            statusText.textContent = 'Server sudah kembali, memuat ulang…';
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        // Masih down -- diamkan, coba lagi di interval berikutnya.
                    });
            }

            setInterval(checkAndReload, 8000);
        })();
    </script>

</body>
</html>
