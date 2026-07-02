@extends('layouts.sapto')

@section('title', 'SAPTO')

@php
    $icons = [
        'users' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'cap' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>',
        'swap' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>',
        'calendar' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
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
    const activeTableId = 'dosen-pembimbing-ta';
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
        els.tableStatus.textContent = 'Memuat data...';
        els.tableHead.innerHTML = '';
        els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="12">Memuat data...</td></tr>`;
        els.tableFoot.innerHTML = '';
    }

    function renderSummary() {
        const summary = state.summary;
        const rows = summary.rows || [];
        const totals = summary.totals || {};
        const psTotal = sumYearCounts(totals.ps_diakreditasi);
        const psLainTotal = sumYearCounts(totals.ps_lain_pt);

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

    function showDrawer() {
        els.drawer.classList.add('is-open');
        els.drawerBackdrop.classList.add('is-open');
    }

    function closeDrawer() {
        els.drawer.classList.remove('is-open');
        els.drawerBackdrop.classList.remove('is-open');
    }

    function activateTable(tableId) {
        document.querySelectorAll('.menu-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tableId === tableId);
        });

        const table = tables.find((item) => item.id === tableId);
        els.pageTitle.textContent = table?.name || 'SAPTO';

        if (tableId === activeTableId) {
            els.pageDescription.textContent = 'Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.';
            els.tablePage.classList.remove('hidden');
            els.placeholderPage.classList.add('hidden');
            loadSummary();
        } else {
            els.pageDescription.textContent = 'Tahap pertama pengembangan SAPTO.';
            els.tablePage.classList.add('hidden');
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

    document.querySelectorAll('.menu-item').forEach((button) => {
        button.addEventListener('click', () => activateTable(button.dataset.tableId));
    });

    els.closeDrawer.addEventListener('click', closeDrawer);
    els.drawerBackdrop.addEventListener('click', closeDrawer);

    initTsOptions();
    loadFakultas()
        .then(loadProgramStudi)
        .then(loadSummary)
        .catch((error) => renderError(error.message));
</script>
@endsection
