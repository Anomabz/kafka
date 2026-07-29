@extends('layouts.sapto')

@section('title', 'SAPTO')

@php
    $icons = [
        'users' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'cap' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>',
        'swap' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>',
        'calendar' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'globe' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/></svg>',
    ];
@endphp

@section('content')
    <div id="shared-filter-bar">
        @include('components.filter-bar')
    </div>

    @include('components.dashboard-page')

    <section id="table-page" class="hidden">
        <div class="mb-4 grid grid-cols-2 gap-2.5 sm:mb-5 sm:gap-3 lg:grid-cols-4">
            @include('components.metric-card', ['id' => 'metric-dosen', 'labelId' => 'metric-dosen-label', 'label' => 'Total dosen pembimbing', 'icon' => $icons['users'], 'tint' => 'accent'])
            @include('components.metric-card', ['id' => 'metric-ps', 'labelId' => 'metric-ps-label', 'label' => 'Total mahasiswa bimbingan PS diakreditasi', 'icon' => $icons['cap'], 'tint' => 'info'])
            @include('components.metric-card', ['id' => 'metric-ps-lain', 'labelId' => 'metric-ps-lain-label', 'label' => 'Total mahasiswa bimbingan PS lain di PT', 'icon' => $icons['swap'], 'tint' => 'warn'])
            @include('components.metric-card', ['id' => 'metric-ts', 'labelId' => 'metric-ts-label', 'label' => 'TS aktif', 'icon' => $icons['calendar'], 'tint' => 'slate'])
        </div>

        @include('components.table-panel')
    </section>

    <section id="mahasiswa-asing-page" class="hidden">
        @include('components.mahasiswa-asing-filter')

        <div class="mb-4 grid grid-cols-2 gap-2.5 sm:mb-5 sm:gap-3 lg:grid-cols-4">
            @include('components.metric-card', ['id' => 'ma-metric-prodi', 'label' => 'Total program studi', 'icon' => $icons['cap'], 'tint' => 'accent'])
            @include('components.metric-card', ['id' => 'ma-metric-aktif', 'label' => 'Total mahasiswa asing aktif (TS)', 'icon' => $icons['users'], 'tint' => 'info'])
            @include('components.metric-card', ['id' => 'ma-metric-ft', 'label' => 'Mahasiswa asing penuh waktu (TS)', 'icon' => $icons['globe'], 'tint' => 'warn'])
            @include('components.metric-card', ['id' => 'ma-metric-pt', 'label' => 'Mahasiswa asing paruh waktu (TS)', 'icon' => $icons['calendar'], 'tint' => 'slate'])
        </div>

        @include('components.mahasiswa-asing-panel')
    </section>

    <section id="placeholder-page" class="glass hidden rounded-2xl p-6">
        <h2 id="placeholder-title" class="text-[16px] font-bold text-ink"></h2>
        <p class="mt-2 text-[13px] text-muted">
            Halaman ini belum dikembangkan pada tahap pertama. Fokus tahap pertama adalah Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir.
        </p>
    </section>
@endsection

@section('overlays')
    @include('components.detail-drawer')
@endsection

@section('scripts')
<script>
    const tables = @json($tables);
    const currentYear = new Date().getFullYear();
    const PAGE_SIZE = 12; // baris per halaman, semua tabel
    // Akses korprodi biasa selalu dikunci ke satu program studi (lihat
    // SaptoUserContext::fromRequest) — canSelectFakultas/canSelectProgramStudi
    // datang dari API (/api/sapto/fakultas, /api/sapto/program-studi), bukan
    // dari pilihan klien; true hanya untuk akun admin (is_admin=true).
    const state = {
        ts: null, // diisi dari available_years hasil summary (mengikuti isi database)
        maTs: null, // TS khusus halaman Mahasiswa Asing (filter-nya terpisah dari #ts)
        fakultasId: 'all',
        programStudiId: 'all',
        fakultas: [],
        programStudi: [],
        canSelectFakultas: false,
        canSelectProgramStudi: false,
        summary: null,
        page: 1, // halaman aktif tabel dosen pembimbing
        searchQuery: '', // filter teks lokal di tabel aktif (table-page), lowercase
    };

    const els = {
        ts: document.getElementById('ts'),
        fakultas: document.getElementById('fakultas'),
        fakultasWrap: document.getElementById('fakultas-wrap'),
        programStudi: document.getElementById('program-studi'),
        programStudiWrap: document.getElementById('program-studi-wrap'),
        tableCardTitle: document.getElementById('table-card-title'),
        activeUnit: document.getElementById('active-unit'),
        tablePage: document.getElementById('table-page'),
        placeholderPage: document.getElementById('placeholder-page'),
        placeholderTitle: document.getElementById('placeholder-title'),
        dashboardPage: document.getElementById('dashboard-page'),
        dashboardStatus: document.getElementById('dashboard-status'),
        dashboardCards: document.querySelectorAll('.dashboard-card'),
        sharedFilterBar: document.getElementById('shared-filter-bar'),
        pageTitle: document.getElementById('page-title'),
        pageDescription: document.getElementById('page-description'),
        tableStatus: document.getElementById('table-status'),
        tableSearch: document.getElementById('table-search'),
        tableExport: document.getElementById('table-export'),
        tableHead: document.getElementById('table-head'),
        tableBody: document.getElementById('table-body'),
        tableFoot: document.getElementById('table-foot'),
        tablePagination: document.getElementById('table-pagination'),
        metricDosen: document.getElementById('metric-dosen'),
        metricPs: document.getElementById('metric-ps'),
        metricPsLain: document.getElementById('metric-ps-lain'),
        metricTs: document.getElementById('metric-ts'),
        metricDosenLabel: document.getElementById('metric-dosen-label'),
        metricPsLabel: document.getElementById('metric-ps-label'),
        metricPsLainLabel: document.getElementById('metric-ps-lain-label'),
        metricTsLabel: document.getElementById('metric-ts-label'),
        drawer: document.getElementById('detail-drawer'),
        drawerBackdrop: document.getElementById('drawer-backdrop'),
        detailTitle: document.getElementById('detail-title'),
        detailSubtitle: document.getElementById('detail-subtitle'),
        detailBody: document.getElementById('detail-body'),
        closeDrawer: document.getElementById('close-drawer'),
        menuSearch: document.getElementById('menu-search'),
        menuGroups: document.querySelectorAll('[data-menu-group]'),
        menuEmpty: document.getElementById('menu-empty'),
        appShell: document.getElementById('app-shell'),
        sidebar: document.getElementById('sidebar'),
        sidebarBackdrop: document.getElementById('sidebar-backdrop'),
        sidebarToggle: document.getElementById('sidebar-toggle'),
        sidebarClose: document.getElementById('sidebar-close'),
        maPage: document.getElementById('mahasiswa-asing-page'),
        maTableStatus: document.getElementById('ma-table-status'),
        maTableSearch: document.getElementById('ma-table-search'),
        maTableExport: document.getElementById('ma-table-export'),
        maTableHead: document.getElementById('ma-table-head'),
        maTableBody: document.getElementById('ma-table-body'),
        maTableFoot: document.getElementById('ma-table-foot'),
        maMetricProdi: document.getElementById('ma-metric-prodi'),
        maMetricAktif: document.getElementById('ma-metric-aktif'),
        maMetricFt: document.getElementById('ma-metric-ft'),
        maMetricPt: document.getElementById('ma-metric-pt'),
        maActiveUnit: document.getElementById('ma-active-unit'),
        maTs: document.getElementById('ma-ts'),
        maFakultas: document.getElementById('ma-fakultas'),
        maFakultasWrap: document.getElementById('ma-fakultas-wrap'),
        maProgramStudi: document.getElementById('ma-program-studi'),
        maProgramStudiWrap: document.getElementById('ma-program-studi-wrap'),
    };

    const metricEls = [els.metricDosen, els.metricPs, els.metricPsLain, els.metricTs];
    const maMetricEls = [els.maMetricProdi, els.maMetricAktif, els.maMetricFt, els.maMetricPt];
    let maSummary = null;

    // Filter halaman Mahasiswa Asing — khusus admin, dan cuma memuat prodi/
    // fakultas yang benar-benar punya mahasiswa asing (dibangun dari data
    // maSummary, bukan katalog penuh). Korprodi biasa tak melihat dropdown ini
    // (server sudah scope ke satu prodi mereka).
    const maFilter = { fakultasId: 'all', prodiId: 'all', searchQuery: '' };

    let currentTableId = @json($activeTableId ?? 'kerjasama-pendidikan');

    // TS awal belum diketahui sampai summary pertama memberi tahu tahun akademik
    // apa saja yang ada di database. Tampilkan placeholder dulu.
    function initTsOptions() {
        els.ts.innerHTML = '<option value="">Memuat...</option>';
        els.maTs.innerHTML = '<option value="">Memuat...</option>';
    }

    // Isi dropdown TS dari daftar tahun akademik yang benar-benar ada di
    // database (available_years), lalu pilih tahun aktif.
    function populateTsOptions(el, years, selected) {
        const list = Array.isArray(years) && years.length ? years : [selected];

        el.innerHTML = list.map((year) => `<option value="${year}">${year}</option>`).join('');
        el.value = String(selected);
    }

    // Scope (fakultas/prodi) selalu ditentukan server-side dari akun yang
    // login (lihat SaptoUserContext::fromRequest) — header ini cuma
    // membantu disambiguasi akun dengan dua program studi (co-koordinator);
    // server mengabaikannya kalau tidak cocok dengan entitlement akun.
    function contextHeaders() {
        const headers = { Accept: 'application/json' };
        const prodiId = state.programStudi[0]?.id;

        if (prodiId) {
            headers['X-SAPTO-Program-Studi-Id'] = String(prodiId);
        }

        return headers;
    }

    // Sekali coba ulang untuk kegagalan sementara (putus jaringan atau 5xx,
    // termasuk timeout proxy saat cache masih dingin). Percobaan pertama yang
    // lambat itulah yang mengisi cache, jadi percobaan kedua biasanya langsung
    // cepat. 4xx (401/403/400) tidak diulang: itu jawaban final dari server.
    async function fetchJson(url, retry = true) {
        let response;

        try {
            response = await fetch(url, { headers: contextHeaders() });
        } catch (error) {
            if (retry) {
                return fetchJson(url, false);
            }

            throw new Error('Tidak bisa menghubungi server. Periksa koneksi lalu muat ulang halaman.');
        }

        if (response.status >= 500 && retry) {
            return fetchJson(url, false);
        }

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Permintaan data gagal.');
        }

        return payload;
    }

    // Fakultas: cuma admin (is_admin=true) yang can_select_fakultas=true dari
    // API — korprodi biasa tak pernah lihat field ini (lihat filter-bar.blade.php).
    async function loadFakultas() {
        const payload = await fetchJson('/api/sapto/fakultas');
        state.canSelectFakultas = Boolean(payload.can_select_fakultas);
        state.fakultas = payload.data || [];

        els.fakultasWrap.classList.toggle('hidden', !state.canSelectFakultas);

        if (state.canSelectFakultas) {
            renderFakultasSelect();
        }
    }

    // Program studi: satu-satunya untuk korprodi biasa (kosong kalau data
    // referensinya tidak ditemukan, lihat SaptoUserContext::fromRequest), atau
    // daftar penuh + pilihan bebas untuk admin (can_select_program_studi=true).
    async function loadProgramStudi() {
        const params = new URLSearchParams();

        if (state.canSelectFakultas && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        const payload = await fetchJson(`/api/sapto/program-studi${params.size ? `?${params}` : ''}`);
        state.canSelectProgramStudi = Boolean(payload.can_select_program_studi);
        state.programStudi = payload.data || [];

        els.programStudiWrap.classList.toggle('hidden', !state.canSelectProgramStudi);

        if (state.canSelectProgramStudi) {
            const exists = state.programStudi.some((item) => String(item.id) === String(state.programStudiId));

            if (state.programStudiId !== 'all' && !exists) {
                state.programStudiId = 'all';
            }

            renderProgramStudiSelect();
        } else {
            renderActiveUnit();
        }
    }

    // Filter bar utama (dashboard + tabel dosen/kerjasama) — daftar penuh
    // katalog prodi. Halaman Mahasiswa Asing punya dropdown sendiri (ma-*)
    // yang cuma memuat prodi yang benar-benar punya mahasiswa asing.
    function renderFakultasSelect() {
        els.fakultas.innerHTML = [
            '<option value="all">Semua Fakultas</option>',
            ...state.fakultas.map((item) => `<option value="${item.id}">${escapeHtml(item.nama)}</option>`),
        ].join('');
        els.fakultas.value = state.fakultasId;
    }

    function renderProgramStudiSelect() {
        els.programStudi.innerHTML = [
            '<option value="all">Semua Program Studi</option>',
            ...state.programStudi.map((item) => `<option value="${item.id}">${escapeHtml(item.jenjang)} ${escapeHtml(item.nama)} - ${escapeHtml(item.fakultas)}</option>`),
        ].join('');
        els.programStudi.value = state.programStudiId;

        renderActiveUnit();
    }

    // Dipakai oleh filter bar utama (activeUnit) dan filter Mahasiswa Asing
    // (maActiveUnit) — sama-sama turunan dari state fakultas/prodi terpilih.
    function activeUnitLabel(fallbackText) {
        if (!state.canSelectFakultas && !state.canSelectProgramStudi) {
            return fallbackText || 'Program studi akun tidak ditemukan';
        }

        if (state.canSelectProgramStudi && state.programStudiId !== 'all') {
            const selected = state.programStudi.find((item) => String(item.id) === String(state.programStudiId));

            if (selected) {
                return `${selected.jenjang} ${selected.nama}`;
            }
        }

        if (state.canSelectFakultas && state.fakultasId !== 'all') {
            const fakultas = state.fakultas.find((item) => String(item.id) === String(state.fakultasId));

            if (fakultas) {
                return fakultas.nama;
            }
        }

        return 'Universitas Sriwijaya';
    }

    // Untuk korprodi: nama prodi tunggal akun; untuk admin: null (activeUnitLabel
    // yang menentukan dari fakultas/prodi terpilih).
    function activeUnitFallback() {
        const selected = state.programStudi[0];
        return selected ? `${selected.jenjang} ${selected.nama}` : null;
    }

    function renderActiveUnit() {
        els.activeUnit.textContent = activeUnitLabel(activeUnitFallback());
    }

    function summaryUrl() {
        const params = new URLSearchParams();

        // TS null pada load pertama -> backend memakai tahun akademik terbaru.
        if (state.ts !== null) {
            params.set('ts', state.ts);
        }

        if (state.canSelectFakultas && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.canSelectProgramStudi && state.programStudiId !== 'all') {
            params.set('program_studi_id', state.programStudiId);
        }

        const slug = isGeneric(currentTableId) ? GENERIC_TABLES[currentTableId].endpoint : 'dosen-pembimbing-ta';

        return `/api/sapto/tables/${slug}/summary?${params}`;
    }

    async function loadSummary() {
        setLoading();

        try {
            state.summary = await fetchJson(summaryUrl());
            renderSummary();
        } catch (error) {
            renderError(error.message);
        }
    }

    function setLoading() {
        metricEls.forEach((el) => el.classList.add('is-loading'));
        els.tableStatus.textContent = 'Memuat data...';
        els.tableHead.innerHTML = '';
        els.tableBody.innerHTML = Array.from({ length: 6 }).map(() => `
            <tr>
                <td class="sp-skeleton-td" colspan="12"><div class="sp-skeleton-bar w-full max-w-md"></div></td>
            </tr>
        `).join('');
        els.tableFoot.innerHTML = '';
        els.tablePagination.innerHTML = '';
    }

    // Label metric card default untuk tabel 3.a.2 (dipulihkan bila sebelumnya
    // dipakai tabel generik yang mengubah label).
    const DOSEN_METRIC_LABELS = [
        'Total dosen pembimbing',
        'Total mahasiswa bimbingan PS diakreditasi',
        'Total mahasiswa bimbingan PS lain di PT',
        'TS aktif',
    ];
    const metricValueEls = [els.metricDosen, els.metricPs, els.metricPsLain, els.metricTs];
    const metricLabelEls = [els.metricDosenLabel, els.metricPsLabel, els.metricPsLainLabel, els.metricTsLabel];
    const metricIconEls = metricValueEls.map((el) => document.getElementById(`${el.id}-icon`));

    // Ikon chip metrik: dipakai lintas tabel generik lewat kunci di sini, bukan
    // svg mentah, supaya tiap tabel bisa pilih ikon yang cocok dengan makna
    // metriknya (lihat GENERIC_TABLES[id].icons) tanpa duplikasi markup svg.
    const METRIC_ICONS = {
        users: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        cap: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>',
        swap: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>',
        calendar: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        globe: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/></svg>',
        book: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        chart: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/></svg>',
        pin: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    };
    const DEFAULT_METRIC_ICONS = ['users', 'cap', 'swap', 'calendar'];

    function setMetricLabels(labels) {
        metricLabelEls.forEach((el, i) => { if (el) el.textContent = labels[i] ?? ''; });
    }

    // Ganti svg tiap chip ikon metrik sesuai tabel aktif (fallback ke ikon
    // default 3.a.2 kalau tabel belum didefinisikan icons-nya).
    function setMetricIcons(iconKeys) {
        const keys = iconKeys || DEFAULT_METRIC_ICONS;
        metricIconEls.forEach((el, i) => {
            if (el) el.innerHTML = METRIC_ICONS[keys[i]] || METRIC_ICONS[DEFAULT_METRIC_ICONS[i]];
        });
    }

    // Isi kartu metrik dari daftar {label, value} (tabel generik). Kartu sisa
    // dikosongkan agar tidak menampilkan angka tabel sebelumnya.
    function setMetricCards(metrics) {
        metricValueEls.forEach((el, i) => {
            const metric = metrics[i];
            if (metricLabelEls[i]) metricLabelEls[i].textContent = metric ? metric.label : '';
            el.textContent = metric ? String(metric.value) : '';
        });
    }

    function renderSummary() {
        const summary = state.summary;

        // Data baru -> kembali ke halaman pertama tabel.
        state.page = 1;

        // Sinkronkan TS aktif + dropdown dengan tahun yang ada di database.
        state.ts = summary.ts;
        populateTsOptions(els.ts, summary.available_years, summary.ts);

        metricEls.forEach((el) => el.classList.remove('is-loading'));
        setMetricIcons(isGeneric(currentTableId) ? GENERIC_TABLES[currentTableId].icons : null);

        if (isGeneric(currentTableId)) {
            renderGenericSummary(summary);
        } else {
            renderDosenSummary(summary);
        }
    }

    function renderDosenSummary(summary) {
        const rows = summary.rows || [];
        const totals = summary.totals || {};

        els.tableCardTitle.textContent = 'Dosen Pembimbing Utama Tugas Akhir';
        setMetricLabels(DOSEN_METRIC_LABELS);
        els.metricDosen.textContent = rows.length;
        els.metricPs.textContent = sumYearCounts(totals.ps_diakreditasi);
        els.metricPsLain.textContent = sumYearCounts(totals.ps_lain_pt);
        els.metricTs.textContent = summary.ts;
        els.tableStatus.textContent = rows.length
            ? `${rows.length} dosen${summary.source?.partial ? ' - data API parsial' : ''}`
            : 'Belum ada data';
        renderTable(summary);
    }

    function renderGenericSummary(summary) {
        els.tableCardTitle.textContent = summary.table_name || 'Data Tabel';
        setMetricCards(summary.metrics || []);
        const unit = GENERIC_TABLES[currentTableId]?.unit || 'baris';
        els.tableStatus.textContent = summary.notice
            ? 'Pilih program studi'
            : (summary.rows.length
                ? `${summary.rows.length} ${unit}${summary.source?.partial ? ' - sebagian kolom belum tersedia' : ''}`
                : 'Belum ada data');
        renderGenericTable(summary);
    }

    // Render tabel generik dari definisi kolom + baris envelope backend.
    function renderGenericTable(summary) {
        const cols = summary.columns || [];

        // Pesan pengarah (mis. Tabel 8.c minta pilih prodi dulu).
        if (summary.notice) {
            els.tableHead.innerHTML = '';
            els.tableBody.innerHTML = `<tr><td class="sp-empty-row">${escapeHtml(summary.notice)}</td></tr>`;
            els.tableFoot.innerHTML = '';
            els.tablePagination.innerHTML = '';
            return;
        }

        // Kolom pertama (biasanya No/nama) di-pin lewat .sp-th-sticky /
        // .sp-td-sticky supaya identitas baris tetap kelihatan selagi geser
        // horizontal di layar sempit -- lihat komentar .table-scroll di
        // app.css untuk kenapa ini butuh background solid, bukan cuma
        // position:sticky saja.
        els.tableHead.innerHTML = `<tr>${cols.map((c, i) =>
            `<th class="sp-th ${c.align === 'num' ? 'sp-th-num' : ''} ${i === 0 ? 'sp-th-sticky' : ''}">${escapeHtml(c.label)}</th>`).join('')}</tr>`;

        if (!summary.rows.length) {
            els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="${cols.length || 1}">Belum ada data untuk kombinasi filter ini.</td></tr>`;
            els.tableFoot.innerHTML = '';
            els.tablePagination.innerHTML = '';
            return;
        }

        const filteredRows = filterGenericRows(summary.rows, cols, state.searchQuery);

        if (!filteredRows.length) {
            els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="${cols.length || 1}">Tidak ada baris yang cocok dengan pencarian "${escapeHtml(state.searchQuery)}".</td></tr>`;
            els.tableFoot.innerHTML = '';
            els.tablePagination.innerHTML = '';
            return;
        }

        const pageCount = Math.max(1, Math.ceil(filteredRows.length / PAGE_SIZE));
        state.page = Math.min(Math.max(state.page, 1), pageCount);
        const startIndex = (state.page - 1) * PAGE_SIZE;
        const pageRows = filteredRows.slice(startIndex, startIndex + PAGE_SIZE);

        els.tableBody.innerHTML = pageRows.map((row) =>
            `<tr class="sp-row">${cols.map((c, i) => genericCell(row, c, i === 0)).join('')}</tr>`).join('');
        els.tableFoot.innerHTML = '';
        renderPagination(filteredRows.length, GENERIC_TABLES[currentTableId]?.unit || 'baris');
    }

    function genericCell(row, col, isFirst) {
        const value = row[col.key];
        const sticky = isFirst ? ' sp-td-sticky' : '';

        if (col.align === 'num') {
            return `<td class="sp-td sp-td-num${sticky}">${value === null || value === undefined ? '-' : formatNumber(value)}</td>`;
        }

        const display = value === null || value === undefined || value === '' ? '-' : String(value);

        // Kolom non-angka isinya macam-macam: nomor urut, tanda "✓"/"-",
        // "Ya"/"Tidak", TAPI juga nama/deskripsi panjang. Tidak ada penanda
        // per kolom yang membedakan keduanya, jadi ditentukan dari nilai
        // aktualnya saat render -- pendek & tanpa spasi (nomor, ikon,
        // Ya/Tidak) dipusatkan biar rapi kayak kolom angka; teks yang
        // mengandung spasi (nama, deskripsi) hampir pasti kalimat/frasa,
        // dibiarkan rata kiri seperti semula.
        const isShortToken = display.length <= 6 && !display.includes(' ');

        return `<td class="sp-td${isShortToken ? ' sp-td-center' : ''}${sticky}">${escapeHtml(display)}</td>`;
    }

    // Pencarian tabel lokal (tidak ke server -- summary.rows sudah lengkap
    // di memori buat kebutuhan pagination, jadi filter teks cukup jalan di
    // situ juga). Query sudah lowercase dari pemanggil (lihat listener
    // #table-search/#ma-table-search).
    function filterGenericRows(rows, cols, query) {
        if (!query) {
            return rows;
        }

        return rows.filter((row) => cols.some((c) => {
            const value = row[c.key];

            if (value === null || value === undefined || value === '') {
                return false;
            }

            return String(c.align === 'num' ? formatNumber(value) : value).toLowerCase().includes(query);
        }));
    }

    // Tabel Dosen Pembimbing punya bentuk baris sendiri (bukan columns/rows
    // generik) -- dicari lewat kolom yang benar-benar ditampilkan sebagai
    // teks (nama, NIDN/NIDK/NIP, nomor urut), bukan seluruh angka bimbingan.
    function filterDosenRows(rows, query) {
        if (!query) {
            return rows;
        }

        return rows.filter((row) =>
            String(row.no ?? '').toLowerCase().includes(query) ||
            String(row.nama_dosen ?? '').toLowerCase().includes(query) ||
            String(row.identifier ?? '').toLowerCase().includes(query));
    }

    function renderTable(summary) {
        const y = summary.year_columns;
        els.tableHead.innerHTML = `
            <tr>
                <th rowspan="2" class="sp-th sp-th-sticky">No</th>
                <th rowspan="2" class="sp-th">Nama Dosen</th>
                <th rowspan="2" class="sp-th">NIDN/NIDK/NIP</th>
                <th class="sp-th sp-th-group" colspan="4">Jumlah Mahasiswa yang Dibimbing pada PS yang Diakreditasi</th>
                <th class="sp-th sp-th-group" colspan="4">Jumlah Mahasiswa yang Dibimbing pada PS Lain di PT</th>
                <th rowspan="2" class="sp-th sp-th-num">Rata-rata Jumlah Bimbingan di Semua PS</th>
            </tr>
            <tr>
                <th class="sp-th sp-th-num">TS-2 (${y.ts_2})</th>
                <th class="sp-th sp-th-num">TS-1 (${y.ts_1})</th>
                <th class="sp-th sp-th-num">TS (${y.ts})</th>
                <th class="sp-th sp-th-num">Rata-rata</th>
                <th class="sp-th sp-th-num">TS-2 (${y.ts_2})</th>
                <th class="sp-th sp-th-num">TS-1 (${y.ts_1})</th>
                <th class="sp-th sp-th-num">TS (${y.ts})</th>
                <th class="sp-th sp-th-num">Rata-rata</th>
            </tr>
        `;

        if (!summary.rows.length) {
            els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="12">Belum ada data dosen pembimbing utama tugas akhir untuk kombinasi filter ini.</td></tr>`;
            els.tableFoot.innerHTML = totalsRow(summary.totals, true);
            els.tablePagination.innerHTML = '';
            return;
        }

        const filteredRows = filterDosenRows(summary.rows, state.searchQuery);

        if (!filteredRows.length) {
            els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="12">Tidak ada dosen yang cocok dengan pencarian "${escapeHtml(state.searchQuery)}".</td></tr>`;
            els.tableFoot.innerHTML = totalsRow(summary.totals, false);
            els.tablePagination.innerHTML = '';
            return;
        }

        // Jaga halaman aktif tetap valid bila jumlah baris berubah.
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / PAGE_SIZE));
        state.page = Math.min(Math.max(state.page, 1), pageCount);
        const startIndex = (state.page - 1) * PAGE_SIZE;
        const pageRows = filteredRows.slice(startIndex, startIndex + PAGE_SIZE);

        els.tableBody.innerHTML = pageRows.map((row) => `
            <tr class="sp-row">
                <td class="sp-td sp-td-center sp-td-sticky">${row.no}</td>
                <td class="sp-td">${escapeHtml(row.nama_dosen || '-')}</td>
                <td class="sp-td sp-td-center">${escapeHtml(row.identifier || '-')}</td>
                ${yearButton(row, 'ps_diakreditasi', 'ts_2')}
                ${yearButton(row, 'ps_diakreditasi', 'ts_1')}
                ${yearButton(row, 'ps_diakreditasi', 'ts')}
                <td class="sp-td sp-td-num">${formatNumber(row.ps_diakreditasi.rata_rata)}</td>
                ${yearButton(row, 'ps_lain_pt', 'ts_2')}
                ${yearButton(row, 'ps_lain_pt', 'ts_1')}
                ${yearButton(row, 'ps_lain_pt', 'ts')}
                <td class="sp-td sp-td-num">${formatNumber(row.ps_lain_pt.rata_rata)}</td>
                <td class="sp-td sp-td-num">${formatNumber(row.rata_rata_semua_ps)}</td>
            </tr>
        `).join('');
        els.tableFoot.innerHTML = totalsRow(summary.totals, false);
        renderPagination(filteredRows.length, 'dosen');
    }

    // Deretan nomor halaman dengan pemadatan (…) bila halaman banyak.
    function pageWindow(current, count) {
        const wanted = new Set([1, count, current, current - 1, current + 1]);
        const pages = [...wanted].filter((p) => p >= 1 && p <= count).sort((a, b) => a - b);
        const out = [];
        let prev = 0;

        pages.forEach((p) => {
            if (p - prev > 1) {
                out.push('…');
            }
            out.push(p);
            prev = p;
        });

        return out;
    }

    function renderPagination(total, unit = 'dosen') {
        const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));
        const start = total === 0 ? 0 : (state.page - 1) * PAGE_SIZE + 1;
        const end = Math.min(state.page * PAGE_SIZE, total);

        // Satu halaman saja: cukup tampilkan ringkasan jumlah.
        if (pageCount <= 1) {
            els.tablePagination.innerHTML = `<span class="text-[12px] text-muted">Menampilkan ${total} ${unit}</span>`;
            return;
        }

        const navBtn = (label, page, disabled, active) => `
            <button type="button"
                    class="sp-page-btn ${active ? 'sp-page-btn-active' : ''}"
                    data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
        `;

        const numbers = pageWindow(state.page, pageCount).map((p) =>
            p === '…'
                ? '<span class="px-2 text-[12px] text-muted">…</span>'
                : navBtn(p, p, false, p === state.page)
        ).join('');

        els.tablePagination.innerHTML = `
            <span class="text-[12px] text-muted">Menampilkan ${start}–${end} dari ${total} ${unit}</span>
            <div class="flex items-center gap-1">
                ${navBtn('Sebelumnya', state.page - 1, state.page === 1, false)}
                ${numbers}
                ${navBtn('Berikutnya', state.page + 1, state.page === pageCount, false)}
            </div>
        `;
    }

    function goToPage(page) {
        const total = state.summary?.rows?.length || 0;
        const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));
        const target = Math.min(Math.max(page, 1), pageCount);

        if (target === state.page) {
            return;
        }

        state.page = target;

        if (isGeneric(currentTableId)) {
            renderGenericTable(state.summary);
        } else {
            renderTable(state.summary);
        }
    }

    function yearButton(row, scope, yearKey) {
        const value = row[scope][yearKey];

        return `
            <td class="sp-td sp-td-num">
                <button type="button" class="sp-number-chip" data-dosen-id="${row.dosen_id}" data-dosen-name="${escapeAttr(row.nama_dosen || '')}" data-scope="${scope}" data-year-key="${yearKey}">
                    ${formatNumber(value)}
                </button>
            </td>
        `;
    }

    function totalsRow(totals, empty) {
        const ps = totals?.ps_diakreditasi || { ts_2: 0, ts_1: 0, ts: 0, rata_rata: 0 };
        const psLain = totals?.ps_lain_pt || { ts_2: 0, ts_1: 0, ts: 0, rata_rata: 0 };
        const totalAverage = ((ps.ts_2 + ps.ts_1 + ps.ts + psLain.ts_2 + psLain.ts_1 + psLain.ts) / 3).toFixed(2);

        return `
            <tr>
                <td class="sp-foot-td sp-foot-td-sticky" colspan="3">${empty ? 'Ringkasan' : 'Total'}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(ps.ts_2)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(ps.ts_1)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(ps.ts)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(ps.rata_rata)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(psLain.ts_2)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(psLain.ts_1)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(psLain.ts)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(psLain.rata_rata)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totalAverage)}</td>
            </tr>
        `;
    }

    function renderError(message) {
        metricEls.forEach((el) => el.classList.remove('is-loading'));
        els.metricDosen.textContent = '0';
        els.metricPs.textContent = '0';
        els.metricPsLain.textContent = '0';
        els.metricTs.textContent = state.ts ?? '-';
        els.tableStatus.innerHTML = `<span class="font-medium text-warn">Perlu perhatian</span>`;
        els.tableHead.innerHTML = '';
        els.tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="p-4">
                    <div class="sp-alert-warn" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-warn" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                  stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div>
                            <div class="font-semibold">Data belum bisa ditampilkan</div>
                            <p class="mt-0.5 text-warn/90">${escapeHtml(message)}</p>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        els.tableFoot.innerHTML = '';
        els.tablePagination.innerHTML = '';
    }

    async function openDetail(button) {
        const params = new URLSearchParams({
            ts: state.ts,
            dosen_id: button.dataset.dosenId,
            scope: button.dataset.scope,
            year_key: button.dataset.yearKey,
        });

        if (state.canSelectFakultas && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.canSelectProgramStudi && state.programStudiId !== 'all') {
            params.set('program_studi_id', state.programStudiId);
        }

        els.detailTitle.textContent = button.dataset.dosenName || 'Detail Data';
        els.detailSubtitle.textContent = `${scopeLabel(button.dataset.scope)} - ${yearLabel(button.dataset.yearKey)}`;
        els.detailBody.innerHTML = '<div class="text-sm text-muted">Memuat detail...</div>';
        showDrawer();

        try {
            const payload = await fetchJson(`/api/sapto/tables/dosen-pembimbing-ta/detail?${params}`);
            renderDetail(payload);
        } catch (error) {
            els.detailBody.innerHTML = `<div class="text-sm text-warn">${escapeHtml(error.message)}</div>`;
        }
    }

    function renderDetail(payload) {
        if (!payload.items.length) {
            els.detailBody.innerHTML = '<div class="text-sm text-muted">Tidak ada detail data untuk kombinasi dosen, program studi, dan TS ini.</div>';
            return;
        }

        els.detailBody.innerHTML = payload.items.map((item) => `
            <article class="rounded-2xl p-3.5" style="background:var(--glass-2);border:1px solid var(--color-line)">
                <strong class="mb-1.5 block text-[14px] text-ink">${escapeHtml(item.nama_mahasiswa || '-')}</strong>
                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-[13px] text-muted">
                    <div><span class="block text-[11px] text-muted">NIM</span>${escapeHtml(item.nim || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Fakultas</span>${escapeHtml(item.fakultas || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Program Studi</span>${escapeHtml(item.program_studi || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Judul</span>${escapeHtml(item.judul_tugas_akhir || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Jenis</span>${escapeHtml(item.jenis_tugas_akhir || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Tahun</span>${escapeHtml(String(item.tahun || '-'))}</div>
                    <div><span class="block text-[11px] text-muted">Status</span>${escapeHtml(item.status || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Sumber Data</span>${escapeHtml(item.sumber_data || '-')}</div>
                </div>
            </article>
        `).join('');
    }

    // Admin melihat semua prodi yang punya mahasiswa asing (server tidak
    // discope), lalu disaring di klien lewat dropdown ma-*. Korprodi biasa:
    // server hanya mengembalikan satu prodi mereka, dropdown disembunyikan.
    const maCanSelect = () => state.canSelectFakultas || state.canSelectProgramStudi;

    async function loadMahasiswaAsing() {
        setMaLoading();

        try {
            const params = new URLSearchParams();
            if (state.maTs !== null) {
                params.set('ts', state.maTs);
            }

            const payload = await fetchJson(`/sapto/mahasiswa-asing?${params}`);

            if (!payload.success) {
                throw new Error(payload.message || 'Permintaan data gagal.');
            }

            maSummary = payload;
            state.maTs = payload.ts;
            populateTsOptions(els.maTs, payload.available_years, payload.ts);
            renderMaFilters();
            renderMaActiveUnit();
            renderMaSummary();
        } catch (error) {
            renderMaError(error.message);
        }
    }

    // Bangun dropdown Fakultas/Program Studi khusus halaman ini dari prodi
    // yang benar-benar punya mahasiswa asing (bukan katalog penuh) — supaya
    // admin tidak dibingungkan opsi prodi tanpa data.
    function renderMaFilters() {
        els.maFakultasWrap.classList.toggle('hidden', !maCanSelect());
        els.maProgramStudiWrap.classList.toggle('hidden', !maCanSelect());

        if (!maCanSelect()) {
            return;
        }

        const all = maSummary.data || [];

        const fakultas = [...new Map(all.map((r) => [r.fakultas_id, r.fakultas])).entries()]
            .map(([id, nama]) => ({ id, nama }))
            .sort((a, b) => a.nama.localeCompare(b.nama, 'id'));

        els.maFakultas.innerHTML = [
            '<option value="all">Semua Fakultas</option>',
            ...fakultas.map((f) => `<option value="${escapeAttr(f.id)}">${escapeHtml(f.nama)}</option>`),
        ].join('');
        els.maFakultas.value = maFilter.fakultasId;

        const prodi = all
            .filter((r) => maFilter.fakultasId === 'all' || r.fakultas_id === maFilter.fakultasId)
            .map((r) => ({ id: r.id_prodi, label: r.program_studi }))
            .sort((a, b) => a.label.localeCompare(b.label, 'id'));

        els.maProgramStudi.innerHTML = [
            '<option value="all">Semua Program Studi</option>',
            ...prodi.map((p) => `<option value="${escapeAttr(p.id)}">${escapeHtml(p.label)}</option>`),
        ].join('');
        els.maProgramStudi.value = maFilter.prodiId;
    }

    function maVisibleRows() {
        return (maSummary.data || []).filter((row) => {
            if (maFilter.fakultasId !== 'all' && row.fakultas_id !== maFilter.fakultasId) {
                return false;
            }

            if (maFilter.prodiId !== 'all' && row.id_prodi !== maFilter.prodiId) {
                return false;
            }

            if (maFilter.searchQuery && !String(row.program_studi ?? '').toLowerCase().includes(maFilter.searchQuery)) {
                return false;
            }

            return true;
        });
    }

    function setMaLoading() {
        maMetricEls.forEach((el) => el.classList.add('is-loading'));
        els.maTableStatus.textContent = 'Memuat data...';
        els.maTableHead.innerHTML = '';
        els.maTableBody.innerHTML = Array.from({ length: 6 }).map(() => `
            <tr>
                <td class="sp-skeleton-td" colspan="11"><div class="sp-skeleton-bar w-full max-w-md"></div></td>
            </tr>
        `).join('');
        els.maTableFoot.innerHTML = '';
    }

    function renderMaActiveUnit() {
        if (!maCanSelect()) {
            const row = (maSummary.data || [])[0];
            els.maActiveUnit.textContent = row ? row.program_studi : 'Program studi akun tidak ditemukan';
            return;
        }

        if (maFilter.prodiId !== 'all') {
            const row = (maSummary.data || []).find((r) => r.id_prodi === maFilter.prodiId);
            els.maActiveUnit.textContent = row ? row.program_studi : 'Universitas Sriwijaya';
            return;
        }

        if (maFilter.fakultasId !== 'all') {
            const row = (maSummary.data || []).find((r) => r.fakultas_id === maFilter.fakultasId);
            els.maActiveUnit.textContent = row ? row.fakultas : 'Universitas Sriwijaya';
            return;
        }

        els.maActiveUnit.textContent = 'Universitas Sriwijaya';
    }

    function renderMaSummary() {
        const rows = maVisibleRows();
        const totals = maRowTotals(rows);

        maMetricEls.forEach((el) => el.classList.remove('is-loading'));
        els.maMetricProdi.textContent = rows.length;
        els.maMetricAktif.textContent = formatNumber(totals.aktif.ts);
        els.maMetricFt.textContent = formatNumber(totals.ft.ts);
        els.maMetricPt.textContent = formatNumber(totals.pt.ts);
        els.maTableStatus.textContent = rows.length ? `${rows.length} program studi` : 'Belum ada data';

        renderMaTable(rows, totals);
    }

    function maRowTotals(rows) {
        const totals = {
            aktif: { ts_2: 0, ts_1: 0, ts: 0 },
            ft: { ts_2: 0, ts_1: 0, ts: 0 },
            pt: { ts_2: 0, ts_1: 0, ts: 0 },
        };

        rows.forEach((row) => {
            totals.aktif.ts_2 += Number(row.mhs_aktif_ts2 || 0);
            totals.aktif.ts_1 += Number(row.mhs_aktif_ts1 || 0);
            totals.aktif.ts += Number(row.mhs_aktif_ts || 0);
            totals.ft.ts_2 += Number(row.asing_ft_ts2 || 0);
            totals.ft.ts_1 += Number(row.asing_ft_ts1 || 0);
            totals.ft.ts += Number(row.asing_ft_ts || 0);
            totals.pt.ts_2 += Number(row.asing_pt_ts2 || 0);
            totals.pt.ts_1 += Number(row.asing_pt_ts1 || 0);
            totals.pt.ts += Number(row.asing_pt_ts || 0);
        });

        return totals;
    }

    function renderMaTable(rows, totals) {
        const y = maSummary.tahun_akademik || { ts_2: '-', ts_1: '-', ts: '-' };

        els.maTableHead.innerHTML = `
            <tr>
                <th rowspan="2" class="sp-th sp-th-sticky">No</th>
                <th rowspan="2" class="sp-th">Program Studi</th>
                <th class="sp-th sp-th-group" colspan="3">Jumlah Mahasiswa Aktif</th>
                <th class="sp-th sp-th-group" colspan="3">Jumlah Mahasiswa Asing Penuh Waktu (Full-time)</th>
                <th class="sp-th sp-th-group" colspan="3">Jumlah Mahasiswa Asing Paruh Waktu (Part-time)</th>
            </tr>
            <tr>
                <th class="sp-th sp-th-num">TS-2 (${y.ts_2})</th>
                <th class="sp-th sp-th-num">TS-1 (${y.ts_1})</th>
                <th class="sp-th sp-th-num">TS (${y.ts})</th>
                <th class="sp-th sp-th-num">TS-2 (${y.ts_2})</th>
                <th class="sp-th sp-th-num">TS-1 (${y.ts_1})</th>
                <th class="sp-th sp-th-num">TS (${y.ts})</th>
                <th class="sp-th sp-th-num">TS-2 (${y.ts_2})</th>
                <th class="sp-th sp-th-num">TS-1 (${y.ts_1})</th>
                <th class="sp-th sp-th-num">TS (${y.ts})</th>
            </tr>
        `;

        if (!rows.length) {
            const message = maFilter.searchQuery
                ? `Tidak ada program studi yang cocok dengan pencarian "${escapeHtml(maFilter.searchQuery)}".`
                : 'Belum ada data mahasiswa asing untuk periode ini.';
            els.maTableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="11">${message}</td></tr>`;
            els.maTableFoot.innerHTML = maTotalsRow(totals, true);
            return;
        }

        els.maTableBody.innerHTML = rows.map((row, index) => `
            <tr class="sp-row">
                <td class="sp-td sp-td-center sp-td-sticky">${index + 1}</td>
                <td class="sp-td">${escapeHtml(row.program_studi || '-')}</td>
                <td class="sp-td sp-td-num">${formatNumber(row.mhs_aktif_ts2)}</td>
                <td class="sp-td sp-td-num">${formatNumber(row.mhs_aktif_ts1)}</td>
                <td class="sp-td sp-td-num">${formatNumber(row.mhs_aktif_ts)}</td>
                ${maNumberChip(row, 'asing_ft_ts2', y.ts_2)}
                ${maNumberChip(row, 'asing_ft_ts1', y.ts_1)}
                ${maNumberChip(row, 'asing_ft_ts', y.ts)}
                <td class="sp-td sp-td-num text-muted" title="Data paruh waktu belum tersedia di sumber data saat ini">${formatNumber(row.asing_pt_ts2)}</td>
                <td class="sp-td sp-td-num text-muted" title="Data paruh waktu belum tersedia di sumber data saat ini">${formatNumber(row.asing_pt_ts1)}</td>
                <td class="sp-td sp-td-num text-muted" title="Data paruh waktu belum tersedia di sumber data saat ini">${formatNumber(row.asing_pt_ts)}</td>
            </tr>
        `).join('');
        els.maTableFoot.innerHTML = maTotalsRow(totals, false);
    }

    function maNumberChip(row, field, tahun) {
        return `
            <td class="sp-td sp-td-num">
                <button type="button" class="sp-number-chip" data-id-prodi="${escapeAttr(row.id_prodi || '')}" data-program-studi="${escapeAttr(row.program_studi || '')}" data-tahun="${tahun}">
                    ${formatNumber(row[field])}
                </button>
            </td>
        `;
    }

    function maTotalsRow(totals, empty) {
        return `
            <tr>
                <td class="sp-foot-td sp-foot-td-sticky" colspan="2">${empty ? 'Ringkasan' : 'Total'}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.aktif.ts_2)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.aktif.ts_1)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.aktif.ts)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.ft.ts_2)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.ft.ts_1)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.ft.ts)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.pt.ts_2)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.pt.ts_1)}</td>
                <td class="sp-foot-td sp-td-num">${formatNumber(totals.pt.ts)}</td>
            </tr>
        `;
    }

    function renderMaError(message) {
        maMetricEls.forEach((el) => el.classList.remove('is-loading'));
        maMetricEls.forEach((el) => { el.textContent = '0'; });
        els.maTableStatus.innerHTML = `<span class="font-medium text-warn">Perlu perhatian</span>`;
        els.maTableHead.innerHTML = '';
        els.maTableBody.innerHTML = `
            <tr>
                <td colspan="11" class="p-4">
                    <div class="sp-alert-warn" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-warn" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                  stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div>
                            <div class="font-semibold">Data belum bisa ditampilkan</div>
                            <p class="mt-0.5 text-warn/90">${escapeHtml(message)}</p>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        els.maTableFoot.innerHTML = '';
    }

    async function openMaDetail(button) {
        const params = new URLSearchParams({ tahun: button.dataset.tahun });

        // Prodi yang diklik. Server memvalidasi ini terhadap hak akses akun
        // (lihat SaptoUserContext::resolveProgramStudiId) — admin bebas pilih
        // prodi manapun, korprodi tetap dikunci ke prodinya sendiri.
        if (button.dataset.idProdi) {
            params.set('program_studi_id', button.dataset.idProdi);
        }

        const tahun = button.dataset.tahun;
        els.detailTitle.textContent = button.dataset.programStudi || 'Detail Data';
        els.detailSubtitle.textContent = `Mahasiswa asing aktif pada TS ${tahun}`;
        els.detailBody.innerHTML = '<div class="text-sm text-muted">Memuat detail...</div>';
        showDrawer();

        try {
            const payload = await fetchJson(`/sapto/mahasiswa-asing/detail?${params}`);

            if (!payload.success) {
                throw new Error(payload.message || 'Permintaan detail gagal.');
            }

            renderMaDetail(payload, tahun);
        } catch (error) {
            els.detailBody.innerHTML = `<div class="text-sm text-warn">${escapeHtml(error.message)}</div>`;
        }
    }

    function renderMaDetail(payload, tahun) {
        const items = payload.data || [];

        if (!items.length) {
            els.detailBody.innerHTML = '<div class="text-sm text-muted">Tidak ada mahasiswa asing untuk program studi dan tahun ini.</div>';
            return;
        }

        // Perjelas bahwa ini "mahasiswa aktif pada tahun X" (stok, sesuai
        // BAN-PT) — jadi angkatan lama yang belum lulus wajar ikut muncul,
        // dan mahasiswa yang sama bisa tampil di beberapa TS. Bukan data ganda.
        const note = `
            <div class="mb-3 rounded-xl px-3 py-2 text-[12px] leading-relaxed text-muted" style="background:var(--glass-2);border:1px solid var(--color-line)">
                ${items.length} mahasiswa asing yang <strong>masih aktif pada tahun ${escapeHtml(String(tahun))}</strong> (termasuk angkatan sebelumnya yang belum lulus). Mahasiswa yang belum lulus akan tetap terhitung pada tahun-tahun berikutnya.
            </div>`;

        els.detailBody.innerHTML = note + items.map((item) => `
            <article class="rounded-2xl p-3.5" style="background:var(--glass-2);border:1px solid var(--color-line)">
                <strong class="mb-1.5 block text-[14px] text-ink">${escapeHtml(item.nama || '-')}</strong>
                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-[13px] text-muted">
                    <div><span class="block text-[11px] text-muted">NIM</span>${escapeHtml(item.nim || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Angkatan</span>${escapeHtml(String(item.angkatan || '-'))}</div>
                    <div class="col-span-2"><span class="block text-[11px] text-muted">Program Studi</span>${escapeHtml(item.program_studi || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Status</span>${escapeHtml(item.status || '-')}</div>
                    <div><span class="block text-[11px] text-muted">Kewarganegaraan</span>${escapeHtml(item.kewarganegaraan || '-')}</div>
                </div>
            </article>
        `).join('');
    }

    function showDrawer() {
        els.drawer.classList.add('is-open');
        els.drawerBackdrop.classList.add('is-open');
    }

    function closeDrawer() {
        els.drawer.classList.remove('is-open');
        els.drawerBackdrop.classList.remove('is-open');
    }

    // Kerjasama (Tabel 1) bersifat institusi — repository mengabaikan
    // fakultas/prodi (lihat SaptoKerjasamaRepository), jadi dropdown itu tak
    // menyaring apa-apa dan disembunyikan untuk tabel-tabel ini.
    const INSTITUTION_WIDE_TABLES = ['kerjasama-pendidikan', 'kerjasama-penelitian', 'kerjasama-pkm'];

    // Tabel generik: memakai kembali #table-page (filter, kartu metrik, panel,
    // pagination) tapi render dari envelope {columns, rows, metrics} backend.
    const GENERIC_TABLES = {
        'ipk-lulusan': {
            endpoint: 'ipk-lulusan',
            unit: 'tahun lulus',
            description: 'Rata-rata IPK lulusan (minimum/rata-rata/maksimum) beserta jumlah lulusan per tahun lulus (TS-2/TS-1/TS) dalam cakupan terpilih, mengikuti format Tabel 8.a BAN-PT.',
            icons: ['cap', 'users', 'chart', 'calendar'],
        },
        'dosen-tetap-pt': {
            endpoint: 'dosen-tetap-pt',
            unit: 'dosen',
            description: 'Daftar dosen tetap perguruan tinggi berdasarkan program studi homebase pada tahun ajaran terpilih (Tabel 3.a.1). Kolom mata kuliah dan kesesuaian kompetensi inti PS belum tersedia di sumber data.',
            icons: ['users', 'cap', 'cap', 'calendar'],
        },
        'dosen-tidak-tetap': {
            endpoint: 'dosen-tidak-tetap',
            unit: 'dosen',
            description: 'Daftar dosen tidak tetap: dosen yang ditugaskan mengajar di program studi terpilih tanpa prodi itu menjadi homebase-nya, pada tahun ajaran terpilih (Tabel 3.a.4). Kolom pendidikan/jabatan/sertifikat/mata kuliah menyusul saat data tersedia.',
            icons: ['users', 'users', 'users', 'calendar'],
        },
        'masa-studi': {
            endpoint: 'masa-studi',
            unit: 'angkatan',
            description: 'Matriks masa studi lulusan per angkatan (tahun masuk) untuk satu program studi: jumlah diterima, lulus di akhir tiap TS, dan rata-rata masa studi, mengikuti format Tabel 8.c BAN-PT.',
            icons: ['users', 'cap', 'chart', 'calendar'],
        },
        'waktu-tunggu': {
            endpoint: 'waktu-tunggu',
            unit: 'tahun lulus',
            description: 'Jumlah lulusan per tahun lulus (TS-4/TS-3/TS-2) untuk satu program studi, mengikuti format Tabel 8.d.1 BAN-PT -- kolomnya berbeda untuk prodi Diploma Tiga vs Sarjana; jenjang lain (S2/S3/Profesi) tidak punya varian tabel ini. Cuma "Jumlah Lulusan" yang terisi dari data lulusan; kolom "Terlacak", "Dipesan Sebelum Lulus", dan seluruh kategori waktu tunggu selalu 0 karena butuh data tracer study alumni yang belum ada sumbernya di sistem -- isi manual dari hasil tracer study terpisah.',
            icons: ['cap', 'users', 'swap', 'calendar'],
        },
        'prestasi-akademik': {
            endpoint: 'prestasi-akademik',
            unit: 'prestasi',
            description: 'Daftar prestasi akademik mahasiswa pada TS-2/TS-1/TS, mengikuti format Tabel 8.b.1 BAN-PT. Hanya jenis prestasi "Sains" (lomba esai/riset/debat ilmiah) -- Olahraga & Seni masuk Tabel 8.b.2. Kolom Tingkat (Lokal/Wilayah, Nasional, Internasional) dipetakan dari taksonomi sumber -- kategori "Lainnya" dan tingkat di luar Nasional/Internasional dianggap Lokal/Wilayah, perlu diverifikasi manual. Kolom "Prestasi yang Dicapai" dibentuk dari peringkat mentah sebagai "Juara {n}"; baris tanpa peringkat tercatat ditampilkan "-".',
            // Metriknya sama persis (Total, Internasional, Nasional, Wilayah/
            // Lokal) dengan tabel Kerjasama -> pakai set ikon yang sama biar
            // artinya konsisten se-aplikasi (globe=internasional, pin=lokal).
            icons: ['swap', 'globe', 'cap', 'pin'],
        },
        'prestasi-non-akademik': {
            endpoint: 'prestasi-non-akademik',
            unit: 'prestasi',
            description: 'Daftar prestasi non-akademik mahasiswa pada TS-2/TS-1/TS, mengikuti format Tabel 8.b.2 BAN-PT. Jenis prestasi "Olahraga" & "Seni", plus "Lain-lain" sebagai kategori residual (klaim akademik harus eksplisit "Sains" di Tabel 8.b.1) -- sample manual "Lain-lain" isinya campur, mis. ada Karya Tulis Ilmiah berdampingan dengan Finalis Bujang Gadis, perlu diverifikasi manual per baris. Kolom Tingkat & Prestasi yang Dicapai mengikuti aturan yang sama seperti Tabel 8.b.1.',
            icons: ['swap', 'globe', 'cap', 'pin'],
        },
        'kerjasama-pendidikan': {
            endpoint: 'kerjasama-pendidikan',
            unit: 'kerjasama',
            description: 'Kerjasama tridharma bidang pendidikan (MoU institusi + PKS pelaksana) yang masih berlaku pada tahun terpilih, mengikuti format Tabel 1 BAN-PT. Kolom Tingkat dan filter "judul pendidikan" hasil inferensi teks (perlu diverifikasi manual); kolom Manfaat bagi PS wajib diisi manual karena narasinya tidak tersimpan di sistem.',
            icons: ['swap', 'globe', 'cap', 'pin'],
        },
        'kerjasama-penelitian': {
            endpoint: 'kerjasama-penelitian',
            unit: 'kerjasama',
            description: 'Kerjasama tridharma bidang penelitian (MoU institusi + PKS pelaksana) yang masih berlaku pada tahun terpilih, mengikuti format Tabel 1 BAN-PT. Kolom Tingkat dan filter "judul penelitian" hasil inferensi teks (perlu diverifikasi manual); kolom Manfaat bagi PS wajib diisi manual karena narasinya tidak tersimpan di sistem.',
            icons: ['swap', 'globe', 'cap', 'pin'],
        },
        'kerjasama-pkm': {
            endpoint: 'kerjasama-pkm',
            unit: 'kerjasama',
            description: 'Kerjasama tridharma bidang pengabdian kepada masyarakat (MoU institusi + PKS pelaksana) yang masih berlaku pada tahun terpilih, mengikuti format Tabel 1 BAN-PT. Kolom Tingkat dan filter "judul PkM" hasil inferensi teks (perlu diverifikasi manual); kolom Manfaat bagi PS wajib diisi manual karena narasinya tidak tersimpan di sistem.',
            icons: ['swap', 'globe', 'cap', 'pin'],
        },
        'penelitian-dtps': {
            endpoint: 'penelitian-dtps',
            unit: 'kategori sumber pembiayaan',
            description: 'Jumlah judul penelitian dosen tetap program studi (DTPS) per sumber pembiayaan pada TS-2/TS-1/TS, mengikuti format Tabel 3.b.2 BAN-PT. Klasifikasi "Lembaga Luar Negeri" berbasis skema penelitian (heuristik teks, perlu diverifikasi manual); judul yang didanai lebih dari satu sumber dihitung di tiap kategori yang berlaku.',
            icons: ['book', 'book', 'book', 'calendar'],
        },
        'publikasi-ilmiah-dtps': {
            endpoint: 'publikasi-ilmiah-dtps',
            unit: 'jenis publikasi',
            description: 'Untuk program AKADEMIK (Sarjana/Magister/Doktor). Jumlah judul publikasi ilmiah dosen tetap program studi (DTPS) per jenis publikasi pada TS-2/TS-1/TS, mengikuti format Tabel 3.b.4 BAN-PT. DTPS didasarkan pada penugasan homebase terakhir dosen dari SISTER (sister_list_penugasan) yang masih aktif pada tahun pelaporan, disaring jenjang S1/S2/S3. Kategori "Seminar wilayah/lokal/PT" dan "Tulisan media massa wilayah/internasional" selalu 0 (tidak ada penanda cakupan di sumber data); "Tulisan media massa nasional" mengasumsikan semua tulisan koran/majalah populer berskala nasional -- perlu diverifikasi manual.',
            icons: ['book', 'book', 'book', 'calendar'],
        },
        'pagelaran-dtps': {
            endpoint: 'pagelaran-dtps',
            unit: 'jenis publikasi',
            description: 'Untuk program VOKASI (Diploma Tiga/Sarjana Terapan/Magister Terapan/Doktor Terapan). Baris 1-7 (jurnal & seminar) dihitung sama persis dengan Tabel 3.b.4 versi akademik; bedanya baris 8-10 adalah pagelaran/pameran/presentasi dalam forum, bukan tulisan di media massa -- karena itu koran/majalah populer tidak ikut dihitung di sini. DTPS didasarkan pada penugasan homebase terakhir dari SISTER (sister_list_penugasan) untuk jenjang D3/D4/Sarjana Terapan/Magister Terapan/Doktor Terapan. Ketiga baris pagelaran selalu 0 karena sumbernya belum ada di SISTER (tabel pembicara/orasi ilmiah/penghargaan masih kosong), begitu juga "Seminar wilayah/lokal/PT" -- isi manual.',
            icons: ['book', 'book', 'book', 'calendar'],
        },
    };

    function isGeneric(tableId) {
        return Object.prototype.hasOwnProperty.call(GENERIC_TABLES, tableId);
    }

    const TABLE_HANDLERS = {
        'dashboard': {
            description: 'Ringkasan angka kunci dari 5 tabel BAN-PT yang sudah tersedia datanya.',
            show() {
                els.dashboardPage.classList.remove('hidden');
                loadDashboard();
            },
        },
        'dosen-pembimbing-ta': {
            description: 'Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.',
            show() {
                els.tablePage.classList.remove('hidden');
                loadSummary();
            },
        },
        'mahasiswa-asing': {
            description: 'Rekapitulasi jumlah mahasiswa aktif dan mahasiswa asing (penuh waktu/paruh waktu) per program studi, mengikuti format Tabel 2.b BAN-PT.',
            show() {
                els.maPage.classList.remove('hidden');
                loadMahasiswaAsing();
            },
        },
    };

    // Tabel generik berbagi handler yang sama: tampilkan #table-page lalu muat.
    Object.keys(GENERIC_TABLES).forEach((tableId) => {
        TABLE_HANDLERS[tableId] = {
            description: GENERIC_TABLES[tableId].description,
            show() {
                els.tablePage.classList.remove('hidden');
                loadSummary();
            },
        };
    });

    function activateTable(tableId) {
        currentTableId = tableId;

        // Pencarian tabel lokal khusus tabel yang lagi dilihat -- pindah
        // tabel harus mulai bersih, bukan bawa query tabel sebelumnya.
        state.searchQuery = '';
        state.page = 1;
        if (els.tableSearch) {
            els.tableSearch.value = '';
        }

        document.querySelectorAll('.menu-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tableId === tableId);
        });
        window.saptoExpandMenuGroupFor?.(tableId);

        const table = tables.find((item) => item.id === tableId);
        els.pageTitle.textContent = table?.name || 'SAPTO';

        els.tablePage.classList.add('hidden');
        els.maPage.classList.add('hidden');
        els.placeholderPage.classList.add('hidden');
        els.dashboardPage.classList.add('hidden');

        // Filter bar dipakai bersama keluarga tabel "table-page"
        // (dosen-pembimbing-ta + tabel generik); mahasiswa-asing punya filter
        // sendiri, placeholder tidak butuh filter sama sekali.
        const usesSharedFilterBar = tableId === 'dosen-pembimbing-ta' || isGeneric(tableId);
        els.sharedFilterBar.classList.toggle('hidden', !usesSharedFilterBar);

        // Kerjasama (Tabel 1) bersifat institusi — datanya tidak per fakultas/
        // prodi, jadi dropdown itu tak berpengaruh apa-apa (bikin pengelola
        // bingung). Sembunyikan buat tabel ini dan kunci Unit Aktif ke tingkat
        // universitas; tabel lain tetap bisa discope oleh admin.
        const institutionWide = INSTITUTION_WIDE_TABLES.includes(tableId);
        if (state.canSelectFakultas) {
            els.fakultasWrap.classList.toggle('hidden', institutionWide);
        }
        if (state.canSelectProgramStudi) {
            els.programStudiWrap.classList.toggle('hidden', institutionWide);
        }
        if (usesSharedFilterBar) {
            els.activeUnit.textContent = institutionWide ? 'Universitas Sriwijaya' : activeUnitLabel(activeUnitFallback());
        }

        const handler = TABLE_HANDLERS[tableId];

        if (handler) {
            els.pageDescription.textContent = handler.description;
            handler.show();
        } else {
            els.pageDescription.textContent = 'Tahap pertama pengembangan SAPTO.';
            els.placeholderPage.classList.remove('hidden');
            els.placeholderTitle.textContent = table?.name || 'Tabel SAPTO';
        }
    }

    function sumYearCounts(counts) {
        if (!counts) {
            return 0;
        }

        return Number(counts.ts_2 || 0) + Number(counts.ts_1 || 0) + Number(counts.ts || 0);
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function scopeLabel(scope) {
        return scope === 'ps_lain_pt' ? 'PS Lain di PT' : 'PS yang Diakreditasi';
    }

    function yearLabel(yearKey) {
        const columns = state.summary?.year_columns || {};
        const label = yearKey === 'ts_2' ? 'TS-2' : (yearKey === 'ts_1' ? 'TS-1' : 'TS');

        return `${label} ${columns[yearKey] || ''}`.trim();
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replaceAll('`', '&#096;');
    }

    // ---- Export tabel ("Export Excel") ----
    //
    // Bukan file .xlsx asli -- CSV dipilih dengan sengaja: satu-satunya
    // library JS buat menulis .xlsx (paket `xlsx` di npm, SheetJS) punya 2
    // kerentanan severity tinggi yang tidak ada perbaikannya (prototype
    // pollution + ReDoS -- SheetJS sudah berhenti merilis versi terbaru ke
    // npm). CSV tidak butuh dependency sama sekali dan Excel membukanya
    // langsung dengan sekali klik. Baris "sep=," di awal file + BOM UTF-8
    // memastikan Excel selalu mem-parsing delimiter dan karakter non-ASCII
    // (mis. tanda "✓") dengan benar, terlepas dari locale sistem operasi
    // user (beberapa region default ke titik koma sebagai delimiter).
    //
    // Selalu mengekspor SEMUA baris yang cocok dengan pencarian aktif, bukan
    // cuma yang lagi tampil di halaman pagination saat ini -- itulah alasan
    // export ini ada (butuh datanya buat berkas akreditasi, bukan cuma versi
    // 12-baris yang kelihatan di layar).
    function csvCell(value) {
        const s = value === null || value === undefined ? '' : String(value);

        return /[",\r\n]/.test(s) ? '"' + s.replaceAll('"', '""') + '"' : s;
    }

    function downloadCsv(filename, rows) {
        const body = rows.map((row) => row.map(csvCell).join(',')).join('\r\n');
        const bom = String.fromCharCode(0xFEFF); // BOM eksplisit lewat code point, bukan karakter tak terlihat di source
        const blob = new Blob([bom, 'sep=,\r\n', body], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');

        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function exportFilenameFor(tableId) {
        const table = tables.find((item) => item.id === tableId);
        const label = (table?.name || tableId).replace(/[\\/:*?"<>|]/g, '-');

        return `SAPTO - ${label} - ${new Date().toISOString().slice(0, 10)}.csv`;
    }

    function exportGenericRows(summary, cols) {
        const filteredRows = filterGenericRows(summary.rows || [], cols, state.searchQuery);
        const header = cols.map((c) => c.label);
        const body = filteredRows.map((row) => cols.map((c) => {
            const value = row[c.key];

            if (value === null || value === undefined || value === '') {
                return '-';
            }

            return c.align === 'num' ? Number(value) : value;
        }));

        return [header, ...body];
    }

    function exportDosenRows(summary) {
        const y = summary.year_columns || {};
        const header = [
            'No', 'Nama Dosen', 'NIDN/NIDK/NIP',
            `PS Diakreditasi - TS-2 (${y.ts_2 ?? ''})`, `PS Diakreditasi - TS-1 (${y.ts_1 ?? ''})`, `PS Diakreditasi - TS (${y.ts ?? ''})`, 'PS Diakreditasi - Rata-rata',
            `PS Lain - TS-2 (${y.ts_2 ?? ''})`, `PS Lain - TS-1 (${y.ts_1 ?? ''})`, `PS Lain - TS (${y.ts ?? ''})`, 'PS Lain - Rata-rata',
            'Rata-rata Semua PS',
        ];
        const filteredRows = filterDosenRows(summary.rows || [], state.searchQuery);
        const body = filteredRows.map((row) => [
            row.no, row.nama_dosen || '-', row.identifier || '-',
            row.ps_diakreditasi.ts_2, row.ps_diakreditasi.ts_1, row.ps_diakreditasi.ts, row.ps_diakreditasi.rata_rata,
            row.ps_lain_pt.ts_2, row.ps_lain_pt.ts_1, row.ps_lain_pt.ts, row.ps_lain_pt.rata_rata,
            row.rata_rata_semua_ps,
        ]);

        const totals = summary.totals || {};
        const ps = totals.ps_diakreditasi || { ts_2: 0, ts_1: 0, ts: 0, rata_rata: 0 };
        const psLain = totals.ps_lain_pt || { ts_2: 0, ts_1: 0, ts: 0, rata_rata: 0 };
        const totalAverage = ((ps.ts_2 + ps.ts_1 + ps.ts + psLain.ts_2 + psLain.ts_1 + psLain.ts) / 3).toFixed(2);
        const totalsLine = ['Total', '', '', ps.ts_2, ps.ts_1, ps.ts, ps.rata_rata, psLain.ts_2, psLain.ts_1, psLain.ts, psLain.rata_rata, totalAverage];

        return [header, ...body, totalsLine];
    }

    function exportMaRows() {
        const y = maSummary?.tahun_akademik || { ts_2: '-', ts_1: '-', ts: '-' };
        const header = [
            'No', 'Program Studi',
            `Aktif TS-2 (${y.ts_2})`, `Aktif TS-1 (${y.ts_1})`, `Aktif TS (${y.ts})`,
            `Penuh Waktu TS-2 (${y.ts_2})`, `Penuh Waktu TS-1 (${y.ts_1})`, `Penuh Waktu TS (${y.ts})`,
            `Paruh Waktu TS-2 (${y.ts_2})`, `Paruh Waktu TS-1 (${y.ts_1})`, `Paruh Waktu TS (${y.ts})`,
        ];
        const rows = maVisibleRows();
        const body = rows.map((row, index) => [
            index + 1, row.program_studi || '-',
            row.mhs_aktif_ts2, row.mhs_aktif_ts1, row.mhs_aktif_ts,
            row.asing_ft_ts2, row.asing_ft_ts1, row.asing_ft_ts,
            row.asing_pt_ts2, row.asing_pt_ts1, row.asing_pt_ts,
        ]);

        const totals = maRowTotals(rows);
        const totalsLine = [
            'Total', '',
            totals.aktif.ts_2, totals.aktif.ts_1, totals.aktif.ts,
            totals.ft.ts_2, totals.ft.ts_1, totals.ft.ts,
            totals.pt.ts_2, totals.pt.ts_1, totals.pt.ts,
        ];

        return [header, ...body, totalsLine];
    }

    function dashboardUrl() {
        const params = new URLSearchParams();

        if (state.ts !== null) {
            params.set('ts', state.ts);
        }

        if (state.canSelectFakultas && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.canSelectProgramStudi && state.programStudiId !== 'all') {
            params.set('program_studi_id', state.programStudiId);
        }

        return `/api/sapto/dashboard/summary?${params}`;
    }

    async function loadDashboard() {
        setDashboardLoading();

        try {
            const payload = await fetchJson(dashboardUrl());
            renderDashboard(payload);
        } catch (error) {
            renderDashboardError(error.message);
        }
    }

    // Filter bar is shared antara dashboard & table-page; dispatch ke loader
    // yang sesuai halaman aktif alih-alih selalu memanggil loadSummary().
    function loadActivePage() {
        if (currentTableId === 'dashboard') return loadDashboard();
        if (currentTableId === 'mahasiswa-asing') return loadMahasiswaAsing();

        return loadSummary();
    }

    function setDashboardLoading() {
        els.dashboardStatus.textContent = 'Memuat ringkasan...';

        els.dashboardCards.forEach((card) => {
            card.querySelector('.dashboard-card-metrics').innerHTML = Array.from({ length: 3 }).map(() => `
                <div class="dashboard-metric">
                    <div class="dashboard-metric-value is-loading">0</div>
                    <div class="dashboard-metric-label">&nbsp;</div>
                </div>
            `).join('');
            card.querySelector('.dashboard-card-trend').innerHTML = '';
        });
    }

    function renderDashboard(payload) {
        if (payload.ts !== null && payload.ts !== undefined) {
            state.ts = payload.ts;
        }

        populateTsOptions(els.ts, payload.available_years, payload.ts);

        els.dashboardStatus.textContent = '';

        const cardsById = new Map((payload.cards || []).map((card) => [card.id, card]));

        els.dashboardCards.forEach((el) => {
            const card = cardsById.get(el.dataset.cardId);

            if (card) {
                renderDashboardCard(el, card);
            }
        });
    }

    function renderDashboardCard(el, card) {
        el.querySelector('.dashboard-card-title').textContent = card.title;
        const metricsEl = el.querySelector('.dashboard-card-metrics');
        const trendEl = el.querySelector('.dashboard-card-trend');

        if (card.error) {
            metricsEl.innerHTML = '<p class="dashboard-card-notice dashboard-card-notice-error">Data belum bisa ditampilkan.</p>';
            trendEl.innerHTML = '';
            return;
        }

        if (card.notice) {
            metricsEl.innerHTML = `<p class="dashboard-card-notice">${escapeHtml(card.notice)}</p>`;
            trendEl.innerHTML = '';
            return;
        }

        metricsEl.innerHTML = card.metrics.map((metric) => `
            <div class="dashboard-metric">
                <div class="dashboard-metric-value">${formatNumber(metric.value)}</div>
                <div class="dashboard-metric-label">${escapeHtml(metric.label)}</div>
            </div>
        `).join('');

        trendEl.innerHTML = card.trend ? dashboardSparkline(card.trend) : '';
    }

    function dashboardSparkline(trend) {
        const values = trend.values.map(Number);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min || 1;
        const width = 120;
        const height = 32;
        const step = width / (values.length - 1 || 1);

        const points = values
            .map((value, index) => `${(index * step).toFixed(1)},${(height - ((value - min) / range) * height).toFixed(1)}`)
            .join(' ');

        return `
            <svg class="dashboard-sparkline" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true">
                <polyline points="${points}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div class="dashboard-sparkline-labels">
                ${trend.labels.map((label) => `<span>${escapeHtml(label)}</span>`).join('')}
            </div>
        `;
    }

    function renderDashboardError(message) {
        els.dashboardStatus.innerHTML = `<span class="font-medium text-warn">${escapeHtml(message)}</span>`;
    }

    els.dashboardCards.forEach((card) => {
        card.addEventListener('click', () => activateTable(card.dataset.tableId));
    });

    els.ts.addEventListener('change', () => {
        state.ts = Number(els.ts.value);
        loadActivePage();
    });

    els.maTs.addEventListener('change', () => {
        state.maTs = Number(els.maTs.value);
        loadMahasiswaAsing();
    });

    els.fakultas.addEventListener('change', async () => {
        state.fakultasId = els.fakultas.value;
        state.programStudiId = 'all';
        await loadProgramStudi();
        await loadActivePage();
    });

    els.programStudi.addEventListener('change', () => {
        state.programStudiId = els.programStudi.value;
        renderProgramStudiSelect();
        loadActivePage();
    });

    // Data Mahasiswa Asing sudah lengkap di klien (maSummary tak discope untuk
    // admin), jadi ganti fakultas/prodi cukup filter ulang — tanpa refetch.
    els.maFakultas.addEventListener('change', () => {
        maFilter.fakultasId = els.maFakultas.value;
        maFilter.prodiId = 'all';
        renderMaFilters();
        renderMaActiveUnit();
        renderMaSummary();
    });

    els.maProgramStudi.addEventListener('change', () => {
        maFilter.prodiId = els.maProgramStudi.value;
        renderMaActiveUnit();
        renderMaSummary();
    });

    els.tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.sp-number-chip');

        if (button) {
            openDetail(button);
        }
    });

    els.tablePagination.addEventListener('click', (event) => {
        const button = event.target.closest('.sp-page-btn');

        if (button && !button.disabled) {
            goToPage(Number(button.dataset.page));
        }
    });

    els.maTableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.sp-number-chip');

        if (button) {
            openMaDetail(button);
        }
    });

    document.querySelectorAll('.menu-item').forEach((button) => {
        button.addEventListener('click', () => activateTable(button.dataset.tableId));
    });

    els.closeDrawer.addEventListener('click', closeDrawer);
    els.drawerBackdrop.addEventListener('click', closeDrawer);

    function toggleSidebar() {
        els.sidebar.classList.toggle('is-open');
        els.sidebarBackdrop.classList.toggle('is-open');
        els.appShell.classList.toggle('sidebar-closed');
        els.sidebarToggle.setAttribute('aria-expanded', String(els.sidebar.classList.contains('is-open') || !els.appShell.classList.contains('sidebar-closed')));
    }

    function closeSidebarOffCanvas() {
        els.sidebar.classList.remove('is-open');
        els.sidebarBackdrop.classList.remove('is-open');
    }

    els.sidebarToggle.addEventListener('click', toggleSidebar);
    els.sidebarClose.addEventListener('click', closeSidebarOffCanvas);
    els.sidebarBackdrop.addEventListener('click', closeSidebarOffCanvas);

    els.menuSearch.addEventListener('input', () => {
        const query = els.menuSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        els.menuGroups.forEach((group) => {
            let groupHasMatch = false;

            group.querySelectorAll('.menu-item').forEach((button) => {
                const matches = !query || button.dataset.search.includes(query);
                button.classList.toggle('hidden', !matches);

                if (matches) {
                    groupHasMatch = true;
                    visibleCount += 1;
                }
            });

            group.classList.toggle('hidden', !groupHasMatch);

            // Dropdown groups (mis. Tabel 1) harus terbuka otomatis saat
            // pencarian aktif, kalau tidak hasil yang cocok tersembunyi di
            // balik panel yang collapsed.
            if (query && groupHasMatch && group.classList.contains('menu-group-dropdown')) {
                group.classList.remove('is-collapsed');
                group.querySelector('[data-group-toggle]')?.setAttribute('aria-expanded', 'true');
            }
        });

        els.menuEmpty.classList.toggle('hidden', visibleCount > 0);
    });

    // Pencarian di dalam tabel aktif (bukan pencarian menu sidebar di atas).
    // Data tabel sudah lengkap di memori (state.summary.rows) buat kebutuhan
    // pagination, jadi filter cukup di klien -- tidak perlu round-trip ke
    // server tiap ketikan.
    els.tableSearch?.addEventListener('input', () => {
        state.searchQuery = els.tableSearch.value.trim().toLowerCase();
        state.page = 1;

        if (!state.summary) {
            return;
        }

        if (isGeneric(currentTableId)) {
            renderGenericTable(state.summary);
        } else {
            renderTable(state.summary);
        }
    });

    els.maTableSearch?.addEventListener('input', () => {
        maFilter.searchQuery = els.maTableSearch.value.trim().toLowerCase();

        if (maSummary) {
            renderMaSummary();
        }
    });

    els.tableExport?.addEventListener('click', () => {
        if (!state.summary) {
            return;
        }

        const rows = isGeneric(currentTableId)
            ? exportGenericRows(state.summary, state.summary.columns || [])
            : exportDosenRows(state.summary);

        downloadCsv(exportFilenameFor(currentTableId), rows);
    });

    els.maTableExport?.addEventListener('click', () => {
        if (!maSummary) {
            return;
        }

        downloadCsv(exportFilenameFor('mahasiswa-asing'), exportMaRows());
    });

    // Toggle layar penuh untuk seluruh halaman (pakai Fullscreen API bawaan
    // browser pada <html>) -- satu tombol di topbar, bukan diulang per kartu
    // tabel, karena targetnya memang seluruh situs.
    const fullscreenToggle = document.getElementById('fullscreen-toggle');
    const fullscreenToggleIcon = document.getElementById('fullscreen-toggle-icon');
    const FULLSCREEN_ICON_D = 'M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3';
    const EXIT_FULLSCREEN_ICON_D = 'M9 3v4a1 1 0 0 1-1 1H4m16 0h-4a1 1 0 0 1-1-1V3m0 18v-4a1 1 0 0 1 1-1h4M4 15h4a1 1 0 0 1 1 1v4';

    if (fullscreenToggle && fullscreenToggleIcon && document.fullscreenEnabled) {
        fullscreenToggle.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        });

        document.addEventListener('fullscreenchange', () => {
            const isActive = Boolean(document.fullscreenElement);

            fullscreenToggleIcon.setAttribute('d', isActive ? EXIT_FULLSCREEN_ICON_D : FULLSCREEN_ICON_D);
            fullscreenToggle.title = isActive ? 'Keluar dari layar penuh' : 'Perbesar situs ke layar penuh';
        });
    } else {
        fullscreenToggle?.remove();
    }

    // Shortcut "/" fokus ke pencarian menu (kecuali sedang mengetik di field lain).
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        const tag = document.activeElement?.tagName;

        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || document.activeElement?.isContentEditable) {
            return;
        }

        event.preventDefault();
        els.menuSearch.focus();
    });

    // Toggle mode terang/gelap. data-theme selalu di-set (lihat skrip anti-flash
    // di <head>), jadi ikon matahari/bulan selalu cocok. Pilihan disimpan di
    // localStorage supaya bertahan antar kunjungan.
    const themeToggle = document.getElementById('theme-toggle');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            const meta = document.getElementById('theme-color-meta');
            if (meta) meta.setAttribute('content', next === 'dark' ? '#0a0a0c' : '#e7ecf5');
            try { localStorage.setItem('sapto-theme', next); } catch (e) {}
        });
    }

    initTsOptions();

    // Tiga tahap ini dimuat terpisah, bukan sebagai satu rantai .then().
    // Sebelumnya satu request gagal/lambat (mis. saat cache dingin, tabel
    // berat bisa belasan detik lalu kena timeout proxy) membatalkan sisa
    // rantai sekaligus: dropdown fakultas tidak pernah di-unhide DAN tabel
    // tidak pernah dimuat, sehingga halaman tampak "semua datanya hilang".
    // Sekarang tiap tahap gagal sendiri-sendiri dan yang lain tetap jalan.
    (async () => {
        try {
            await loadFakultas();
        } catch (error) {
            console.error('Gagal memuat daftar fakultas:', error);
        }

        try {
            await loadProgramStudi();
        } catch (error) {
            console.error('Gagal memuat daftar program studi:', error);
        }

        try {
            await activateTable(currentTableId);
        } catch (error) {
            renderError(error.message);
        }
    })();
</script>
@endsection
