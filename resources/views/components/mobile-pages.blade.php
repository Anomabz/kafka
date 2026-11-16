{{-- resources/views/components/mobile-pages.blade.php --}}
{{--
    Halaman khusus layar < sm:
      #beranda-page       ringkasan kesiapan data + tindak lanjut + kriteria
      #tabel-picker-page  daftar semua tabel LKPS per kriteria (tab "Tabel")
      #akun-page          profil, tampilan, admin, keluar
    Semuanya display:none di >= sm (app.css), jadi desktop tidak berubah.
    Isi dinamisnya dirender di sapto.blade.php (bagian "Shell mobile").
--}}
@php
    $mUser = session('sso_user');
    $mIsAdmin = (bool) ($mUser['is_admin'] ?? false);
    $mName = \App\Support\PersonName::tidy((string) ($mUser['name'] ?? ($mUser['preferred_username'] ?? 'Pengguna')));
    // Peran & cakupan dari SaptoUserContext -- sumber yang sama dengan yang
    // membatasi data (dan dipakai sidebar), bukan klaim SSO. Akun tanpa
    // penugasan tidak boleh membuat halaman gagal dirender.
    $mAkses = null;
    if ($mUser && ! $mIsAdmin) {
        try {
            $mAkses = \App\Support\SaptoUserContext::fromRequest(request())->keterangan();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            $mAkses = null;
        }
    }
    $mRoleKey = match (true) {
        $mIsAdmin => 'admin',
        ($mAkses['peran'] ?? null) === 'Rektor' => 'rektor',
        ($mAkses['peran'] ?? null) === 'Dekan' => 'dekan',
        ($mAkses['peran'] ?? null) === 'Koordinator Program Studi' => 'korprodi',
        default => null,
    };
    $mLingkup = $mIsAdmin ? 'Pengelola sistem SAPTO' : ($mAkses['lingkup'] ?? 'Belum punya akses SAPTO');
    $mWords = preg_split('/\s+/u', preg_replace('/[^\p{L}\s]/u', ' ', $mName), -1, PREG_SPLIT_NO_EMPTY);
    $mInitials = mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($mWords, 0, 2)))) ?: 'P';
    $chevron = '<svg class="m-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>';
@endphp

{{-- ================= BERANDA ================= --}}
<section id="beranda-page" class="m-page hidden" aria-labelledby="m-greet-name">
    <header class="m-hero">
        <div class="m-hero-pattern" aria-hidden="true">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="m-kisi" width="28" height="28" patternUnits="userSpaceOnUse">
                        <path d="M14 1 L27 14 L14 27 L1 14 Z" fill="none" stroke="#ffffff" stroke-opacity="0.07" stroke-width="1" />
                        <circle cx="14" cy="14" r="1.8" fill="#ffffff" fill-opacity="0.10" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#m-kisi)" />
            </svg>
        </div>
        <div class="m-hero-top">
            <div class="m-brand">
                <img src="{{ asset('images/logo-unsri.webp') }}" alt="" class="m-brand-logo">
                <span class="m-brand-text">SAPTO</span>
            </div>
            <div id="m-api-slot" class="m-hero-slot"></div>
        </div>
        <p id="m-greet" class="m-hero-greet">Selamat datang</p>
        <h1 id="m-greet-name" class="m-hero-name">{{ $mName }}</h1>
        <p class="m-hero-meta"><span id="m-unit">{{ $mLingkup }}</span></p>
    </header>

    <div class="m-stack">
        <section class="m-card m-ready" aria-label="Kelengkapan data akreditasi">
            <div class="m-ready-main">
                <div class="m-ring-wrap">
                <svg class="m-ring" viewBox="0 0 100 100" aria-hidden="true">
                    <circle cx="50" cy="50" r="42" fill="none" class="m-ring-track" stroke-width="10" />
                    <circle id="m-ring-arc" cx="50" cy="50" r="42" fill="none" class="m-ring-arc" stroke-width="10" stroke-linecap="round" stroke-dasharray="0 264" transform="rotate(-90 50 50)" />
                </svg>
                <div class="m-ring-value"><span id="m-ring-pct">–</span></div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="m-overline">Kelengkapan data akreditasi</div>
                    <div id="m-ready-title" class="m-ready-title">Menghitung…</div>
                    <div id="m-ready-sync" class="m-ready-sync"></div>
                </div>
            </div>
            <dl id="m-ready-stats" class="m-stats"></dl>
        </section>

        <section aria-labelledby="m-attn-title">
            <div class="m-section-head">
                <h2 id="m-attn-title" class="m-section-title">Belum terisi</h2>
                <button type="button" class="m-text-btn" data-go="tabel-picker" data-picker-filter="kosong">Lihat semua</button>
            </div>
            <div id="m-attn" class="m-list"></div>
        </section>

        <section aria-labelledby="m-criteria-title">
            <div class="m-section-head">
                <h2 id="m-criteria-title" class="m-section-title">Tabel per kriteria</h2>
            </div>
            <div id="m-criteria" class="m-list"></div>
        </section>

        <section id="m-recent-wrap" aria-labelledby="m-recent-title" hidden>
            <div class="m-section-head">
                <h2 id="m-recent-title" class="m-section-title">Terakhir dibuka</h2>
            </div>
            <div id="m-recent" class="m-list"></div>
        </section>
    </div>
</section>

{{-- ================= DAFTAR TABEL ================= --}}
<section id="tabel-picker-page" class="m-page hidden" aria-labelledby="m-picker-title">
    <header class="m-pagehead">
        <h1 id="m-picker-title" class="m-pagehead-title">Tabel LKPS</h1>
        <p class="m-pagehead-sub">Pilih tabel yang ingin dilihat.</p>
    </header>
    <div class="m-stack">
        <div class="m-search-field">
            <svg class="m-search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
            <input id="m-picker-search" type="search" class="m-input" placeholder="Cari nama atau nomor tabel" aria-label="Cari tabel" autocomplete="off">
        </div>
        <div class="m-segmented" role="group" aria-label="Saring tabel">
            <button type="button" class="m-segment" data-picker-filter="semua" aria-pressed="true">Semua tabel <span id="m-count-semua" class="m-segment-count"></span></button>
            <button type="button" class="m-segment" data-picker-filter="kosong" aria-pressed="false">Belum terisi <span id="m-count-kosong" class="m-segment-count"></span></button>
        </div>
        <div id="m-picker-list"></div>
    </div>
</section>

{{-- ================= AKUN ================= --}}
<section id="akun-page" class="m-page hidden" aria-labelledby="m-akun-title">
    <header class="m-pagehead">
        <h1 id="m-akun-title" class="m-pagehead-title">Akun</h1>
    </header>
    <div class="m-stack">
        <div class="m-card m-profile">
            <span class="m-avatar" aria-hidden="true">
                @if ($mIsAdmin)
                    <img src="{{ asset('images/logo-unsri.webp') }}" alt="" class="h-full w-full object-contain p-1.5">
                @else
                    {{ $mInitials }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="m-profile-name">{{ $mName }}</div>
                <div class="m-profile-scope">{{ $mLingkup }}</div>
            </div>
            @if ($mRoleKey)
                <x-role-badge :role="$mRoleKey" />
            @endif
        </div>

        <section>
            <h2 class="m-section-title m-section-title-list">Pengaturan</h2>
            <div class="m-list">
                <button type="button" id="m-account-theme" class="m-list-row">
                    <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg></span>
                    <span class="m-row-main"><span class="m-row-title">Mode tampilan</span></span>
                    <span id="m-account-theme-label" class="m-row-value"></span>
                </button>
                <button type="button" id="m-account-profile" class="m-list-row">
                    <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="11" r="2.5" /><path d="M14 10h4M14 14h4M5.5 17a3.5 3.5 0 0 1 7 0" /></svg></span>
                    <span class="m-row-main"><span class="m-row-title">Data profil SSO</span></span>
                    {!! $chevron !!}
                </button>
            </div>
        </section>

        @if ($mIsAdmin)
            <section>
                <h2 class="m-section-title m-section-title-list">Administrasi</h2>
                <div class="m-list">
                    <a href="{{ route('admin.peran') }}" class="m-list-row">
                        <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-3.6 7-9V6l-7-3-7 3v6c0 5.4 7 9 7 9Z" /><path d="m9 12 2 2 4-4" /></svg></span>
                        <span class="m-row-main"><span class="m-row-title">Kelola peran</span></span>
                        {!! $chevron !!}
                    </a>
                    <a href="{{ route('admin.sync-pd-unsri') }}" class="m-list-row">
                        <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 1-15.5 6.2M3 12a9 9 0 0 1 15.5-6.2" /><path d="M21 4v5h-5M3 20v-5h5" /></svg></span>
                        <span class="m-row-main"><span class="m-row-title">Sinkronisasi PD UNSRI</span></span>
                        {!! $chevron !!}
                    </a>
                    <a href="{{ route('admin.kafka') }}" class="m-list-row">
                        <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h16" /><circle cx="18" cy="12" r="2" /></svg></span>
                        <span class="m-row-main"><span class="m-row-title">Event Stream (Kafka)</span></span>
                        {!! $chevron !!}
                    </a>
                </div>
            </section>
        @endif

        <div class="m-list">
            <a href="{{ route('sso.logout') }}" class="m-list-row is-danger">
                <span class="m-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg></span>
                <span class="m-row-main"><span class="m-row-title">Keluar</span></span>
            </a>
        </div>

        <p class="m-legal">SAPTO &middot; Universitas Sriwijaya</p>
    </div>
</section>

