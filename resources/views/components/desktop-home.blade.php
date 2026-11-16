{{-- resources/views/components/desktop-home.blade.php --}}
{{--
    Beranda desktop (>= sm): ringkasan kelengkapan data akreditasi + capaian
    utama untuk lingkup akun. Versi HP-nya ada di mobile-pages.blade.php
    (#beranda-page); keduanya "halaman virtual" beranda yang sama dan berbagi
    data (lihat bagian "Shell mobile & Beranda" di sapto.blade.php).

    Markup di sini hanya kerangka + skeleton; isinya dirender renderHome():
      - /api/sapto/kesiapan         -> kelengkapan, kriteria, perlu tindakan
      - /api/sapto/dashboard/summary -> angka utama + tren tiga tahun
      - khusus admin: /admin/sync-pd-unsri/status dan /api/sapto/pd-unsri/status
--}}
@php
    $hUser = session('sso_user');
    $hIsAdmin = (bool) ($hUser['is_admin'] ?? false);
    $hName = \App\Support\PersonName::tidy((string) ($hUser['name'] ?? ($hUser['preferred_username'] ?? 'Pengguna')));
    $skel = fn (string $w, string $extra = '') => '<span class="home-skel" style="width:'.$w.';'.$extra.'"></span>';
@endphp

<section id="home-page" class="home hidden" aria-labelledby="home-title" data-admin="{{ $hIsAdmin ? '1' : '0' }}">

    <header class="home-head">
        <div class="home-head-text">
            <p class="home-eyebrow" id="home-scope">&nbsp;</p>
            <h2 class="home-title" id="home-title"><span id="home-greet">Selamat datang</span>, {{ $hName }}</h2>
            <p class="home-lead">Ringkasan kelengkapan data akreditasi dan capaian utama untuk lingkup Anda.</p>
        </div>
        <div class="home-head-meta">
            <span class="home-sync" id="home-sync"></span>
            <button type="button" class="home-btn" id="home-refresh">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36" /><path d="M21 3v6h-6" /></svg>
                Muat ulang
            </button>
        </div>
    </header>

    <p class="home-alert" id="home-alert" role="alert" hidden></p>

    {{-- ======= Angka utama ======= --}}
    <div class="home-kpis" aria-label="Angka utama">
        <article class="home-card home-hero-card" id="home-hero" aria-labelledby="home-hero-label">
            <div class="home-label" id="home-hero-label">Kelengkapan data akreditasi</div>
            <div class="home-hero-row">
                <div class="home-hero-fig" id="home-hero-fig">{!! $skel('4.5rem', 'height:3.4rem') !!}</div>
                <div class="home-hero-txt">
                    <div class="home-hero-title" id="home-hero-title">{!! $skel('11rem') !!}</div>
                    <div class="home-muted" id="home-hero-sub">{!! $skel('8rem') !!}</div>
                </div>
            </div>
            <div class="home-meter" id="home-meter" role="img" aria-label="Kelengkapan data"></div>
            <ul class="home-legend" id="home-legend"></ul>
            <p class="home-note" id="home-hero-note" hidden></p>
        </article>

        @foreach ([
            'dosen-tetap-pt' => 'Dosen tetap',
            'ipk-lulusan' => 'Lulusan tahun berjalan',
            'dosen-pembimbing-ta' => 'Dosen pembimbing tugas akhir',
        ] as $cardId => $label)
            <article class="home-card home-stat" data-stat="{{ $cardId }}" aria-labelledby="home-stat-{{ $cardId }}">
                <div class="home-label" id="home-stat-{{ $cardId }}">{{ $label }}</div>
                <div class="home-stat-val" data-role="value">{!! $skel('5rem', 'height:2rem') !!}</div>
                <div class="home-muted home-stat-sub" data-role="sub">{!! $skel('9rem') !!}</div>
                <button type="button" class="home-link" data-role="open" data-table-id="{{ $cardId }}">Buka tabel <span aria-hidden="true">→</span></button>
            </article>
        @endforeach
    </div>

    {{-- ======= Kriteria + perlu tindakan ======= --}}
    <div class="home-cols">
        <section class="home-card" aria-labelledby="home-crit-title">
            <div class="home-card-head">
                <div>
                    <h3 class="home-h3" id="home-crit-title">Kelengkapan per kriteria</h3>
                    <p class="home-muted">Tabel yang sudah terisi dari seluruh tabel di tiap kriteria.</p>
                </div>
            </div>
            <ul class="home-list" id="home-criteria">
                @for ($i = 0; $i < 4; $i++)
                    <li class="home-row is-skel">{!! $skel('60%') !!}{!! $skel('100%', 'height:6px;margin-top:10px') !!}</li>
                @endfor
            </ul>
        </section>

        <section class="home-card" aria-labelledby="home-attn-title">
            <div class="home-card-head">
                <div>
                    <h3 class="home-h3" id="home-attn-title">Perlu tindakan</h3>
                    <p class="home-muted" id="home-attn-sub">Tabel yang belum berisi data.</p>
                </div>
            </div>
            <ul class="home-list" id="home-attn">
                @for ($i = 0; $i < 4; $i++)
                    <li class="home-row is-skel">{!! $skel('70%') !!}{!! $skel('45%', 'margin-top:8px') !!}</li>
                @endfor
            </ul>
            <button type="button" class="home-more" id="home-attn-more" hidden></button>
        </section>
    </div>

    {{-- ======= Tren ======= --}}
    <section aria-labelledby="home-trend-title" class="home-section">
        <div class="home-section-head">
            <h3 class="home-h3" id="home-trend-title">Tren tiga tahun akademik</h3>
            <p class="home-muted">Tahun berjalan (TS) diberi warna penuh, dua tahun sebelumnya lebih pucat.</p>
        </div>
        <div class="home-charts" id="home-charts">
            @for ($i = 0; $i < 4; $i++)
                <article class="home-card home-chart is-skel">{!! $skel('55%') !!}{!! $skel('100%', 'height:120px;margin-top:14px') !!}</article>
            @endfor
        </div>
    </section>

    {{-- ======= Terakhir dibuka + (admin) kesehatan sistem / (lainnya) tentang data ======= --}}
    <div class="home-cols home-cols-even">
        <section class="home-card" aria-labelledby="home-recent-title">
            <div class="home-card-head">
                <div>
                    <h3 class="home-h3" id="home-recent-title">Terakhir dibuka</h3>
                    <p class="home-muted">Lanjutkan dari tabel yang baru Anda lihat.</p>
                </div>
            </div>
            <ul class="home-list" id="home-recent"></ul>
        </section>

        @if ($hIsAdmin)
            <section class="home-card" aria-labelledby="home-sys-title">
                <div class="home-card-head">
                    <div>
                        <h3 class="home-h3" id="home-sys-title">Kesehatan sistem</h3>
                        <p class="home-muted">Khusus admin. Status jalur data dan layanan pendukung.</p>
                    </div>
                </div>
                <ul class="home-list" id="home-system">
                    @for ($i = 0; $i < 4; $i++)
                        <li class="home-row is-skel">{!! $skel('55%') !!}{!! $skel('35%', 'margin-top:8px') !!}</li>
                    @endfor
                </ul>
            </section>
        @else
            <section class="home-card" aria-labelledby="home-about-title">
                <div class="home-card-head">
                    <div>
                        <h3 class="home-h3" id="home-about-title">Tentang data ini</h3>
                    </div>
                </div>
                <p class="home-about" id="home-about">
                    Data bersumber dari PD UNSRI dan disinkronkan otomatis setiap Minggu dini hari (pukul 02.00 WIB).
                    Kolom yang belum tersedia di sumber ditandai "kolom sebagian" pada tabel yang bersangkutan.
                </p>
            </section>
        @endif
    </div>

    <div id="home-tip" class="home-tip" role="tooltip" hidden></div>
</section>
