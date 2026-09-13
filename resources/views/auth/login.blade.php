<!DOCTYPE html>
{{--
    data-theme="dark" tetap (bukan ikut preferensi tersimpan/OS seperti
    halaman lain) -- login/intro sengaja selalu gelap terlepas dari mode
    terang/gelap yang dipilih user di aplikasi.
--}}
<html lang="id" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0c">
    {{-- Judul halaman = sinyal on-page terkuat, jadi diawali nama yang
         benar-benar dicari orang ("sapto unsri"), bukan kata "Masuk" atau
         nomor versi. Versi tetap ada di kaki kartu. --}}
    <title>SAPTO Unsri — Sistem Akreditasi Perguruan Tinggi Online</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    @include('components.seo-meta')
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
                linear-gradient(165deg, rgba(11, 16, 11, 0.88) 0%, rgba(28, 38, 28, 0.78) 45%, rgba(51, 80, 46, 0.6) 100%),
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
            background: #1c261c;
        }

        /* Motif batik Palembang -- satu medali bunga besar mengambang di
           pojok kanan-bawah panel (bukan ubin kecil di-repeat, biar rapi
           dan tidak ada risiko sambungan tile patah-patah). Markup SVG-nya
           ada di auth-context, tepat sebelum .auth-context-inner pertama. */
        .batik-medallion {
            position: absolute;
            right: -70px;
            bottom: -70px;
            z-index: -1;
            pointer-events: none;
            opacity: 0.5;
        }

        .auth-context .text-ink {
            color: #f8fdfc;
        }

        .auth-context .text-muted {
            color: rgba(243, 250, 248, 0.72);
        }

        .auth-context .bg-accent {
            background-color: #b7dba9;
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
            color: #b7dba9;
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
            color: #b7dba9;
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
            background: radial-gradient(circle, rgba(99, 150, 90, 0.28), transparent 68%);
            pointer-events: none;
        }

        .auth-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #33502e 0%, #4b7a42 40%, #63965a 70%, #b7dba9 100%);
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
            border: 0;
            border-radius: 0.9rem;
            font-family: inherit;
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
            background: linear-gradient(135deg, #4b7a42 0%, #63965a 55%, #33502e 100%);
            box-shadow: 0 12px 26px -10px rgba(75, 122, 66, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.18);
            transition: transform 140ms ease, box-shadow 140ms ease, filter 140ms ease;
        }

        .auth-cta:hover {
            transform: translateY(-1px);
            filter: brightness(1.06);
            box-shadow: 0 16px 32px -10px rgba(75, 122, 66, 0.95), inset 0 1px 0 rgba(255, 255, 255, 0.22);
        }

        /* Terkunci sampai captcha lolos. */
        .auth-cta:disabled,
        .auth-cta:disabled:hover {
            cursor: wait;
            opacity: 0.55;
            transform: none;
            filter: saturate(0.6);
            box-shadow: none;
        }

        /*
         * Bar verifikasi buatan sendiri (gaya Cloudflare Turnstile). Widget
         * checkbox asli Google adalah iframe lintas-domain yang tidak bisa
         * direstyle sama sekali, jadi dipakai reCAPTCHA mode INVISIBLE:
         * widget Google tidak tampil, checkbox di bawah ini yang memicu
         * grecaptcha.execute(), dan Google tetap memunculkan teka-teki
         * gambar sendiri kalau curiga bot. Semua warna dari token app.css,
         * jadi ikut tema terang/gelap.
         */
        .auth-verify {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            min-height: 64px;
            margin-top: 1rem;
            padding: 0.55rem 0.9rem 0.55rem 0.8rem;
            border: 1px solid var(--input-border);
            border-radius: 0.9rem;
            background: var(--input-bg);
            transition: border-color 160ms ease;
        }

        .auth-verify[data-state="ok"] {
            border-color: rgba(22, 163, 74, 0.45);
        }

        .auth-verify-check {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
            padding: 0.25rem;
            margin: -0.25rem;
            border: 0;
            border-radius: 0.6rem;
            font: inherit;
            text-align: left;
            color: var(--color-ink);
            background: none;
            cursor: pointer;
        }

        .auth-verify-check:disabled {
            cursor: default;
        }

        .auth-verify-check:focus-visible {
            outline: 2px solid var(--color-accent);
            outline-offset: 2px;
        }

        .auth-verify-box {
            position: relative;
            display: grid;
            flex-shrink: 0;
            place-items: center;
            width: 26px;
            height: 26px;
            border: 2px solid var(--color-muted);
            border-radius: 0.45rem;
            background: var(--color-surface);
            transition: border-color 160ms ease, background 160ms ease;
        }

        .auth-verify-check:not(:disabled):hover .auth-verify-box {
            border-color: var(--color-accent);
        }

        .auth-verify-tick {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: #ffffff;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 24;
            stroke-dashoffset: 24;
            transition: stroke-dashoffset 280ms ease 60ms;
        }

        .auth-verify-spin {
            position: absolute;
            inset: -2px;
            border: 2.5px solid var(--color-accent-line);
            border-top-color: var(--color-accent);
            border-radius: 999px;
            opacity: 0;
        }

        .auth-verify[data-state="loading"] .auth-verify-box {
            border-color: transparent;
            background: transparent;
        }

        .auth-verify[data-state="loading"] .auth-verify-spin {
            opacity: 1;
            animation: auth-verify-spin 0.8s linear infinite;
        }

        .auth-verify[data-state="ok"] .auth-verify-box {
            border-color: #16a34a;
            background: #16a34a;
        }

        .auth-verify[data-state="ok"] .auth-verify-tick {
            stroke-dashoffset: 0;
        }

        @keyframes auth-verify-spin {
            to { transform: rotate(360deg); }
        }

        .auth-verify-label {
            font-size: 13.5px;
            font-weight: 600;
            line-height: 1.3;
        }

        .auth-verify[data-state="ok"] .auth-verify-label {
            color: #16a34a;
        }

        .auth-verify-brand {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            flex-shrink: 0;
            gap: 0.15rem;
            text-align: right;
            color: var(--color-muted);
        }

        .auth-verify-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .auth-verify-logo svg {
            width: 14px;
            height: 14px;
        }

        .auth-verify-links {
            font-size: 10px;
        }

        .auth-verify-links a {
            color: inherit;
            text-decoration: none;
        }

        .auth-verify-links a:hover {
            text-decoration: underline;
        }

        /* Badge melayang Google disembunyikan -- diizinkan Google selama
           tulisan "reCAPTCHA" + tautan Privasi/Ketentuan tetap terlihat di
           alur pengguna, dan itu ada di sisi kanan bar di atas. */
        .grecaptcha-badge {
            visibility: hidden !important;
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-verify[data-state="loading"] .auth-verify-spin { animation: none; }
            .auth-verify-tick { transition: none; }
        }

        .auth-captcha-error {
            margin: 1rem 0 0;
            padding: 0.6rem 0.8rem;
            border-radius: 0.75rem;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.45;
            color: var(--color-warn);
            background: var(--color-warn-soft);
            box-shadow: inset 0 0 0 1px var(--color-warn-line);
        }

        .auth-captcha-error[hidden] { display: none; }

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

        /*
         * Overlay transisi keberangkatan: form ini POST lalu langsung
         * redirect 302 ke Keycloak, jadi tanpa ini layar terasa "meloncat"
         * dari kartu login ke domain lain dalam sepersekian detik --
         * mengagetkan buat pengguna awam. Tombol Masuk menahan submit
         * sebentar, menampilkan overlay ini, baru benar-benar mengirim form.
         * Gaya visualnya sengaja mirip intro-splash di layouts/sapto.blade
         * (logo + cincin berdenyut + aurora) supaya "pergi ke SSO" dan
         * "kembali dari SSO" terasa satu alur yang sama, bukan dua transisi
         * yang berbeda.
         */
        .leave-splash {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #0b100b;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 320ms ease, visibility 320ms ease;
        }

        .leave-splash.is-active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .leave-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
        }

        .leave-logo-wrap {
            position: relative;
            display: grid;
            place-items: center;
            width: 6.5rem;
            height: 6.5rem;
        }

        .leave-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1.5px solid rgba(143, 191, 126, 0.35);
            opacity: 0;
            animation: leave-ring-pulse 2.1s 0.15s ease-out infinite;
        }

        .leave-ring.leave-ring-2 {
            animation-delay: 0.65s;
        }

        @keyframes leave-ring-pulse {
            0% { transform: scale(0.55); opacity: 0; }
            15% { opacity: 0.85; }
            100% { transform: scale(1.35); opacity: 0; }
        }

        .leave-logo {
            position: relative;
            z-index: 1;
            width: 4.2rem;
            height: 4.2rem;
            object-fit: contain;
            filter: drop-shadow(0 8px 26px rgba(0, 0, 0, 0.45));
        }

        .leave-text {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: rgba(243, 250, 248, 0.85);
        }

        /* Bar geser (bukan mengisi 0->100%): durasi transisi ke Keycloak
           tidak diketahui, jadi indikatornya harus tetap masuk akal walau
           overlay ini bertahan lebih lama dari perkiraan (jaringan lambat). */
        .leave-bar {
            position: relative;
            width: 8rem;
            height: 2px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.14);
        }

        .leave-bar span {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 40%;
            border-radius: 999px;
            background: linear-gradient(90deg, #4b7a42, #b7dba9);
            animation: leave-bar-slide 1.1s ease-in-out infinite;
        }

        @keyframes leave-bar-slide {
            0% { left: -40%; }
            100% { left: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .leave-splash { transition: opacity 120ms ease; }
            .leave-ring { animation: none !important; opacity: 0; }
            .leave-bar span { animation-duration: 1.6s; }
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
                {{-- Lihat komentar .batik-medallion di <style> di atas. --}}
                <svg class="batik-medallion" width="280" height="280" viewBox="0 0 280 280" aria-hidden="true">
                    <defs>
                        <radialGradient id="batikFade" cx="35%" cy="35%" r="70%">
                            <stop offset="45%" stop-color="#fff" stop-opacity="1"/>
                            <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
                        </radialGradient>
                        <mask id="batikMask">
                            <rect width="280" height="280" fill="url(#batikFade)"/>
                        </mask>
                    </defs>
                    <g mask="url(#batikMask)" fill="none" stroke="#8fbf7e" stroke-width="1.1">
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
                            <g stroke-width="1">
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(22.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(67.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(112.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(157.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(202.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(247.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(292.5)"/>
                                <path d="M0 -58 C 9 -48, 9 -34, 0 -27 C -9 -34, -9 -48, 0 -58 Z" transform="rotate(337.5)"/>
                            </g>
                            <circle r="16"/>
                            <circle r="8" fill="#8fbf7e" stroke="none"/>
                        </g>
                    </g>
                </svg>

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

                {{-- POST, bukan link: token captcha ikut terkirim dan
                     diverifikasi server sebelum dialihkan ke Keycloak. --}}
                <form method="POST" action="{{ route('sso.login') }}" id="form-masuk">
                    @csrf
                    @if ($captchaGagal ?? null)
                        <p class="auth-captcha-error" role="alert">{{ $captchaGagal }}</p>
                    @endif
                    <button type="submit" id="login-submit" class="auth-cta" @if ($recaptchaSiteKey ?? null) disabled @endif>
                        <span id="login-submit-label">Masuk</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h13m-5-6 6 6-6 6"/>
                        </svg>
                    </button>
                    @if ($recaptchaSiteKey ?? null)
                        {{-- Di BAWAH tombol (permintaan eksplisit pengguna).
                             Tampilan bar ini milik SAPTO sendiri; widget Google
                             berjalan dalam mode invisible di #captcha-masuk dan
                             menaruh token di textarea tersembunyi
                             `g-recaptcha-response` di dalam form ini. --}}
                        <div class="auth-verify" id="captcha-bar" data-state="idle">
                            <button type="button" class="auth-verify-check" id="captcha-check" disabled aria-describedby="captcha-error">
                                <span class="auth-verify-box" aria-hidden="true">
                                    <svg class="auth-verify-tick" viewBox="0 0 24 24"><path d="M5 12.5l4.2 4.2L19 7.5"/></svg>
                                    <span class="auth-verify-spin"></span>
                                </span>
                                <span class="auth-verify-label" id="captcha-label">Saya bukan robot</span>
                            </button>
                            <div class="auth-verify-brand">
                                <span class="auth-verify-logo">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-3.6 7-9V6l-7-3-7 3v6c0 5.4 7 9 7 9Z"/><path d="m9 12 2 2 4-4"/></svg>
                                    reCAPTCHA
                                </span>
                                <span class="auth-verify-links">
                                    <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privasi</a>
                                    &middot;
                                    <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Ketentuan</a>
                                </span>
                            </div>
                        </div>
                        {{-- Wadah widget invisible: di LUAR bar (kalau di dalam,
                             ia jadi item flex ketiga dan space-between menaruh
                             blok reCAPTCHA di tengah), tapi tetap di dalam form
                             supaya textarea token ikut terkirim. --}}
                        <div id="captcha-masuk" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                        <p class="auth-captcha-error" id="captcha-error" role="alert" hidden>Verifikasi keamanan gagal dimuat. Muat ulang halaman ini.</p>
                        <script>
                            // Didefinisikan SEBELUM api.js dimuat (tag di bawah),
                            // supaya callback onload pasti sudah ada.
                            (function () {
                                var bar = document.getElementById('captcha-bar');
                                var cek = document.getElementById('captcha-check');
                                var label = document.getElementById('captcha-label');
                                var masuk = document.getElementById('login-submit');
                                var pesan = document.getElementById('captcha-error');
                                var LABEL = { idle: 'Saya bukan robot', loading: 'Memeriksa…', ok: 'Berhasil' };
                                var widget = null;
                                var pantau = null;

                                function status(s) {
                                    bar.setAttribute('data-state', s);
                                    label.textContent = LABEL[s] || LABEL.idle;
                                    masuk.disabled = s !== 'ok';
                                    cek.disabled = s === 'ok' || widget === null;
                                }

                                function galat(teks) {
                                    pesan.textContent = teks;
                                    pesan.hidden = false;
                                    status('idle');
                                }

                                // Mode invisible tidak punya callback "teka-teki
                                // ditutup tanpa diselesaikan". Tanpa pantauan ini
                                // spinner berputar selamanya kalau pengguna
                                // menutup popup gambar: iframe tantangan Google
                                // yang sudah sempat tampil lalu tersembunyi lagi
                                // = batal, kembalikan ke keadaan awal.
                                function mulaiPantau() {
                                    var sempatTampil = false;
                                    clearInterval(pantau);
                                    pantau = setInterval(function () {
                                        if (bar.getAttribute('data-state') !== 'loading') { clearInterval(pantau); return; }
                                        var f = document.querySelector('iframe[src*="recaptcha/api2/bframe"]');
                                        var wadah = f && f.parentNode && f.parentNode.parentNode;
                                        if (!wadah) return;
                                        var terlihat = getComputedStyle(wadah).visibility === 'visible';
                                        if (terlihat) {
                                            sempatTampil = true;
                                        } else if (sempatTampil) {
                                            clearInterval(pantau);
                                            // Google menyembunyikan popup BERSAMAAN dengan
                                            // mengirim token saat teka-teki terjawab --
                                            // tunggu sebentar dan cek token dulu, jangan
                                            // sampai jawaban yang benar ikut direset.
                                            setTimeout(function () {
                                                if (bar.getAttribute('data-state') !== 'loading') return;
                                                if (grecaptcha.getResponse(widget)) { pesan.hidden = true; status('ok'); return; }
                                                grecaptcha.reset(widget);
                                                status('idle');
                                            }, 1500);
                                        }
                                    }, 400);
                                }

                                window.saptoRecaptchaSiap = function () {
                                    widget = grecaptcha.render('captcha-masuk', {
                                        sitekey: document.getElementById('captcha-masuk').dataset.sitekey,
                                        size: 'invisible',
                                        badge: 'bottomright',
                                        callback: function () { pesan.hidden = true; status('ok'); },
                                        'expired-callback': function () { status('idle'); },
                                        'error-callback': function () { galat('Verifikasi keamanan gagal dimuat. Muat ulang halaman ini.'); },
                                    });
                                    status('idle');
                                };

                                cek.addEventListener('click', function () {
                                    if (widget === null || bar.getAttribute('data-state') !== 'idle') return;
                                    pesan.hidden = true;
                                    status('loading');
                                    grecaptcha.execute(widget);
                                    mulaiPantau();
                                });

                                // Skrip Google tidak termuat sama sekali (jaringan
                                // memblokir): beri tahu, jangan biarkan tombol mati diam-diam.
                                setTimeout(function () { if (widget === null) galat('Verifikasi keamanan gagal dimuat. Muat ulang halaman ini.'); }, 10000);
                            })();
                        </script>
                        <script src="https://www.google.com/recaptcha/api.js?onload=saptoRecaptchaSiap&render=explicit&hl=id" async defer></script>
                    @endif
                </form>

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

    {{-- Lihat komentar .leave-splash di <style> di atas. --}}
    <div class="leave-splash" id="leave-splash" aria-hidden="true">
        <div class="leave-inner">
            <div class="leave-logo-wrap">
                <span class="leave-ring"></span>
                <span class="leave-ring leave-ring-2"></span>
                <img src="{{ asset('images/logo-unsri.webp') }}" alt="" class="leave-logo">
            </div>
            <p class="leave-text">Mengalihkan ke SSO Unsri…</p>
            <div class="leave-bar"><span></span></div>
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
            var formMasuk = document.getElementById('form-masuk');
            var leaveSplash = document.getElementById('leave-splash');

            if (formMasuk && loginLabel) {
                // form.submit() (bukan requestSubmit()) tidak memicu ulang
                // event 'submit' -- flag ini cuma jaga-jaga kalau pengguna
                // memicu submit dua kali (mis. tekan Enter) selagi overlay
                // sedang tampil.
                var sudahKirim = false;
                var kurangiGerak = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                formMasuk.addEventListener('submit', function (e) {
                    if (sudahKirim) return;

                    loginLabel.textContent = 'Mengalihkan ke SSO…';
                    if (loginBtn) loginBtn.disabled = true;

                    if (!leaveSplash || kurangiGerak) return; // biarkan submit asli jalan langsung

                    e.preventDefault();
                    sudahKirim = true;
                    leaveSplash.classList.add('is-active');
                    // Jeda sengaja: cukup lama supaya animasinya kebaca,
                    // cukup singkat supaya tidak terasa menunda-nunda.
                    setTimeout(function () { formMasuk.submit(); }, 550);
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
