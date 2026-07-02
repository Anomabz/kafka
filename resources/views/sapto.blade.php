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
    <section id="table-page">
        @include('components.filter-bar')

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @include('components.metric-card', ['id' => 'metric-dosen', 'label' => 'Total dosen pembimbing', 'icon' => $icons['users'], 'tint' => 'accent'])
            @include('components.metric-card', ['id' => 'metric-ps', 'label' => 'Total mahasiswa bimbingan PS diakreditasi', 'icon' => $icons['cap'], 'tint' => 'info'])
            @include('components.metric-card', ['id' => 'metric-ps-lain', 'label' => 'Total mahasiswa bimbingan PS lain di PT', 'icon' => $icons['swap'], 'tint' => 'warn'])
            @include('components.metric-card', ['id' => 'metric-ts', 'label' => 'TS aktif', 'icon' => $icons['calendar'], 'tint' => 'slate'])
        </div>

        @include('components.table-panel')
    </section>

    <section id="mahasiswa-asing-page" class="hidden">
        @include('components.mahasiswa-asing-filter')

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @include('components.metric-card', ['id' => 'ma-metric-prodi', 'label' => 'Total program studi', 'icon' => $icons['cap'], 'tint' => 'accent'])
            @include('components.metric-card', ['id' => 'ma-metric-aktif', 'label' => 'Total mahasiswa asing aktif (TS)', 'icon' => $icons['users'], 'tint' => 'info'])
            @include('components.metric-card', ['id' => 'ma-metric-ft', 'label' => 'Mahasiswa asing penuh waktu (TS)', 'icon' => $icons['globe'], 'tint' => 'warn'])
            @include('components.metric-card', ['id' => 'ma-metric-pt', 'label' => 'Mahasiswa asing paruh waktu (TS)', 'icon' => $icons['calendar'], 'tint' => 'slate'])
        </div>

        @include('components.mahasiswa-asing-panel')
    </section>

    <section id="placeholder-page" class="hidden rounded-xl border border-line bg-surface p-6">
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
    const state = {
        role: 'universitas',
        ts: currentYear,
        fakultasId: 'all',
        programStudiId: 'all',
        fakultas: [],
        programStudi: [],
        summary: null,
    };

    const els = {
        role: document.getElementById('role'),
        ts: document.getElementById('ts'),
        fakultas: document.getElementById('fakultas'),
        programStudi: document.getElementById('program-studi'),
        activeUnit: document.getElementById('active-unit'),
        tablePage: document.getElementById('table-page'),
        placeholderPage: document.getElementById('placeholder-page'),
        placeholderTitle: document.getElementById('placeholder-title'),
        pageTitle: document.getElementById('page-title'),
        pageDescription: document.getElementById('page-description'),
        tableStatus: document.getElementById('table-status'),
        tableHead: document.getElementById('table-head'),
        tableBody: document.getElementById('table-body'),
        tableFoot: document.getElementById('table-foot'),
        metricDosen: document.getElementById('metric-dosen'),
        metricPs: document.getElementById('metric-ps'),
        metricPsLain: document.getElementById('metric-ps-lain'),
        metricTs: document.getElementById('metric-ts'),
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
        maTableHead: document.getElementById('ma-table-head'),
        maTableBody: document.getElementById('ma-table-body'),
        maTableFoot: document.getElementById('ma-table-foot'),
        maMetricProdi: document.getElementById('ma-metric-prodi'),
        maMetricAktif: document.getElementById('ma-metric-aktif'),
        maMetricFt: document.getElementById('ma-metric-ft'),
        maMetricPt: document.getElementById('ma-metric-pt'),
        maFakultas: document.getElementById('ma-fakultas'),
        maFakultasWrap: document.getElementById('ma-fakultas-wrap'),
        maProgramStudi: document.getElementById('ma-program-studi'),
        maProdiWrap: document.getElementById('ma-prodi-wrap'),
        maActiveUnit: document.getElementById('ma-active-unit'),
    };

    const metricEls = [els.metricDosen, els.metricPs, els.metricPsLain, els.metricTs];
    const maMetricEls = [els.maMetricProdi, els.maMetricAktif, els.maMetricFt, els.maMetricPt];
    let maSummary = null;
    let currentTableId = @json($activeTableId ?? 'dosen-pembimbing-ta');

    // Filter mahasiswa asing (role diambil dari state.role yang dibagi topbar).
    const maFilter = {
        fakultasId: 'all',   // 'all' | fakultas_id
        prodiId: 'all',      // 'all' | id_prodi
        fakultas: [],        // [{ id, nama }]
    };

    function initTsOptions() {
        const years = [];

        for (let year = currentYear + 1; year >= currentYear - 6; year--) {
            years.push(year);
        }

        els.ts.innerHTML = years.map((year) => `<option value="${year}">${year}</option>`).join('');
        els.ts.value = String(state.ts);
    }

    function contextHeaders() {
        const headers = {
            Accept: 'application/json',
            'X-SAPTO-Role': state.role,
        };

        if (state.role === 'fakultas') {
            const fakultasId = selectedFakultasId();

            if (fakultasId && fakultasId !== 'all') {
                headers['X-SAPTO-Fakultas-Id'] = fakultasId;
            }
        }

        if (state.role === 'prodi') {
            const prodiId = selectedProgramStudiId();

            if (prodiId && prodiId !== 'all') {
                headers['X-SAPTO-Program-Studi-Id'] = prodiId;
            }
        }

        return headers;
    }

    function selectedFakultasId() {
        if (state.role === 'fakultas' && state.fakultasId === 'all') {
            return state.fakultas[0]?.id ? String(state.fakultas[0].id) : 'all';
        }

        if (state.role === 'prodi') {
            const selected = state.programStudi.find((item) => String(item.id) === String(selectedProgramStudiId()));

            return selected?.fakultas_id ? String(selected.fakultas_id) : state.fakultasId;
        }

        return state.fakultasId;
    }

    function selectedProgramStudiId() {
        if (state.role === 'prodi' && state.programStudiId === 'all') {
            return state.programStudi[0]?.id ? String(state.programStudi[0].id) : 'all';
        }

        return state.programStudiId;
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: contextHeaders() });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Permintaan data gagal.');
        }

        return payload;
    }

    async function loadFakultas() {
        const payload = await fetchJson('/api/sapto/fakultas');
        state.fakultas = payload.data || [];

        if (state.role === 'fakultas' && state.fakultas.length > 0) {
            state.fakultasId = String(state.fakultas[0].id);
        }

        if (state.role === 'universitas' && state.fakultasId !== 'all') {
            const exists = state.fakultas.some((item) => String(item.id) === String(state.fakultasId));

            if (!exists) {
                state.fakultasId = 'all';
            }
        }

        renderFakultasSelect();
    }

    async function loadProgramStudi() {
        const params = new URLSearchParams();

        if (state.role === 'universitas' && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        const payload = await fetchJson(`/api/sapto/program-studi${params.size ? `?${params}` : ''}`);
        state.programStudi = payload.data || [];

        if (state.role === 'prodi' && state.programStudi.length > 0) {
            state.programStudiId = String(state.programStudi[0].id);
        }

        if (state.role !== 'prodi' && state.programStudiId !== 'all') {
            const exists = state.programStudi.some((item) => String(item.id) === String(state.programStudiId));

            if (!exists) {
                state.programStudiId = 'all';
            }
        }

        renderProgramStudiSelect();
    }

    function renderFakultasSelect() {
        if (state.role === 'universitas') {
            const options = [
                '<option value="all">Semua Fakultas</option>',
                ...state.fakultas.map((item) => `<option value="${item.id}">${escapeHtml(item.nama)}</option>`),
            ];

            els.fakultas.innerHTML = options.join('');
            els.fakultas.disabled = false;
            els.fakultas.value = state.fakultasId;
        } else {
            const selected = state.fakultas.find((item) => String(item.id) === String(selectedFakultasId()));
            els.fakultas.innerHTML = selected ? `<option value="${selected.id}">${escapeHtml(selected.nama)}</option>` : '<option value="">Fakultas akun tidak ditemukan</option>';
            els.fakultas.disabled = true;
        }
    }

    function renderProgramStudiSelect() {
        if (state.role !== 'prodi') {
            const options = [
                '<option value="all">Semua Program Studi</option>',
                ...state.programStudi.map((item) => `<option value="${item.id}">${escapeHtml(item.jenjang)} ${escapeHtml(item.nama)} - ${escapeHtml(item.fakultas)}</option>`),
            ];

            els.programStudi.innerHTML = options.join('');
            els.programStudi.disabled = false;
            els.programStudi.value = state.programStudiId;
        } else {
            const item = state.programStudi[0];
            els.programStudi.innerHTML = item ? `<option value="${item.id}">${escapeHtml(item.jenjang)} ${escapeHtml(item.nama)} - ${escapeHtml(item.fakultas)}</option>` : '<option value="">Program studi akun tidak ditemukan</option>';
            els.programStudi.disabled = true;
        }

        renderActiveUnit();
    }

    function renderActiveUnit() {
        const fakultas = state.fakultas.find((item) => String(item.id) === String(selectedFakultasId()));
        const selected = state.programStudi.find((item) => String(item.id) === String(selectedProgramStudiId()));

        if (selected) {
            els.activeUnit.textContent = `${selected.jenjang} ${selected.nama}`;
            return;
        }

        if (fakultas) {
            els.activeUnit.textContent = fakultas.nama;
            return;
        }

        els.activeUnit.textContent = 'Universitas Sriwijaya';
    }

    function summaryUrl() {
        const params = new URLSearchParams({ ts: state.ts });

        if (state.role === 'universitas' && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.role !== 'prodi' && state.programStudiId !== 'all') {
            params.set('program_studi_id', state.programStudiId);
        }

        return `/api/sapto/tables/dosen-pembimbing-ta/summary?${params}`;
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
    }

    function renderSummary() {
        const summary = state.summary;
        const rows = summary.rows || [];
        const totals = summary.totals || {};
        const psTotal = sumYearCounts(totals.ps_diakreditasi);
        const psLainTotal = sumYearCounts(totals.ps_lain_pt);

        metricEls.forEach((el) => el.classList.remove('is-loading'));
        els.metricDosen.textContent = rows.length;
        els.metricPs.textContent = psTotal;
        els.metricPsLain.textContent = psLainTotal;
        els.metricTs.textContent = summary.ts;
        els.tableStatus.textContent = rows.length
            ? `${rows.length} dosen${summary.source?.partial ? ' - data API parsial' : ''}`
            : 'Belum ada data';
        renderTable(summary);
    }

    function renderTable(summary) {
        const y = summary.year_columns;
        els.tableHead.innerHTML = `
            <tr>
                <th rowspan="2" class="sp-th">No</th>
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
            return;
        }

        els.tableBody.innerHTML = summary.rows.map((row) => `
            <tr class="sp-row">
                <td class="sp-td">${row.no}</td>
                <td class="sp-td">${escapeHtml(row.nama_dosen || '-')}</td>
                <td class="sp-td">${escapeHtml(row.identifier || '-')}</td>
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
                <td class="sp-foot-td" colspan="3">${empty ? 'Ringkasan' : 'Total'}</td>
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
        els.metricTs.textContent = state.ts;
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
    }

    async function openDetail(button) {
        const params = new URLSearchParams({
            ts: state.ts,
            dosen_id: button.dataset.dosenId,
            scope: button.dataset.scope,
            year_key: button.dataset.yearKey,
        });

        if (state.role === 'universitas' && state.fakultasId !== 'all') {
            params.set('fakultas_id', state.fakultasId);
        }

        if (state.role !== 'prodi' && state.programStudiId !== 'all') {
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
            <article class="rounded-xl border border-line p-3.5">
                <strong class="mb-1.5 block text-[14px] text-ink">${escapeHtml(item.nama_mahasiswa || '-')}</strong>
                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-[13px] text-slate-600">
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

    async function loadMahasiswaAsing() {
        setMaLoading();

        try {
            const payload = await fetchJson('/sapto/mahasiswa-asing');

            if (!payload.success) {
                throw new Error(payload.message || 'Permintaan data gagal.');
            }

            maSummary = payload;
            buildMaFilterData();
            applyMaRoleDefaults();
            renderMaFilters();
            renderMaSummary();
        } catch (error) {
            renderMaError(error.message);
        }
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

    // Fakultas unik dari baris yang punya mahasiswa asing, terurut nama.
    function buildMaFilterData() {
        const seen = new Map();

        (maSummary.data || []).forEach((row) => {
            if (row.fakultas_id && !seen.has(row.fakultas_id)) {
                seen.set(row.fakultas_id, { id: row.fakultas_id, nama: row.fakultas });
            }
        });

        maFilter.fakultas = [...seen.values()].sort((a, b) => a.nama.localeCompare(b.nama, 'id'));
    }

    // Prodi (id_prodi) yang tersedia untuk fakultas terpilih, atau semua.
    function maProdiOptions() {
        return (maSummary.data || [])
            .filter((row) => maFilter.fakultasId === 'all' || row.fakultas_id === maFilter.fakultasId)
            .map((row) => ({ id: row.id_prodi, nama: row.program_studi }));
    }

    // Selaraskan pilihan default dengan role aktif:
    // - universitas: bebas, default Semua/Semua.
    // - fakultas: wajib satu fakultas (default pertama), prodi Semua.
    // - prodi: wajib satu fakultas + satu prodi (default pertama).
    function applyMaRoleDefaults() {
        const role = state.role;

        if (role === 'universitas') {
            maFilter.fakultasId = 'all';
            maFilter.prodiId = 'all';
            return;
        }

        const fakultasValid = maFilter.fakultas.some((f) => f.id === maFilter.fakultasId);

        if (!fakultasValid) {
            maFilter.fakultasId = maFilter.fakultas[0]?.id ?? 'all';
        }

        if (role === 'fakultas') {
            maFilter.prodiId = 'all';
            return;
        }

        // role === 'prodi'
        const prodiList = maProdiOptions();
        const prodiValid = prodiList.some((p) => p.id === maFilter.prodiId);

        if (!prodiValid) {
            maFilter.prodiId = prodiList[0]?.id ?? 'all';
        }
    }

    function renderMaFilters() {
        const role = state.role;

        // Fakultas: role universitas boleh "Semua"; role lain wajib pilih satu.
        const fakultasOptions = [
            ...(role === 'universitas' ? ['<option value="all">Semua Fakultas</option>'] : []),
            ...maFilter.fakultas.map((f) => `<option value="${escapeAttr(f.id)}">${escapeHtml(f.nama)}</option>`),
        ];
        els.maFakultas.innerHTML = fakultasOptions.join('') || '<option value="all">Tidak ada fakultas</option>';
        els.maFakultas.value = maFilter.fakultasId;

        // Program studi: role prodi wajib pilih satu; selain itu boleh "Semua".
        const prodiList = maProdiOptions();
        const prodiOptions = [
            ...(role === 'prodi' ? [] : ['<option value="all">Semua Program Studi</option>']),
            ...prodiList.map((p) => `<option value="${escapeAttr(p.id)}">${escapeHtml(p.nama)}</option>`),
        ];
        els.maProgramStudi.innerHTML = prodiOptions.join('') || '<option value="all">Tidak ada program studi</option>';
        els.maProgramStudi.value = maFilter.prodiId;

        // Prodi hanya relevan saat menyaring per fakultas/prodi.
        els.maProdiWrap.classList.toggle('hidden', role === 'universitas' && maFilter.fakultasId === 'all');

        renderMaActiveUnit();
    }

    function renderMaActiveUnit() {
        if (maFilter.prodiId !== 'all') {
            const prodi = (maSummary.data || []).find((row) => row.id_prodi === maFilter.prodiId);
            els.maActiveUnit.textContent = prodi ? prodi.program_studi : 'Universitas Sriwijaya';
            return;
        }

        if (maFilter.fakultasId !== 'all') {
            const fakultas = maFilter.fakultas.find((f) => f.id === maFilter.fakultasId);
            els.maActiveUnit.textContent = fakultas ? fakultas.nama : 'Universitas Sriwijaya';
            return;
        }

        els.maActiveUnit.textContent = 'Universitas Sriwijaya';
    }

    function maVisibleRows() {
        return (maSummary.data || []).filter((row) => {
            if (maFilter.fakultasId !== 'all' && row.fakultas_id !== maFilter.fakultasId) {
                return false;
            }

            if (maFilter.prodiId !== 'all' && row.id_prodi !== maFilter.prodiId) {
                return false;
            }

            return true;
        });
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
                <th rowspan="2" class="sp-th">No</th>
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
            els.maTableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="11">Belum ada data mahasiswa asing untuk periode ini.</td></tr>`;
            els.maTableFoot.innerHTML = maTotalsRow(totals, true);
            return;
        }

        els.maTableBody.innerHTML = rows.map((row, index) => `
            <tr class="sp-row">
                <td class="sp-td">${index + 1}</td>
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
                <td class="sp-foot-td" colspan="2">${empty ? 'Ringkasan' : 'Total'}</td>
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

        if (button.dataset.idProdi) {
            params.set('id_prodi', button.dataset.idProdi);
        } else {
            params.set('nama_prodi', button.dataset.programStudi);
        }

        els.detailTitle.textContent = button.dataset.programStudi || 'Detail Data';
        els.detailSubtitle.textContent = `Mahasiswa Asing Penuh Waktu - TS ${button.dataset.tahun}`;
        els.detailBody.innerHTML = '<div class="text-sm text-muted">Memuat detail...</div>';
        showDrawer();

        try {
            const payload = await fetchJson(`/sapto/mahasiswa-asing/detail?${params}`);

            if (!payload.success) {
                throw new Error(payload.message || 'Permintaan detail gagal.');
            }

            renderMaDetail(payload);
        } catch (error) {
            els.detailBody.innerHTML = `<div class="text-sm text-warn">${escapeHtml(error.message)}</div>`;
        }
    }

    function renderMaDetail(payload) {
        const items = payload.data || [];

        if (!items.length) {
            els.detailBody.innerHTML = '<div class="text-sm text-muted">Tidak ada mahasiswa asing untuk program studi dan tahun ini.</div>';
            return;
        }

        els.detailBody.innerHTML = items.map((item) => `
            <article class="rounded-xl border border-line p-3.5">
                <strong class="mb-1.5 block text-[14px] text-ink">${escapeHtml(item.nama || '-')}</strong>
                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-[13px] text-slate-600">
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

    const TABLE_HANDLERS = {
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

    function activateTable(tableId) {
        currentTableId = tableId;

        document.querySelectorAll('.menu-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tableId === tableId);
        });

        const table = tables.find((item) => item.id === tableId);
        els.pageTitle.textContent = table?.name || 'SAPTO';

        els.tablePage.classList.add('hidden');
        els.maPage.classList.add('hidden');
        els.placeholderPage.classList.add('hidden');

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

    els.role.addEventListener('change', async () => {
        state.role = els.role.value;

        // Tabel Mahasiswa Asing punya filter role sendiri (data lokal, difilter
        // di klien) — tidak lewat API fakultas/prodi milik tabel dosen.
        if (currentTableId === 'mahasiswa-asing') {
            if (maSummary) {
                applyMaRoleDefaults();
                renderMaFilters();
                renderMaSummary();
            }
            return;
        }

        if (state.role === 'fakultas' && state.fakultasId === 'all' && state.fakultas[0]) {
            state.fakultasId = String(state.fakultas[0].id);
            state.programStudiId = 'all';
        }

        if (state.role === 'prodi' && state.programStudiId === 'all' && state.programStudi[0]) {
            state.programStudiId = String(state.programStudi[0].id);
        }

        await loadFakultas();
        await loadProgramStudi();
        await loadSummary();
    });

    els.maFakultas.addEventListener('change', () => {
        maFilter.fakultasId = els.maFakultas.value;

        // Ganti fakultas: reset prodi. Role prodi wajib punya prodi terpilih,
        // jadi jatuhkan ke prodi pertama di fakultas baru.
        if (state.role === 'prodi') {
            maFilter.prodiId = maProdiOptions()[0]?.id ?? 'all';
        } else {
            maFilter.prodiId = 'all';
        }

        renderMaFilters();
        renderMaSummary();
    });

    els.maProgramStudi.addEventListener('change', () => {
        maFilter.prodiId = els.maProgramStudi.value;
        renderMaActiveUnit();
        renderMaSummary();
    });

    els.ts.addEventListener('change', () => {
        state.ts = Number(els.ts.value);
        loadSummary();
    });

    els.fakultas.addEventListener('change', async () => {
        state.fakultasId = els.fakultas.value;
        state.programStudiId = 'all';
        renderFakultasSelect();
        await loadProgramStudi();
        await loadSummary();
    });

    els.programStudi.addEventListener('change', () => {
        state.programStudiId = els.programStudi.value;
        renderProgramStudiSelect();
        loadSummary();
    });

    els.tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.sp-number-chip');

        if (button) {
            openDetail(button);
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
        });

        els.menuEmpty.classList.toggle('hidden', visibleCount > 0);
    });

    initTsOptions();
    loadFakultas()
        .then(loadProgramStudi)
        .then(loadSummary)
        .catch((error) => renderError(error.message));
</script>
@endsection
