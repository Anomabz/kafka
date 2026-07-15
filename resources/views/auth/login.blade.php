<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    <title>Masuk — SAPTO</title>
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
    <style>
        /*
         * Scoped to the login page only. Layout/motion here, but every colour
         * and surface is pulled from app.css's existing design tokens so this
         * page re-skins with the rest of the app on the light/dark toggle.
         */
        .auth-shell {
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 1.5rem;
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
            max-width: 62rem;
            margin: 0 auto 1.25rem;
        }

        @media (min-width: 1024px) {
            .auth-mobile-brand {
                display: none;
            }
        }

        /*
         * Mobile gets its own layout, not just the desktop one squeezed into
         * one column: only the form shows (that's the actual task) — the
         * branded panel is desktop-only, restored at the 1024px breakpoint.
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
                radial-gradient(30rem 26rem at -12% 112%, rgba(110, 231, 212, 0.18), transparent 60%),
                linear-gradient(155deg, #081018 0%, #0b241f 32%, #0a6b5e 78%, #0f9d8b 100%);
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
            background-color: #6ee7d4;
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
            color: #6ee7d4;
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
            padding: 3rem;
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
            color: #6ee7d4;
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
            font-size: 19px;
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

        .auth-input-wrap {
            position: relative;
        }

        .auth-input-wrap .field-icon {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-muted);
            pointer-events: none;
        }

        .auth-input-wrap input.filter-select {
            padding-left: 2.5rem;
        }

        .auth-input-wrap input[type='password'].filter-select,
        .auth-input-wrap input.has-toggle {
            padding-right: 2.5rem;
        }

        .field-toggle {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            display: grid;
            place-items: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.6rem;
            color: var(--color-muted);
            cursor: pointer;
            background: transparent;
            border: none;
            transition: color 120ms ease, background 120ms ease;
        }

        .field-toggle:hover {
            color: var(--color-accent);
            background: var(--color-accent-soft);
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

        .auth-modal-countdown {
            margin-top: 0.9rem;
            font-size: 12px;
            color: var(--color-muted);
        }

        .auth-modal-countdown strong {
            color: var(--color-accent);
            font-variant-numeric: tabular-nums;
        }

        .auth-modal .auth-submit {
            margin-top: 1.1rem;
            text-decoration: none;
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
                    src="{{ asset('images/logo-unsri.png') }}"
                    alt="Lambang Universitas Sriwijaya"
                    class="h-full w-full object-contain"
                />
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="auth-wordmark font-extrabold tracking-tight text-ink">SAPTO</span>
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                </div>
                <p class="text-[12px] font-medium text-muted">Universitas Sriwijaya</p>
            </div>
        </div>

        <div class="auth-grid">
            <div class="auth-context rounded-2xl">
                <div class="auth-context-inner">
                    <div class="flex items-center gap-3">
                        <div class="auth-logo grid shrink-0 place-items-center">
                            <img
                                src="{{ asset('images/logo-unsri.png') }}"
                                alt="Lambang Universitas Sriwijaya"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="auth-wordmark font-extrabold tracking-tight text-ink">SAPTO</span>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                            </div>
                            <p class="text-[12px] font-medium text-muted">Universitas Sriwijaya</p>
                        </div>
                    </div>

                    <h2 class="auth-context-headline">Satu portal untuk seluruh pelaporan Tridharma UNSRI</h2>

                    <p class="auth-mt-5 auth-tagline text-[13px] leading-relaxed text-muted">
                        Data pendidikan, penelitian, dan pengabdian masyarakat dari tiap fakultas dan program studi — terkumpul rapi dalam satu sistem.
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
                <h1 class="auth-heading font-extrabold tracking-tight text-ink">Masuk ke SAPTO</h1>
                <p class="mt-1 text-[13px] text-muted">Gunakan akun SSO Universitas Sriwijaya Anda.</p>

                <form method="POST" action="{{ route('login.attempt') }}" class="auth-mt-5 space-y-4">
                    @csrf
                    <div class="auth-field">
                        <label for="username" class="mb-1 block text-[12px] font-semibold text-muted">Username</label>
                        <div class="auth-input-wrap">
                            <svg class="field-icon icon-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
                                <circle cx="10" cy="7" r="4"/>
                            </svg>
                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="filter-select"
                            />
                        </div>
                        @error('username')
                            <p class="mt-1 text-[12px] text-warn">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="password" class="mb-1 block text-[12px] font-semibold text-muted">Kata Sandi</label>
                        <div class="auth-input-wrap">
                            <svg class="field-icon icon-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="10.5" width="16" height="9.5" rx="2"/>
                                <path d="M7.5 10.5V7a4.5 4.5 0 0 1 9 0v3.5"/>
                            </svg>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autocomplete="current-password"
                                class="filter-select has-toggle"
                            />
                            <button type="button" id="password-toggle" class="field-toggle" aria-label="Tampilkan kata sandi" aria-pressed="false">
                                <svg id="password-toggle-icon" class="icon-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-[12px] text-warn">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        id="login-submit"
                        class="auth-submit inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent text-[13px] font-bold text-white"
                    >
                        Masuk
                    </button>
                </form>

                <p class="auth-mt-4 text-center text-[12px] text-muted">SAPTO Universitas Sriwijaya</p>
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
                <h2 id="not-korprodi-title" class="auth-modal-title">Akses SAPTO ditolak</h2>
                <p id="not-korprodi-desc" class="auth-modal-desc">
                    Akun {{ $notKorprodiHint }} valid di SSO, tapi belum terdaftar sebagai koordinator program studi (korprodi) di SAPTO. Hubungi admin SAPTO jika ini keliru.
                </p>
            </div>
        </div>
    @endif

    @if ($unregisteredHint)
        @php($ssoRedirectUrl = route('sso.login', ['login_hint' => $unregisteredHint]))
        <div class="auth-modal-backdrop" id="unregistered-backdrop">
            <div
                class="auth-modal glass-strong rounded-2xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="unregistered-title"
                aria-describedby="unregistered-desc"
                tabindex="-1"
            >
                <button type="button" id="unregistered-close" class="theme-toggle auth-modal-close" aria-label="Tutup">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="auth-modal-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                    </svg>
                </div>
                <h2 id="unregistered-title" class="auth-modal-title">Akun tidak terdaftar</h2>
                <p id="unregistered-desc" class="auth-modal-desc">Akun Anda tidak terdaftar, silakan register.</p>
                <p class="auth-modal-countdown">
                    Mengalihkan ke portal SSO dalam <strong id="unregistered-countdown">5</strong> detik…
                </p>
                <a
                    href="{{ $ssoRedirectUrl }}"
                    id="unregistered-register-btn"
                    class="auth-submit inline-flex w-full items-center justify-center gap-2 rounded-xl bg-accent text-[13px] font-bold text-white"
                >
                    Register Sekarang
                </a>
            </div>
        </div>
    @endif

    <script>
        (function () {
            var pwToggle = document.getElementById('password-toggle');
            var pwInput = document.getElementById('password');
            var pwIcon = document.getElementById('password-toggle-icon');
            if (pwToggle && pwInput) {
                pwToggle.addEventListener('click', function () {
                    var showing = pwInput.type === 'text';
                    pwInput.type = showing ? 'password' : 'text';
                    pwToggle.setAttribute('aria-pressed', String(!showing));
                    pwToggle.setAttribute('aria-label', showing ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
                    if (pwIcon) {
                        pwIcon.innerHTML = showing
                            ? '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/>'
                            : '<path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M9.9 5.1A10.6 10.6 0 0 1 12 5c7 0 10.5 7 10.5 7a13.4 13.4 0 0 1-3.1 3.9M6.3 6.3C3.4 8.2 1.5 12 1.5 12s1.6 3.2 4.6 5.2A10.4 10.4 0 0 0 12 19"/>';
                    }
                });
            }

            var form = document.querySelector('form[action="{{ route('login.attempt') }}"]');
            var submitBtn = document.getElementById('login-submit');
            if (form && submitBtn) {
                form.addEventListener('submit', function () {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Memproses…';
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

            var registerBtn = document.getElementById('unregistered-register-btn');
            var countdownEl = document.getElementById('unregistered-countdown');
            var backdrop = document.getElementById('unregistered-backdrop');
            var closeBtn = document.getElementById('unregistered-close');
            if (registerBtn && countdownEl && backdrop) {
                var modal = document.querySelector('.auth-modal');
                if (modal) modal.focus();

                var seconds = 5;
                var timer = setInterval(function () {
                    seconds -= 1;
                    countdownEl.textContent = String(seconds);
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.href = registerBtn.href;
                    }
                }, 1000);

                var closeModal = function () {
                    clearInterval(timer);
                    backdrop.remove();
                };

                if (closeBtn) closeBtn.addEventListener('click', closeModal);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeModal();
                });
            }
        })();
    </script>
</body>
</html>
