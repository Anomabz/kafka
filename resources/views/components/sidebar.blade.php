{{--
    resources/views/components/sidebar.blade.php

    45 tables ship in a flat array, so they're grouped here by the leading
    segment of $table['code'] (e.g. "3.a.2" -> group "3") into labeled
    sections — a flat unlabeled list at this length isn't scannable.
    Search filtering (see app.js/#menu-search wiring in sapto.blade.php)
    covers the rest. Colours come from the --side-* theme tokens (app.css).
--}}
@php
    $groups = [];

    foreach ($tables as $table) {
        $groupKey = explode('.', $table['code'])[0] ?? $table['code'];
        $groups[$groupKey][] = $table;
    }

    // Ikon per kategori tabel (sidebar). Tiap kategori yang punya ikon di
    // sini otomatis jadi dropdown collapsible (lihat $dropdownGroups).
    $groupIcons = [
        // 1: Kerjasama Tridharma — rantai/link mewakili kerja sama institusi.
        '1' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 7h3a5 5 0 0 1 5 5 5 5 0 0 1-5 5h-3m-6 0H6a5 5 0 0 1-5-5 5 5 0 0 1 5-5h3"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        // 2: Seleksi Mahasiswa & Mahasiswa Asing — topi wisuda.
        '2' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>',
        // 3: Dosen (tetap/tidak tetap/DTPS/HKI/dst) — dua orang.
        '3' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        // 4: Penggunaan Dana — kartu/pembayaran.
        '4' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        // 5: Kurikulum & Pembelajaran — buku terbuka.
        '5' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        // 6: Penelitian yang melibatkan mahasiswa — labu takar riset.
        '6' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2v6.5L4 18a2 2 0 0 0 1.8 3h12.4a2 2 0 0 0 1.8-3l-5-9.5V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>',
        // 7: PkM yang melibatkan mahasiswa — hati (pengabdian).
        '7' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
        // 8: Capaian & profil lulusan — medali.
        '8' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/></svg>',
    ];

    // Nama deskriptif per kategori, dipakai berdampingan dengan nomor tabel
    // BAN-PT (nomor tetap ditampilkan karena itu yang dirujuk di dokumen
    // akreditasi, tapi "Tabel 3" saja tidak menjelaskan isinya).
    $groupLabels = [
        '1' => 'Kerjasama Tridharma',
        '2' => 'Seleksi & Mahasiswa Asing',
        '3' => 'Dosen',
        '4' => 'Penggunaan Dana',
        '5' => 'Kurikulum & Pembelajaran',
        '6' => 'Penelitian Melibatkan Mahasiswa',
        '7' => 'PkM Melibatkan Mahasiswa',
        '8' => 'Capaian & Profil Lulusan',
    ];

    // Kategori yang punya ikon otomatis jadi dropdown collapsible. Hanya
    // kategori berisi tabel yang sedang aktif yang terbuka secara default —
    // sisanya collapsed supaya sidebar tidak sesak dengan 45 tabel sekaligus.
    $dropdownGroups = array_keys($groupIcons);

    $ssoUser = session('sso_user');
    $userName = $ssoUser['name'] ?? ($ssoUser['preferred_username'] ?? 'Pengguna');

    // Avatar deterministik dari DiceBear — seed pakai identitas stabil
    // (nomor_identitas/nama) supaya tiap orang dapat wajah yang konsisten,
    // bukan cuma huruf inisial.
    $avatarSeed = $ssoUser['nomor_identitas'] ?? $userName;
    $avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?seed='.urlencode($avatarSeed);

    // Profil dirender langsung dari session `sso_user` (userinfo mentah dari
    // Keycloak) — tidak perlu endpoint baru, dan tidak ada risiko satu akun
    // mengintip data akun lain karena sumbernya sesi sendiri.
    //
    // Label dipetakan untuk field yang sudah dikenal; sisanya tetap
    // ditampilkan apa adanya di bagian "Field lain" supaya kalau Keycloak
    // menambah klaim baru, datanya tidak diam-diam hilang dari tampilan.
    $profileLabels = [
        'name' => 'Nama lengkap',
        'preferred_username' => 'Username SSO',
        'email' => 'Email',
        'nomor_identitas' => 'Nomor identitas',
        'jenis_identitas' => 'Jenis identitas',
        'jenis_pengguna' => 'Jenis pengguna',
        'unit_kerja' => 'Unit kerja',
        'tahun_masuk' => 'Tahun masuk',
        'nomor_hp' => 'Nomor HP',
        'given_name' => 'Nama depan',
        'family_name' => 'Nama belakang',
        'sub' => 'ID SSO',
    ];

    // Bukan bagian dari userinfo SSO: penanda internal SAPTO / turunan.
    $profileHidden = ['is_admin', 'email_verified'];

    $profileKnown = [];
    foreach ($profileLabels as $key => $label) {
        $value = $ssoUser[$key] ?? null;

        if ($value !== null && $value !== '') {
            $profileKnown[$label] = $value;
        }
    }

    $profileExtra = collect($ssoUser ?? [])
        ->except(array_merge(array_keys($profileLabels), $profileHidden))
        ->reject(fn ($value) => $value === null || $value === '' || is_array($value))
        ->all();
@endphp

<aside id="sidebar" class="sidebar side-glass flex flex-col">
    <div class="sidebar-header flex min-h-[73px] items-center gap-3 px-5" style="border-bottom:1px solid var(--side-border)">
        <div class="grid h-11 w-11 shrink-0 place-items-center">
            <img
                src="{{ asset('images/logo-unsri.webp') }}"
                alt="Lambang Universitas Sriwijaya"
                class="h-full w-full object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.35)]"
            />
        </div>
        <div class="sidebar-brand-text min-w-0 leading-tight">
            <div class="flex items-center gap-1.5">
                <span class="text-[16px] font-extrabold tracking-tight" style="color:var(--side-text)">SAPTO</span>
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
            </div>
            <div class="truncate text-[11px] font-medium" style="color:var(--side-dim)">Universitas Sriwijaya</div>
        </div>
        <button
            type="button"
            id="sidebar-close"
            aria-label="Tutup sidebar"
            class="ml-auto grid h-8 w-8 shrink-0 place-items-center rounded-lg transition hover:bg-accent/10 lg:hidden"
            style="color:var(--side-dim)"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <div class="sidebar-search px-3 py-3" style="border-bottom:1px solid var(--side-border)">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:var(--side-faint)" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <input
                id="menu-search"
                type="search"
                placeholder="Cari tabel..."
                aria-label="Cari tabel"
                class="peer w-full rounded-xl py-2 pl-8 pr-9 text-[13px] transition focus:outline-none focus:ring-2 focus:ring-accent/25"
                style="background:var(--input-bg);border:1px solid var(--input-border);color:var(--side-text);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)"
            >
            <kbd
                class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded px-1.5 py-0.5 text-[10px] font-medium peer-focus:hidden"
                style="border:1px solid var(--side-border);color:var(--side-faint)"
                aria-hidden="true"
            >/</kbd>
        </div>
    </div>

    <nav id="menu" class="menu flex-1 space-y-4 overflow-y-auto px-3 py-3" aria-label="Menu tabel SAPTO">
        @foreach ($groups as $groupKey => $items)
            @if (in_array($groupKey, $dropdownGroups, true))
                @php
                    $isActiveGroup = collect($items)->contains(fn ($table) => $table['id'] === $activeTableId);
                @endphp
                <div class="menu-group menu-group-dropdown {{ $isActiveGroup ? 'has-active' : 'is-collapsed' }}" data-menu-group>
                    <button
                        type="button"
                        class="menu-group-toggle"
                        data-group-toggle
                        aria-expanded="{{ $isActiveGroup ? 'true' : 'false' }}"
                        aria-controls="menu-group-panel-{{ $groupKey }}"
                        title="{{ $groupLabels[$groupKey] ?? 'Tabel '.$groupKey }}"
                    >
                        <span class="menu-group-icon">{!! $groupIcons[$groupKey] !!}</span>
                        <span class="menu-group-text">
                            <span class="menu-group-label">{{ $groupLabels[$groupKey] ?? 'Tabel '.$groupKey }}</span>
                            <span class="menu-group-meta">Tabel {{ $groupKey }}</span>
                        </span>
                        <span class="menu-group-count">{{ count($items) }}</span>
                        <svg class="menu-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="menu-group-panel" id="menu-group-panel-{{ $groupKey }}" data-group-panel>
                        <div class="menu-group-panel-inner">
                        <div class="menu-group-scroll space-y-0.5">
                            @foreach ($items as $table)
                                @php
                                    // Nama tabel biasanya diawali "Tabel {code})" -- diulang di
                                    // setiap baris itu jadi berisik untuk kategori berisi 15+
                                    // tabel (mis. Dosen). Kode dipindah jadi chip kecil di
                                    // depan, sisanya (label deskriptif) yang ditampilkan.
                                    // Nama yang tidak mengikuti pola ini (mis. "Tabel
                                    // Referensi 8.e.2)") otomatis lolos apa adanya.
                                    $itemLabel = preg_replace(
                                        '/^Tabel\s+'.preg_quote($table['code'], '/').'\)?\s*/u',
                                        '',
                                        $table['name'],
                                        1
                                    );
                                    $itemLabel = trim((string) $itemLabel) !== '' ? $itemLabel : $table['name'];
                                @endphp
                                <button
                                    type="button"
                                    data-table-id="{{ $table['id'] }}"
                                    data-search="{{ Illuminate\Support\Str::lower($table['code'] . ' ' . $table['name']) }}"
                                    class="menu-item {{ $table['id'] === $activeTableId ? 'is-active' : '' }}"
                                >
                                    <span class="menu-item-code">{{ $table['code'] }}</span>
                                    <span class="menu-item-text">{{ $itemLabel }}</span>
                                </button>
                            @endforeach
                        </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="menu-group" data-menu-group>
                    <div class="mb-1 flex items-center gap-2 px-3">
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider" style="color:var(--side-faint)">Tabel {{ $groupKey }}</span>
                        <span class="h-px flex-1" style="background:var(--side-border)"></span>
                    </div>
                    <div class="space-y-0.5">
                        @foreach ($items as $table)
                            <button
                                type="button"
                                data-table-id="{{ $table['id'] }}"
                                data-search="{{ Illuminate\Support\Str::lower($table['code'] . ' ' . $table['name']) }}"
                                class="menu-item {{ $table['id'] === $activeTableId ? 'is-active' : '' }}"
                            >
                                {{ $table['name'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <p id="menu-empty" class="hidden px-3 py-8 text-center text-[12px]" style="color:var(--side-faint)">Tidak ada tabel yang cocok dengan pencarian.</p>
    </nav>

    @if ($ssoUser)
        <div class="sidebar-footer px-3 py-3" style="border-top:1px solid var(--side-border)">
            @if ($ssoUser['is_admin'] ?? false)
                <a href="{{ route('admin.login-as') }}"
                   class="mb-2.5 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[12px] font-semibold"
                   style="color:var(--side-text);background:var(--color-accent-soft)"
                   title="Login sebagai (uji role)">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3 3-2-2"/>
                    </svg>
                    <span class="sidebar-admin-link-text">Login sebagai (uji role)</span>
                </a>
            @endif
            <div class="sidebar-identity-row flex items-center gap-2.5">
                {{-- Blok identitas jadi tombol pembuka profil. Logout tetap
                     <a> terpisah di luar tombol supaya kliknya tidak ikut
                     membuka modal (dan tetap bisa dibuka di tab baru). --}}
                <button
                    type="button"
                    id="profile-trigger"
                    class="profile-trigger flex min-w-0 flex-1 items-center gap-2.5"
                    aria-haspopup="dialog"
                    aria-controls="profile-modal"
                    title="Lihat profil dari SSO"
                >
                    <img
                        src="{{ ($ssoUser['is_admin'] ?? false) ? 'https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExbm9tYnYycGxkemswYWFkam42cGVxdmx5bDd5bjMxeXRmMHVrZmo4cyZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/JLYQnbND9gkYU/giphy.gif' : $avatarUrl }}"
                        alt="{{ $userName }}"
                        class="h-8 w-8 shrink-0 rounded-full object-cover"
                        style="background:var(--color-accent-soft)"
                    >
                    <span class="sidebar-identity-text min-w-0 flex-1 text-left leading-tight">
                        <span class="block truncate text-[12px] font-semibold" style="color:var(--side-text)">
                            {{ $userName }}
                        </span>
                        <span class="block truncate text-[11px]" style="color:var(--side-faint)">
                            @if ($ssoUser['is_admin'] ?? false)
                                Developer SAPTO
                            @else
                                Pengelola Program Studi{{ isset($ssoUser['unit_kerja']) ? ' — '.$ssoUser['unit_kerja'] : '' }}
                            @endif
                        </span>
                    </span>
                </button>
                <a
                    href="{{ route('sso.logout') }}"
                    class="sidebar-logout shrink-0"
                    title="Logout"
                    aria-label="Logout"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif
</aside>

<script>
    (() => {
        const toggles = Array.from(document.querySelectorAll('[data-group-toggle]'));

        function setOpen(toggle, open) {
            toggle.setAttribute('aria-expanded', String(open));
            toggle.closest('.menu-group-dropdown')?.classList.toggle('is-collapsed', !open);
        }

        function openOnly(toggle) {
            toggles.forEach((t) => setOpen(t, t === toggle));
        }

        function isRail() {
            const appShell = document.getElementById('app-shell');

            return !!appShell?.classList.contains('sidebar-closed') && window.matchMedia('(min-width: 64rem)').matches;
        }

        // Rail (sidebar collapsed jadi ikon di desktop) tidak punya ruang
        // untuk daftar sub-tabel, jadi panel grup ditampilkan sebagai
        // flyout mengambang di sisi kanan ikon -- posisinya dihitung dari
        // getBoundingClientRect ikon yang diklik supaya lepas dari
        // clipping overflow milik <nav> (overflow-y-auto membuat
        // overflow-x ikut auto, jadi position:absolute biasa akan
        // terpotong).
        //
        // #sidebar pakai position:sticky, yang SELALU membuat stacking
        // context baru terlepas dari z-index-nya -- jadi panel flyout
        // (position:fixed) yang masih jadi keturunan #sidebar tetap kepasang
        // di dalam stacking context sidebar itu, kepakai di BELAKANG konten
        // utama meski z-index-nya lebih besar. Panel dipindah jadi anak
        // <body> langsung selagi flyout terbuka supaya lolos dari jebakan
        // itu, lalu dikembalikan ke tempat asalnya (di dalam grup-nya) saat
        // ditutup supaya mode expand normal tidak rusak.
        let hoverOpenTimer = null;
        let hoverCloseTimer = null;

        function clearHoverTimers() {
            clearTimeout(hoverOpenTimer);
            clearTimeout(hoverCloseTimer);
            hoverOpenTimer = null;
            hoverCloseTimer = null;
        }

        // Delay kecil sebelum tutup supaya kursor sempat pindah dari ikon
        // ke flyout (ada jarak ~8px di antaranya) tanpa flyout-nya kekunci
        // duluan.
        function scheduleClose() {
            clearTimeout(hoverCloseTimer);
            hoverCloseTimer = setTimeout(closeFlyout, 250);
        }

        function cancelClose() {
            clearTimeout(hoverCloseTimer);
            hoverCloseTimer = null;
        }

        function closeFlyout() {
            clearHoverTimers();
            document.querySelectorAll('.menu-group-flyout').forEach((panel) => {
                panel.classList.remove('menu-group-flyout');
                panel.removeAttribute('style');
                panel.flyoutHome?.appendChild(panel);
                panel.flyoutHome = null;
            });
            document.querySelectorAll('[data-group-toggle][aria-expanded="true"]').forEach((toggle) => {
                if (isRail()) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
            document.removeEventListener('click', onOutsideClick, true);
            document.removeEventListener('keydown', onFlyoutKeydown, true);
        }

        function onOutsideClick(event) {
            if (!event.target.closest('.menu-group-flyout') && !event.target.closest('[data-group-toggle]')) {
                closeFlyout();
            }
        }

        function onFlyoutKeydown(event) {
            if (event.key === 'Escape') {
                closeFlyout();
            }
        }

        function openFlyout(toggle) {
            closeFlyout();

            const group = toggle.closest('.menu-group-dropdown');
            const panel = group?.querySelector('.menu-group-panel');

            if (!panel) {
                return;
            }

            const rect = toggle.getBoundingClientRect();
            const margin = 8;

            panel.flyoutHome = group;
            document.body.appendChild(panel);
            panel.classList.add('menu-group-flyout');
            panel.style.left = `${Math.round(rect.right + margin)}px`;
            panel.style.maxHeight = `${Math.round(window.innerHeight - margin * 2)}px`;
            panel.style.top = `${Math.round(rect.top)}px`;

            // Kategori dekat bagian bawah rail (mis. Tabel 8, 15 sub-tabel)
            // bikin flyout yang nempel ke rect.top kepotong di tepi bawah
            // layar. Ukur tinggi asli setelah dirender, lalu geser ke atas
            // (flip) sebesar bagian yang meluber -- dijepit supaya tetap
            // tidak melewati tepi atas viewport.
            const panelHeight = panel.getBoundingClientRect().height;
            const overflowBottom = (rect.top + panelHeight) - (window.innerHeight - margin);

            if (overflowBottom > 0) {
                panel.style.top = `${Math.round(Math.max(margin, rect.top - overflowBottom))}px`;
            }

            toggle.setAttribute('aria-expanded', 'true');

            document.addEventListener('click', onOutsideClick, true);
            document.addEventListener('keydown', onFlyoutKeydown, true);
        }

        toggles.forEach((toggle) => {
            // ID panel-nya stabil (dipakai buat lookup di openFlyout juga),
            // jadi referensinya aman diambil sekali di sini walau nanti node
            // panel ini dipindah-pindah ke <body> dan balik lagi (portal).
            const panel = document.getElementById(toggle.getAttribute('aria-controls'));

            toggle.addEventListener('click', () => {
                if (isRail()) {
                    clearHoverTimers();
                    const alreadyOpen = toggle.getAttribute('aria-expanded') === 'true';
                    alreadyOpen ? closeFlyout() : openFlyout(toggle);

                    return;
                }

                const isOpen = toggle.getAttribute('aria-expanded') === 'true';
                isOpen ? setOpen(toggle, false) : openOnly(toggle);
            });

            // Hover = interaksi utama di rail (klik masih jalan buat
            // sentuh/keyboard) -- arahkan kursor ke ikon, flyout muncul;
            // pindah ke ikon lain, flyout lama ganti otomatis (openFlyout
            // memanggil closeFlyout duluan).
            toggle.addEventListener('mouseenter', () => {
                if (!isRail()) {
                    return;
                }

                cancelClose();
                clearTimeout(hoverOpenTimer);
                hoverOpenTimer = setTimeout(() => openFlyout(toggle), 120);
            });

            toggle.addEventListener('mouseleave', () => {
                clearTimeout(hoverOpenTimer);

                if (isRail()) {
                    scheduleClose();
                }
            });

            toggle.addEventListener('focus', () => {
                if (isRail()) {
                    openFlyout(toggle);
                }
            });

            toggle.addEventListener('blur', () => {
                if (isRail()) {
                    scheduleClose();
                }
            });

            panel?.addEventListener('mouseenter', () => {
                if (isRail()) {
                    cancelClose();
                }
            });

            panel?.addEventListener('mouseleave', () => {
                if (isRail()) {
                    scheduleClose();
                }
            });

            panel?.addEventListener('focusin', () => {
                if (isRail()) {
                    cancelClose();
                }
            });
        });

        // Pilih sub-tabel dari flyout -> flyout-nya tutup sendiri (aktivasi
        // tabelnya ditangani listener terpisah di sapto.blade.php).
        document.querySelectorAll('.menu-item').forEach((item) => {
            item.addEventListener('click', () => {
                if (item.closest('.menu-group-flyout')) {
                    closeFlyout();
                }
            });
        });

        // Dipanggil dari activateTable() (lihat sapto.blade.php) supaya
        // kategori berisi tabel yang baru aktif ikut terbuka dan disorot di
        // sidebar, termasuk saat tabel diaktifkan dari luar sidebar (mis.
        // kartu dashboard).
        window.saptoExpandMenuGroupFor = (tableId) => {
            const activeGroup = document
                .querySelector(`.menu-item[data-table-id="${tableId}"]`)
                ?.closest('.menu-group-dropdown');

            document.querySelectorAll('.menu-group-dropdown').forEach((group) => {
                group.classList.toggle('has-active', group === activeGroup);
            });

            const toggle = activeGroup?.querySelector('[data-group-toggle]');

            if (toggle && !isRail()) {
                openOnly(toggle);
            }
        };
    })();
</script>

@if ($ssoUser)
    <div id="profile-backdrop" class="profile-backdrop fixed inset-0 z-40 hidden"></div>

    <div
        id="profile-modal"
        class="profile-modal glass-strong fixed z-50 hidden flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-modal-title"
    >
        <header class="flex items-start justify-between gap-3 px-5 py-4" style="border-bottom:1px solid var(--color-line)">
            <div class="flex min-w-0 items-center gap-3">
                <img
                    src="{{ ($ssoUser['is_admin'] ?? false) ? 'https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExbm9tYnYycGxkemswYWFkam42cGVxdmx5bDd5bjMxeXRmMHVrZmo4cyZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/JLYQnbND9gkYU/giphy.gif' : $avatarUrl }}"
                    alt=""
                    class="h-12 w-12 shrink-0 rounded-full object-cover"
                    style="background:var(--color-accent-soft)"
                    aria-hidden="true"
                >
                <div class="min-w-0">
                    <h3 id="profile-modal-title" class="truncate text-[16px] font-bold text-ink">{{ $userName }}</h3>
                    <p class="truncate text-[12px] text-muted">Data dari SSO Universitas Sriwijaya</p>
                </div>
            </div>
            <button type="button" id="profile-close" class="theme-toggle shrink-0" aria-label="Tutup profil">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            <dl class="profile-grid">
                @foreach ($profileKnown as $label => $value)
                    <div class="profile-row">
                        <dt class="profile-label">{{ $label }}</dt>
                        <dd class="profile-value">
                            {{ $value }}
                            @if ($label === 'Email' && ($ssoUser['email_verified'] ?? false))
                                <span class="profile-badge">terverifikasi</span>
                            @endif
                        </dd>
                    </div>
                @endforeach

                @foreach ($profileExtra as $key => $value)
                    <div class="profile-row">
                        <dt class="profile-label">{{ $key }}</dt>
                        <dd class="profile-value">{{ is_bool($value) ? ($value ? 'ya' : 'tidak') : $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Hak akses ditampilkan di sini karena inilah yang menentukan
                 sebanyak apa data yang terlihat di tabel — sering bikin bingung
                 kalau tidak eksplisit (filter fakultas hilang untuk korprodi). --}}
            <h4 class="mb-2 mt-5 text-[12px] font-bold uppercase tracking-wide text-muted">Hak akses SAPTO</h4>
            <dl class="profile-grid">
                <div class="profile-row">
                    <dt class="profile-label">Peran</dt>
                    <dd class="profile-value">
                        @if ($ssoUser['is_admin'] ?? false)
                            Admin — semua fakultas &amp; program studi
                        @else
                            Koordinator program studi — hanya prodi sendiri
                        @endif
                    </dd>
                </div>
                @if (session('impersonator_sso_user'))
                    <div class="profile-row">
                        <dt class="profile-label">Mode uji</dt>
                        <dd class="profile-value">
                            Sedang dilihat oleh {{ session('impersonator_sso_user.name') }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <script>
        (() => {
            const trigger = document.getElementById('profile-trigger');
            const modal = document.getElementById('profile-modal');
            const backdrop = document.getElementById('profile-backdrop');
            const closeBtn = document.getElementById('profile-close');

            if (!trigger || !modal || !backdrop) {
                return;
            }

            function open() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                backdrop.classList.remove('hidden');
                closeBtn?.focus();
            }

            function close() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                backdrop.classList.add('hidden');
                trigger.focus();
            }

            trigger.addEventListener('click', open);
            closeBtn?.addEventListener('click', close);
            backdrop.addEventListener('click', close);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    close();
                }
            });
        })();
    </script>
@endif

<style>
    /* Beberapa nama tabel punya rangkaian panjang tanpa spasi, mis.
       "Pagelaran/Pameran/Presentasi/Publikasi" (38 karakter) di Tabel 3.b.4
       versi vokasi. Tanpa overflow-wrap, satu "kata" itu tidak bisa dipenggal
       dan teksnya meluber keluar dari card sidebar. anywhere dipakai (bukan
       break-word) supaya pemenggalan turut dihitung saat menentukan lebar
       minimum, jadi benar-benar tidak pernah meluber.

       Ditaruh di sini (bukan resources/css/app.css) karena app.css harus
       lewat `npm run build` untuk berlaku, dan build itu sedang gagal di
       server ini: node_modules/.bin milik root dari build Docker sebelumnya,
       sementara shell jalan sebagai user sapto, jadi npm tidak bisa menulis
       binding native rolldown yang hilang. Blok <style> di komponen blade
       ini dirender langsung tanpa build, jadi perbaikannya berlaku seketika.
       Pindahkan ke app.css kalau masalah izin node_modules sudah dibereskan. */
    .menu-item {
        overflow-wrap: anywhere;
    }

    .menu-group-dropdown {
        border-radius: 0.75rem;
        transition: background-color .15s ease;
    }

    .menu-group-dropdown.has-active {
        background: var(--side-hover);
    }

    .menu-group-toggle {
        display: flex;
        width: 100%;
        align-items: center;
        gap: 0.65rem;
        border: 0;
        background: transparent;
        padding: 0.5rem 0.6rem;
        border-radius: 0.75rem;
        cursor: pointer;
        font: inherit;
        text-align: left;
        color: var(--side-text);
        transition: background-color .15s ease;
    }

    .menu-group-toggle:hover {
        background: var(--side-hover);
    }

    .menu-group-icon {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: 0.6rem;
        background: var(--color-accent-soft);
        color: var(--color-accent-dark, var(--color-accent));
        transition: background-color .15s ease, color .15s ease;
    }

    .menu-group-dropdown.has-active .menu-group-icon {
        background: var(--color-accent);
        color: #fff;
    }

    .menu-group-icon svg {
        width: 1.05rem;
        height: 1.05rem;
    }

    .menu-group-text {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.05rem;
    }

    .menu-group-label {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        font-size: 12.5px;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.005em;
    }

    .menu-group-meta {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--side-faint);
    }

    .menu-group-count {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        min-width: 1.3rem;
        height: 1.3rem;
        padding: 0 0.3rem;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        background: var(--side-hover);
        color: var(--side-faint);
    }

    .menu-group-dropdown.has-active .menu-group-count {
        background: var(--color-accent-soft);
        color: var(--color-accent-dark, var(--color-accent));
    }

    .menu-group-chevron {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        color: var(--side-faint);
        transition: transform .2s ease;
    }

    .menu-group-dropdown.is-collapsed .menu-group-chevron {
        transform: rotate(-90deg);
    }

    .menu-group-panel {
        display: grid;
        grid-template-rows: 1fr;
        transition: grid-template-rows .22s ease;
    }

    .menu-group-dropdown.is-collapsed .menu-group-panel {
        grid-template-rows: 0fr;
    }

    .menu-group-panel-inner {
        overflow: hidden;
        min-height: 0;
    }

    /* Kategori seperti "Dosen" atau "Capaian & Profil Lulusan" bisa punya
       15+ tabel — tanpa batas tinggi, membukanya mendorong seluruh kategori
       lain keluar layar. Scroll internal menjaga sidebar tetap dalam ukuran
       yang bisa diprediksi berapa pun jumlah tabelnya. */
    .menu-group-scroll {
        max-height: 15.5rem;
        overflow-y: auto;
        margin: 0.15rem 0 0.35rem 1.55rem;
        padding-left: 0.65rem;
        padding-right: 0.25rem;
        border-left: 1px solid var(--side-border);
        scrollbar-width: thin;
        scrollbar-color: var(--color-accent-line) transparent;
    }

    .menu-group-scroll::-webkit-scrollbar {
        width: 5px;
    }

    .menu-group-scroll::-webkit-scrollbar-thumb {
        background: var(--color-accent-line);
        border-radius: 999px;
    }

    .menu-group-panel-inner .menu-item {
        gap: 0.5rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        font-size: 12.5px;
    }

    .menu-item-code {
        flex-shrink: 0;
        padding: 0.05rem 0.35rem;
        border-radius: 0.35rem;
        font-size: 10px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        background: var(--side-hover);
        color: var(--side-faint);
    }

    .menu-item.is-active .menu-item-code {
        background: var(--color-accent-soft);
        color: var(--color-accent-dark, var(--color-accent));
    }

    .menu-item-text {
        min-width: 0;
    }

    .profile-trigger {
        border: 0;
        background: transparent;
        padding: 0.25rem;
        margin: -0.25rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font: inherit;
        transition: background-color .15s ease;
    }

    .profile-trigger:hover {
        background: var(--side-hover);
    }

    .profile-backdrop {
        background: rgba(3, 7, 18, 0.55);
        -webkit-backdrop-filter: blur(2px);
        backdrop-filter: blur(2px);
    }

    .profile-modal {
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(560px, calc(100vw - 2rem));
        max-height: min(80vh, 640px);
        border-radius: 1rem;
        overflow: hidden;
    }

    .profile-grid {
        display: grid;
        gap: 1px;
        background: var(--color-line);
        border: 1px solid var(--color-line);
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .profile-row {
        display: grid;
        grid-template-columns: minmax(0, 8.5rem) minmax(0, 1fr);
        gap: 0.75rem;
        padding: 0.6rem 0.85rem;
        background: var(--color-surface, var(--color-canvas));
    }

    @media (max-width: 480px) {
        .profile-row {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.15rem;
        }
    }

    .profile-label {
        font-size: 12px;
        color: var(--color-muted);
    }

    .profile-value {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-ink);
        overflow-wrap: anywhere;
    }

    .profile-badge {
        display: inline-block;
        margin-left: 0.35rem;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        background: var(--color-accent-soft);
        color: var(--color-accent-dark, var(--color-accent));
        vertical-align: middle;
    }

    .sidebar-logout {
        display: grid;
        place-items: center;
        height: 26px;
        width: 26px;
        border-radius: 0.5rem;
        color: var(--side-faint);
        transition: background-color .15s ease, color .15s ease;
    }

    .sidebar-logout:hover {
        background: var(--side-hover);
        color: var(--side-text);
    }

    /* Rail (sidebar "tertutup") di desktop — lihat #app-shell.sidebar-closed
       di app.css untuk lebar/grid-nya. Aturan isi rail ditaruh di sini
       (bukan app.css) karena blok <style> ini unlayered (di luar @layer
       components), jadi menang di cascade atas base style kelas yang sama
       di file ini sendiri terlepas dari urutan/spesifisitas @layer. */
    @media (min-width: 64rem) {
        #app-shell.sidebar-closed #sidebar .sidebar-header {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        #app-shell.sidebar-closed #sidebar .sidebar-brand-text,
        #app-shell.sidebar-closed #sidebar .sidebar-search,
        #app-shell.sidebar-closed #sidebar .menu-group-text,
        #app-shell.sidebar-closed #sidebar .menu-group-count,
        #app-shell.sidebar-closed #sidebar .menu-group-chevron,
        #app-shell.sidebar-closed #sidebar .menu-group-panel,
        #app-shell.sidebar-closed #sidebar .sidebar-admin-link-text,
        #app-shell.sidebar-closed #sidebar .sidebar-identity-text {
            display: none;
        }

        #app-shell.sidebar-closed #sidebar .menu-group-toggle {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        /* Avatar + logout tak muat sejajar di rail 4.5rem — ditumpuk. */
        #app-shell.sidebar-closed #sidebar .sidebar-identity-row {
            flex-direction: column;
            gap: 0.6rem;
        }

        #app-shell.sidebar-closed #sidebar .profile-trigger {
            flex: none;
            justify-content: center;
        }

        #app-shell.sidebar-closed #sidebar .sidebar-footer a[href*="login-as"] {
            justify-content: center;
        }

        #app-shell.sidebar-closed #sidebar .menu-group-dropdown.has-active .menu-group-toggle {
            box-shadow: inset 0 0 0 1px var(--color-accent-line);
        }
    }

    /* Flyout: sub-tabel tetap bisa dipilih tanpa membuka sidebar penuh.
       JS (openFlyout) memindahkan panel ini jadi anak <body> langsung
       selagi terbuka (lihat komentar "portal" di <script> di atas), jadi
       selector di sini TIDAK diawali #sidebar -- saat flyout aktif panel
       sudah bukan keturunan #sidebar lagi. Dua kelas
       (.menu-group-panel.menu-group-flyout) tetap cukup spesifik untuk
       mengalahkan base style .menu-group-panel/.menu-group-scroll di atas.
       top/left/max-height diisi lewat JS (getBoundingClientRect ikon). */
    .menu-group-panel.menu-group-flyout {
        display: block;
        position: fixed;
        z-index: 60;
        width: 16rem;
        margin: 0;
        padding: 0.5rem;
        border-radius: 0.9rem;
        background: var(--side-bg);
        border: 1px solid var(--side-border);
        box-shadow: var(--glass-shadow);
        -webkit-backdrop-filter: blur(var(--glass-blur));
        backdrop-filter: blur(var(--glass-blur));
    }

    .menu-group-panel.menu-group-flyout .menu-group-panel-inner,
    .menu-group-panel.menu-group-flyout .menu-group-scroll {
        display: block;
        max-height: none;
    }

    .menu-group-panel.menu-group-flyout .menu-group-scroll {
        max-height: 60vh;
        overflow-y: auto;
        margin: 0;
        padding: 0;
        border-left: 0;
    }
</style>
