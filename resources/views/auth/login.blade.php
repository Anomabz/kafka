<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    {{-- Judul halaman = sinyal on-page terkuat, jadi diawali nama yang
         benar-benar dicari orang ("sapto unsri"), bukan kata "Masuk" atau
         nomor versi. Versi tetap ada di kaki kartu. --}}
    <title>SAPTO Unsri — Sistem Akreditasi Perguruan Tinggi Online</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    @include('components.seo-meta')
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
    <style>
        /*
         * Scoped to the login page only. Layout/motion here, but every colour
         * and surface is pulled from app.css's existing design tokens so this
         * page re-skins with the rest of the app on the light/dark toggle.
         */
        .auth-shell {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 1.5rem;
            position: relative;
            isolation: isolate;
            /* Gerbang kampus UNSRI, sengaja diredupkan lewat gradient + foto
               yang sudah diproses gelap/blur -- supaya .auth-card (glass) di
               atasnya tetap gampang dibaca dan tidak baku hantam warna
               dengan .auth-context yang gradasinya sudah gelap juga. Fixed
               background ini cuma berlaku di halaman login (scoped ke sini),
               tidak menyentuh app.css/halaman lain. */
            background:
                linear-gradient(165deg, rgba(5, 9, 16, 0.88) 0%, rgba(11, 15, 30, 0.78) 45%, rgba(30, 27, 75, 0.6) 100%),
                url('{{ asset('images/login-bg.jpg') }}') center/cover no-repeat fixed;
        }

        /* Baris brand mobile duduk langsung di atas foto (bukan di dalam
           kartu glass), dan foto itu sekarang selalu gelap terlepas dari
           tema -- teks perlu warna terang tetap supaya tidak tenggelam saat
           tema terang dipilih (text-ink jadi gelap di tema terang). */
        .auth-mobile-brand,
        .auth-mobile-brand .text-ink {
            color: #f8fdfc;
        }

        .auth-mobile-brand .text-muted,
        .auth-mobile-stat .text-muted {
            color: rgba(243, 250, 248, 0.72);
        }

        .auth-mobile-stat.glass {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.22);
        }

        .auth-grid {
            display: grid;
            width: 100%;
            max-width: 62rem;
            gap: 1.25rem;
            grid-template-columns: 1fr;
        }

        .auth-mobile-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            max-width: 24rem;
            margin: 0 auto 1.5rem;
        }

        @media (min-width: 1024px) {
            .auth-mobile-brand {
                display: none;
            }
        }

        /* Kartu stat kecil khusus mobile — di desktop info yang sama sudah
           ada di .auth-context (panel bermerek kiri), jadi cukup diulang di
           sini dengan porsi lebih ringkas supaya mobile tidak terasa cuma
           tombol kosong tanpa konteks/identitas produk. */
        .auth-mobile-stat {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            max-width: 24rem;
            margin: 0 auto 1.25rem;
            padding: 0.75rem 1rem;
            border-radius: 0.9rem;
        }

        .auth-mobile-stat-number {
            flex-shrink: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-accent);
        }

        @media (min-width: 1024px) {
            .auth-mobile-stat {
                display: none;
            }
        }

        /*
         * Mobile gets its own layout, not just the desktop one squeezed into
         * one column: only the "Masuk" card shows (that's the actual task) —
         * the branded panel is desktop-only, restored at the 1024px breakpoint.
         */
        .auth-context {
            display: none;
        }

        @media (min-width: 1024px) {
            .auth-grid {
                grid-template-columns: 0.82fr 1fr;
                gap: 0;
            }

            .auth-context {
                display: flex;
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
            }

            .auth-card {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
            }
        }

        /*
         * The context (left) panel is a fixed branded surface — deliberately
         * NOT theme-driven like the rest of the app, the same way a lot of
         * split-screen login pages keep one side as a constant brand
         * statement regardless of light/dark mode. Every colour in here is
         * therefore hardcoded, not a --color-* token.
         */
        .auth-context {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            padding: 3.5rem 2.25rem;
            flex-direction: column;
            justify-content: space-between;
            gap: 2.5rem;
            color: #f3faf8;
            background:
                radial-gradient(38rem 30rem at 108% -12%, rgba(255, 255, 255, 0.16), transparent 60%),
                radial-gradient(30rem 26rem at -12% 112%, rgba(129, 140, 248, 0.18), transparent 60%),
                linear-gradient(155deg, #081018 0%, #151a3d 32%, #312e81 78%, #4338ca 100%);
        }

        .auth-context::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1px);
            background-size: 22px 22px;
            -webkit-mask-image: radial-gradient(46rem 34rem at 12% 8%, black, transparent 72%);
            mask-image: radial-gradient(46rem 34rem at 12% 8%, black, transparent 72%);
        }

        .auth-context .text-ink {
            color: #f8fdfc;
        }

        .auth-context .text-muted {
            color: rgba(243, 250, 248, 0.72);
        }

        .auth-context .bg-accent {
            background-color: #a5b4fc;
        }

        .auth-context-inner {
            position: relative;
            z-index: 1;
            max-width: 21rem;
            margin: 0 auto;
            width: 100%;
        }

        .auth-context-headline {
            margin-top: 1.75rem;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.01em;
            color: #f8fdfc;
        }

        .auth-stat {
            display: flex;
            align-items: baseline;
            gap: 0.65rem;
        }

        .auth-stat-number {
            font-size: 46px;
            font-weight: 800;
            line-height: 1;
            color: #a5b4fc;
            letter-spacing: -0.02em;
        }

        .auth-stat-label {
            max-width: 11rem;
            font-size: 12.5px;
            line-height: 1.4;
            color: rgba(243, 250, 248, 0.72);
        }

        .auth-pillars {
            list-style: none;
            margin: 0;
            padding: 0;
            flex-direction: column;
            gap: 1rem;
        }

        .auth-pillars .pillar-label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            color: #f8fdfc;
        }

        .auth-pillars .pillar-hint {
            display: block;
            margin-top: 0.1rem;
            font-size: 12px;
            color: rgba(243, 250, 248, 0.72);
        }

        .auth-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 1.5rem;
        }

        @media (min-width: 480px) {
            .auth-card {
                padding: 2.5rem 2rem;
            }
        }

        @media (min-width: 1024px) {
            .auth-card {
                padding: 3rem;
            }
        }

        .auth-card-inner {
            width: 100%;
            max-width: 24rem;
            margin: 0 auto;
        }

        /*
         * The rest of this file only uses Tailwind utility classes already
         * present elsewhere in the app (so the pre-built CSS in public/build
         * already contains them). Anything new/one-off for this page (sizes,
         * spacing, positions below) is plain CSS instead of a fresh Tailwind
         * arbitrary-value class, since those wouldn't exist in that bundle
         * until the next `npm run build`.
         */
        .auth-logo {
            width: 48px;
            height: 48px;
        }

        .auth-context .auth-logo {
            width: 56px;
            height: 56px;
            padding: 7px;
            box-sizing: border-box;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .auth-pillar-icon {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #a5b4fc;
        }

        .auth-pillar-icon svg {
            width: 14px;
            height: 14px;
        }

        .auth-pillars li {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
        }

        .auth-pillars li > div {
            padding-top: 0.1rem;
        }

        .icon-16 {
            width: 16px;
            height: 16px;
        }

        .auth-wordmark {
            font-size: 20px;
        }

        .auth-heading {
            font-size: 26px;
            line-height: 1.15;
        }

        @media (min-width: 480px) {
            .auth-heading {
                font-size: 30px;
            }
        }

        .auth-tagline {
            max-width: 20rem;
        }

        .auth-mt-4 {
            margin-top: 1rem;
        }

        .auth-mt-5 {
            margin-top: 1.25rem;
        }

        .auth-submit {
            padding: 0.625rem 1rem;
            text-decoration: none;
            transition: opacity 120ms ease;
        }

        .auth-submit:hover {
            opacity: 0.9;
        }

        .auth-pillars {
            display: none;
        }

        @media (min-width: 1024px) {
            .auth-pillars {
                display: flex;
            }
        }

        /* --- Kartu "Masuk": isinya sengaja berlapis (eyebrow, headline
           besar, CTA menyala, 3 langkah, catatan sesi bersama) supaya kartu
           tidak jadi slab kosong sejak form kredensialnya dipindah ke
           Keycloak. Semua warna pakai token app.css biar ikut tema. --- */
        .auth-card {
            position: relative;
            overflow: hidden;
        }

        /* Glow sudut + garis aksen di tepi atas: dua sentuhan kecil yang
           menyambungkan kartu ke panel kiri, supaya permukaan glass-nya tidak
           terbaca sebagai kotak abu-abu polos. */
        .auth-card::before {
            content: '';
            position: absolute;
            top: -8rem;
            right: -8rem;
            width: 22rem;
            height: 22rem;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.28), transparent 68%);
            pointer-events: none;
        }

        .auth-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #312e81 0%, #4338ca 40%, #6366f1 70%, #a5b4fc 100%);
            pointer-events: none;
        }

        .auth-card-inner {
            position: relative;
            z-index: 1;
        }

        .auth-accent-word {
            color: var(--color-accent);
        }

        /* CTA utama: satu-satunya aksi di halaman ini, jadi dibikin paling
           berat secara visual (gradasi + glow senada panel kiri). */
        .auth-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            width: 100%;
            margin-top: 1.4rem;
            padding: 0.95rem 1.25rem;
            border-radius: 0.9rem;
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            background: linear-gradient(135deg, #4338ca 0%, #6366f1 55%, #312e81 100%);
            box-shadow: 0 12px 26px -10px rgba(67, 56, 202, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.18);
            transition: transform 140ms ease, box-shadow 140ms ease, filter 140ms ease;
        }

        .auth-cta:hover {
            transform: translateY(-1px);
            filter: brightness(1.06);
            box-shadow: 0 16px 32px -10px rgba(67, 56, 202, 0.95), inset 0 1px 0 rgba(255, 255, 255, 0.22);
        }

        .auth-cta svg {
            width: 16px;
            height: 16px;
            transition: transform 140ms ease;
        }

        .auth-cta:hover svg {
            transform: translateX(3px);
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin-top: 1.6rem;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--color-muted);
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: currentColor;
            opacity: 0.28;
        }

        .auth-steps {
            list-style: none;
            margin: 1.1rem 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .auth-steps li {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        /* Garis penyambung antar nomor langkah -- bikin tiga item terbaca
           sebagai satu alur, bukan tiga bullet lepas. */
        .auth-steps li:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 26px;
            bottom: -0.9rem;
            width: 1px;
            background: linear-gradient(180deg, var(--color-accent-line), transparent);
        }

        .auth-step-num {
            flex-shrink: 0;
            display: grid;
            place-items: center;
            width: 23px;
            height: 23px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            color: var(--color-accent);
            background: var(--color-accent-soft);
            box-shadow: inset 0 0 0 1px var(--color-accent-line);
        }

        .auth-step-text {
            font-size: 12px;
            line-height: 1.45;
            color: var(--color-muted);
        }

        .auth-step-text strong {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--color-ink);
        }

        .auth-note {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            margin-top: 1.5rem;
            padding: 0.7rem 0.85rem;
            border-radius: 0.8rem;
            font-size: 11.5px;
            line-height: 1.45;
            color: var(--color-muted);
            background: var(--color-accent-soft);
            box-shadow: inset 0 0 0 1px var(--color-accent-line);
        }

        .auth-foot {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.6rem;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--color-muted);
        }

        .auth-foot span {
            width: 4px;
            height: 4px;
            border-radius: 999px;
            background: var(--color-accent);
        }

        .auth-note svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 1px;
            color: var(--color-accent);
        }

        .auth-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--scrim);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
        }

        .auth-modal {
            position: relative;
            width: 100%;
            max-width: 22rem;
            padding: 1.75rem;
            text-align: center;
        }

        .auth-modal-close {
            position: absolute;
            right: 0.75rem;
            top: 0.75rem;
        }

        .auth-modal-icon {
            margin: 0 auto;
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: var(--color-warn-soft);
            color: var(--color-warn);
        }

        .auth-modal-icon svg {
            width: 20px;
            height: 20px;
        }

        .auth-modal-title {
            margin-top: 0.9rem;
            font-size: 16px;
            font-weight: 800;
            color: var(--color-ink);
        }

        .auth-modal-desc {
            margin-top: 0.4rem;
            font-size: 13px;
            line-height: 1.5;
            color: var(--color-muted);
        }

        @media (prefers-reduced-motion: no-preference) {
            .auth-context,
            .auth-card {
                animation: auth-rise 460ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .auth-card {
                animation-delay: 70ms;
            }

            .auth-pillars li {
                animation: auth-rise 420ms ease both;
            }

            .auth-pillars li:nth-child(1) { animation-delay: 180ms; }
            .auth-pillars li:nth-child(2) { animation-delay: 250ms; }
            .auth-pillars li:nth-child(3) { animation-delay: 320ms; }

            .auth-modal {
                animation: auth-rise 260ms ease both;
            }
        }

        @keyframes auth-rise {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <div class="auth-shell">
        <div class="auth-mobile-brand">
            <div class="auth-logo grid shrink-0 place-items-center">
                <img
                    src="{{ asset('images/logo-unsri.webp') }}"
                    alt="Lambang Universitas Sriwijaya"
                    class="h-full w-full object-contain"
                />
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="auth-wordmark font-extrabold tracking-tight text-ink">SAPTO</span>
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                </div>
                <p class="text-[12px] font-medium text-muted">Sistem Akreditasi Perguruan Tinggi Online</p>
            </div>
        </div>

        <div class="auth-mobile-stat glass">
            <span class="auth-mobile-stat-number">45</span>
            <span class="text-[12px] leading-snug text-muted">tabel pelaporan Tridharma BAN-PT dalam satu sistem</span>
        </div>

        <div class="auth-grid">
            <div class="auth-context rounded-2xl">
                <div class="auth-context-inner">
                    <div class="flex items-center gap-3">
                        <div class="auth-logo grid shrink-0 place-items-center">
                            <img
                                src="{{ asset('images/logo-unsri.webp') }}"
                                alt="Lambang Universitas Sriwijaya"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="auth-wordmark font-extrabold tracking-tight text-ink">SAPTO</span>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                            </div>
                            <p class="text-[12px] font-medium text-muted">Sistem Akreditasi Perguruan Tinggi Online</p>
                        </div>
                    </div>

                    <h2 class="auth-context-headline">Satu sistem akreditasi online untuk seluruh program studi Universitas Sriwijaya</h2>

                    <p class="auth-mt-5 auth-tagline text-[13px] leading-relaxed text-muted">
                        Kelola dan pantau kelengkapan data akreditasi BAN-PT/LAM — dari capaian dosen, penelitian, hingga profil lulusan — dalam satu dashboard yang selalu sinkron dengan data resmi kampus.
                    </p>
                </div>

                <div class="auth-context-inner">
                    <div class="auth-stat">
                        <span class="auth-stat-number">45</span>
                        <span class="auth-stat-label">tabel pelaporan Tridharma dalam satu sistem</span>
                    </div>
                </div>

                <div class="auth-context-inner">
                    <ul class="auth-pillars">
                        <li>
                            <div class="auth-pillar-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m22 10-10-5L2 10l10 5 10-5Z"/>
                                    <path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/>
                                </svg>
                            </div>
                            <div>
                                <span class="pillar-label">Pendidikan</span>
                                <span class="pillar-hint">Kurikulum, dosen, dan capaian mahasiswa</span>
                            </div>
                        </li>
                        <li>
                            <div class="auth-pillar-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 3h6M10 3v6.5L4.5 19a1 1 0 0 0 .9 1.5h13.2a1 1 0 0 0 .9-1.5L14 9.5V3"/>
                                </svg>
                            </div>
                            <div>
                                <span class="pillar-label">Penelitian</span>
                                <span class="pillar-hint">Luaran riset dosen dan mahasiswa</span>
                            </div>
                        </li>
                        <li>
                            <div class="auth-pillar-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78Z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="pillar-label">Pengabdian kepada Masyarakat</span>
                                <span class="pillar-hint">Kerjasama dan kegiatan PkM</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="auth-card glass-strong rounded-2xl">
              <div class="auth-card-inner">
                <h1 class="auth-heading font-extrabold tracking-tight text-ink">Selamat datang di <span class="auth-accent-word">SAPTO</span></h1>
                <p class="mt-1 text-[13px] text-muted">Portal pelaporan akreditasi Tridharma untuk koordinator program studi Universitas Sriwijaya.</p>

                <a href="{{ route('sso.login') }}" id="login-submit" class="auth-cta">
                    <span id="login-submit-label">Masuk</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h13m-5-6 6 6-6 6"/>
                    </svg>
                </a>

                <div class="auth-divider">Cara masuk</div>

                <ol class="auth-steps">
                    <li>
                        <span class="auth-step-num">1</span>
                        <span class="auth-step-text">
                            <strong>Tekan tombol di atas</strong>
                            Anda dialihkan ke halaman login resmi portal SSO Unsri.
                        </span>
                    </li>
                    <li>
                        <span class="auth-step-num">2</span>
                        <span class="auth-step-text">
                            <strong>Masukkan akun Unsri di sana</strong>
                            Kata sandi diketik di halaman SSO, tidak pernah di SAPTO.
                        </span>
                    </li>
                    <li>
                        <span class="auth-step-num">3</span>
                        <span class="auth-step-text">
                            <strong>Kembali otomatis</strong>
                            Begitu login berhasil, SAPTO langsung terbuka.
                        </span>
                    </li>
                </ol>

                <p class="auth-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 21s7-3.6 7-9V6l-7-3-7 3v6c0 5.4 7 9 7 9Z"/>
                    </svg>
                    Sudah login di aplikasi kampus lain? Sesinya sama — Anda langsung masuk tanpa mengetik ulang.
                </p>

                <p class="auth-foot">
                    SAPTO v1.0
                    <span></span>
                    Universitas Sriwijaya
                </p>
              </div>
            </div>
        </div>
    </div>

    @if ($notKorprodiHint)
        <div class="auth-modal-backdrop" id="not-korprodi-backdrop">
            <div
                class="auth-modal glass-strong rounded-2xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="not-korprodi-title"
                aria-describedby="not-korprodi-desc"
                tabindex="-1"
            >
                <button type="button" id="not-korprodi-close" class="theme-toggle auth-modal-close" aria-label="Tutup">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="auth-modal-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                    </svg>
                </div>
                <h2 id="not-korprodi-title" class="auth-modal-title">Akses ditolak</h2>
                <p id="not-korprodi-desc" class="auth-modal-desc">
                    Akun <strong>{{ $notKorprodiHint }}</strong> belum terdaftar di SAPTO. Hubungi admin SAPTO untuk didaftarkan sebagai korprodi, dekan, atau rektor.
                </p>
            </div>
        </div>
    @endif

    <script>
        (function () {
            var loginBtn = document.getElementById('login-submit');
            var loginLabel = document.getElementById('login-submit-label');
            if (loginBtn && loginLabel) {
                loginBtn.addEventListener('click', function () {
                    loginLabel.textContent = 'Mengalihkan ke SSO…';
                });
            }

            var notKorprodiBackdrop = document.getElementById('not-korprodi-backdrop');
            var notKorprodiClose = document.getElementById('not-korprodi-close');
            if (notKorprodiBackdrop) {
                var notKorprodiModal = notKorprodiBackdrop.querySelector('.auth-modal');
                if (notKorprodiModal) notKorprodiModal.focus();
                if (notKorprodiClose) notKorprodiClose.addEventListener('click', function () { notKorprodiBackdrop.remove(); });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') notKorprodiBackdrop.remove();
                });
            }
        })();
    </script>
</body>
</html>
