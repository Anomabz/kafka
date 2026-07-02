<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SAPTO</title>
    <style>
        :root {
            --ink: #17202a;
            --muted: #667085;
            --line: #d7dde5;
            --canvas: #f5f7fa;
            --panel: #ffffff;
            --nav: #102033;
            --nav-soft: #1d334d;
            --accent: #087f5b;
            --accent-soft: #e6f4ef;
            --amber: #b7791f;
            --danger: #b42318;
            --focus: #2f80ed;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--canvas);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            letter-spacing: 0;
        }

        button,
        select {
            font: inherit;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 304px minmax(0, 1fr);
        }

        .sidebar {
            min-height: 100vh;
            color: #eef4fb;
            background: var(--nav);
            display: flex;
            flex-direction: column;
        }

        .brand {
            min-height: 76px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: white;
            background: linear-gradient(135deg, #087f5b 0%, #2f80ed 100%);
            font-weight: 800;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 760;
            line-height: 1.1;
        }

        .brand-subtitle {
            margin-top: 3px;
            color: #b7c7d9;
            font-size: 12px;
        }

        .menu {
            padding: 12px;
            overflow-y: auto;
        }

        .menu button {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            margin: 2px 0;
            border: 0;
            border-radius: 6px;
            color: #dce7f3;
            background: transparent;
            text-align: left;
            cursor: pointer;
            line-height: 1.25;
        }

        .menu button:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .menu button.active {
            color: #ffffff;
            background: var(--nav-soft);
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .content {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            min-height: 76px;
            padding: 16px 24px;
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 21px;
            line-height: 1.2;
            font-weight: 760;
        }

        .topbar .meta {
            margin-top: 5px;
            color: var(--muted);
            line-height: 1.45;
        }

        .role-control {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 6px 10px;
            border-radius: 999px;
            color: #07503a;
            background: var(--accent-soft);
            border: 1px solid #b7e1d2;
            font-size: 12px;
            font-weight: 700;
        }

        main {
            padding: 22px 24px 30px;
        }

        .filterbar {
            margin-bottom: 18px;
            padding: 14px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            grid-template-columns: 120px minmax(220px, 320px) minmax(240px, 380px) minmax(220px, 1fr);
            gap: 12px;
            align-items: end;
        }

        .field {
            min-width: 0;
            display: grid;
            gap: 6px;
        }

        label {
            color: #394553;
            font-size: 12px;
            font-weight: 720;
        }

        select {
            width: 100%;
            min-height: 38px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 34px 0 10px;
            color: var(--ink);
            background: #ffffff;
        }

        select:disabled {
            color: #475467;
            background: #eef2f6;
            opacity: 1;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .metric {
            min-height: 96px;
            padding: 15px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .metric .label {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.3;
        }

        .metric .value {
            margin-top: 10px;
            font-size: 27px;
            font-weight: 780;
            line-height: 1;
        }

        .table-panel,
        .placeholder {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .table-panel header,
        .placeholder {
            padding: 16px;
        }

        .table-panel header {
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .table-panel h2,
        .placeholder h2 {
            margin: 0;
            font-size: 16px;
        }

        .status-text {
            color: var(--muted);
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e4e9f0;
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: #344054;
            background: #f8fafc;
            font-size: 12px;
            font-weight: 760;
        }

        th.group {
            text-align: center;
            border-left: 1px solid #e4e9f0;
        }

        td.num,
        th.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        tbody tr:hover td {
            background: #fbfcfe;
        }

        tfoot td {
            background: #f8fafc;
            font-weight: 760;
        }

        .number-link {
            min-width: 36px;
            padding: 4px 8px;
            border: 1px solid #b7e1d2;
            border-radius: 6px;
            color: #07503a;
            background: #f2fbf7;
            cursor: pointer;
            font-variant-numeric: tabular-nums;
        }

        .number-link:hover,
        .number-link:focus {
            border-color: var(--accent);
            outline: 2px solid rgba(8, 127, 91, 0.16);
        }

        .empty-row {
            padding: 26px 12px;
            color: var(--muted);
            text-align: center;
        }

        .error {
            color: var(--danger);
        }

        .drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: none;
            background: rgba(15, 23, 42, 0.32);
        }

        .drawer-backdrop.open {
            display: block;
        }

        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 30;
            width: min(620px, 100vw);
            height: 100vh;
            display: none;
            background: var(--panel);
            box-shadow: -20px 0 50px rgba(15, 23, 42, 0.18);
        }

        .drawer.open {
            display: flex;
            flex-direction: column;
        }

        .drawer header {
            min-height: 76px;
            padding: 18px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .drawer h3 {
            margin: 0;
            font-size: 18px;
        }

        .icon-button {
            width: 36px;
            height: 36px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
        }

        .drawer-body {
            padding: 18px;
            overflow-y: auto;
            display: grid;
            gap: 12px;
        }

        .detail-item {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .detail-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 12px;
            color: #475467;
        }

        .detail-grid span {
            color: var(--muted);
            font-size: 12px;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                min-height: auto;
                max-height: 280px;
            }

            .filterbar,
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 680px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            main {
                padding: 16px;
            }

            .filterbar,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">S</div>
                <div>
                    <div class="brand-title">SAPTO</div>
                    <div class="brand-subtitle">Akreditasi Program Studi</div>
                </div>
            </div>
            <nav class="menu" aria-label="Menu tabel SAPTO">
                @foreach ($tables as $table)
                    <button type="button" data-table-id="{{ $table['id'] }}" class="{{ $table['id'] === 'dosen-pembimbing-ta' ? 'active' : '' }}">
                        {{ $table['name'] }}
                    </button>
                @endforeach
            </nav>
        </aside>

        <div class="content">
            <header class="topbar">
                <div>
                    <h1 id="page-title">Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir</h1>
                    <div class="meta" id="page-description">Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.</div>
                </div>
                <div class="role-control">
                    <label for="role">Role</label>
                    <select id="role" aria-label="Role pengguna">
                        <option value="universitas">Pengelola SAPTO Universitas</option>
                        <option value="fakultas">Pengelola Fakultas</option>
                        <option value="prodi">Pengelola Program Studi</option>
                    </select>
                </div>
            </header>

            <main>
                <section id="table-page">
                    <div class="filterbar">
                        <div class="field">
                            <label for="ts">TS</label>
                            <select id="ts"></select>
                        </div>
                        <div class="field">
                            <label for="fakultas">Fakultas</label>
                            <select id="fakultas"></select>
                        </div>
                        <div class="field">
                            <label for="program-studi">Program Studi</label>
                            <select id="program-studi"></select>
                        </div>
                        <div class="field">
                            <label>Unit Aktif</label>
                            <div class="badge" id="active-unit">Universitas Sriwijaya</div>
                        </div>
                    </div>

                    <div class="summary-grid">
                        <div class="metric">
                            <div class="label">Total dosen pembimbing</div>
                            <div class="value" id="metric-dosen">0</div>
                        </div>
                        <div class="metric">
                            <div class="label">Total mahasiswa bimbingan PS diakreditasi</div>
                            <div class="value" id="metric-ps">0</div>
                        </div>
                        <div class="metric">
                            <div class="label">Total mahasiswa bimbingan PS lain di PT</div>
                            <div class="value" id="metric-ps-lain">0</div>
                        </div>
                        <div class="metric">
                            <div class="label">TS aktif</div>
                            <div class="value" id="metric-ts">-</div>
                        </div>
                    </div>

                    <section class="table-panel">
                        <header>
                            <h2>Dosen Pembimbing Utama Tugas Akhir</h2>
                            <div class="status-text" id="table-status">Memuat data...</div>
                        </header>
                        <div class="table-wrap">
                            <table>
                                <thead id="table-head"></thead>
                                <tbody id="table-body"></tbody>
                                <tfoot id="table-foot"></tfoot>
                            </table>
                        </div>
                    </section>
                </section>

                <section id="placeholder-page" class="placeholder" hidden>
                    <h2 id="placeholder-title"></h2>
                    <p class="status-text">Halaman ini belum dikembangkan pada tahap pertama. Fokus tahap pertama adalah Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir.</p>
                </section>
            </main>
        </div>
    </div>

    <div class="drawer-backdrop" id="drawer-backdrop"></div>
    <aside class="drawer" id="detail-drawer" aria-labelledby="detail-title" aria-modal="true" role="dialog">
        <header>
            <div>
                <h3 id="detail-title">Detail Data</h3>
                <div class="status-text" id="detail-subtitle"></div>
            </div>
            <button type="button" class="icon-button" id="close-drawer" aria-label="Tutup detail">&times;</button>
        </header>
        <div class="drawer-body" id="detail-body"></div>
    </aside>

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
            els.tableBody.innerHTML = '<tr><td class="empty-row" colspan="12">Memuat data...</td></tr>';
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
                    <th rowspan="2">No</th>
                    <th rowspan="2">Nama Dosen</th>
                    <th rowspan="2">NIDN/NIDK/NIP</th>
                    <th class="group" colspan="4">Jumlah Mahasiswa yang Dibimbing pada PS yang Diakreditasi</th>
                    <th class="group" colspan="4">Jumlah Mahasiswa yang Dibimbing pada PS Lain di PT</th>
                    <th rowspan="2" class="num">Rata-rata Jumlah Bimbingan di Semua PS</th>
                </tr>
                <tr>
                    <th class="num">TS-2 (${y.ts_2})</th>
                    <th class="num">TS-1 (${y.ts_1})</th>
                    <th class="num">TS (${y.ts})</th>
                    <th class="num">Rata-rata</th>
                    <th class="num">TS-2 (${y.ts_2})</th>
                    <th class="num">TS-1 (${y.ts_1})</th>
                    <th class="num">TS (${y.ts})</th>
                    <th class="num">Rata-rata</th>
                </tr>
            `;

            if (!summary.rows.length) {
                els.tableBody.innerHTML = '<tr><td class="empty-row" colspan="12">Belum ada data dosen pembimbing utama tugas akhir untuk kombinasi filter ini.</td></tr>';
                els.tableFoot.innerHTML = totalsRow(summary.totals, true);
                return;
            }

            els.tableBody.innerHTML = summary.rows.map((row) => `
                <tr>
                    <td>${row.no}</td>
                    <td>${escapeHtml(row.nama_dosen || '-')}</td>
                    <td>${escapeHtml(row.identifier || '-')}</td>
                    ${yearButton(row, 'ps_diakreditasi', 'ts_2')}
                    ${yearButton(row, 'ps_diakreditasi', 'ts_1')}
                    ${yearButton(row, 'ps_diakreditasi', 'ts')}
                    <td class="num">${formatNumber(row.ps_diakreditasi.rata_rata)}</td>
                    ${yearButton(row, 'ps_lain_pt', 'ts_2')}
                    ${yearButton(row, 'ps_lain_pt', 'ts_1')}
                    ${yearButton(row, 'ps_lain_pt', 'ts')}
                    <td class="num">${formatNumber(row.ps_lain_pt.rata_rata)}</td>
                    <td class="num">${formatNumber(row.rata_rata_semua_ps)}</td>
                </tr>
            `).join('');
            els.tableFoot.innerHTML = totalsRow(summary.totals, false);
        }

        function yearButton(row, scope, yearKey) {
            const value = row[scope][yearKey];

            return `
                <td class="num">
                    <button type="button" class="number-link" data-dosen-id="${row.dosen_id}" data-dosen-name="${escapeAttr(row.nama_dosen || '')}" data-scope="${scope}" data-year-key="${yearKey}">
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
                    <td colspan="3">${empty ? 'Ringkasan' : 'Total'}</td>
                    <td class="num">${formatNumber(ps.ts_2)}</td>
                    <td class="num">${formatNumber(ps.ts_1)}</td>
                    <td class="num">${formatNumber(ps.ts)}</td>
                    <td class="num">${formatNumber(ps.rata_rata)}</td>
                    <td class="num">${formatNumber(psLain.ts_2)}</td>
                    <td class="num">${formatNumber(psLain.ts_1)}</td>
                    <td class="num">${formatNumber(psLain.ts)}</td>
                    <td class="num">${formatNumber(psLain.rata_rata)}</td>
                    <td class="num">${formatNumber(totalAverage)}</td>
                </tr>
            `;
        }

        function renderError(message) {
            els.metricDosen.textContent = '0';
            els.metricPs.textContent = '0';
            els.metricPsLain.textContent = '0';
            els.metricTs.textContent = state.ts;
            els.tableStatus.innerHTML = `<span class="error">${escapeHtml(message)}</span>`;
            els.tableBody.innerHTML = `<tr><td class="empty-row error" colspan="12">${escapeHtml(message)}</td></tr>`;
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
            els.detailBody.innerHTML = '<div class="status-text">Memuat detail...</div>';
            showDrawer();

            try {
                const payload = await fetchJson(`/api/sapto/tables/dosen-pembimbing-ta/detail?${params}`);
                renderDetail(payload);
            } catch (error) {
                els.detailBody.innerHTML = `<div class="error">${escapeHtml(error.message)}</div>`;
            }
        }

        function renderDetail(payload) {
            if (!payload.items.length) {
                els.detailBody.innerHTML = '<div class="status-text">Tidak ada detail data untuk kombinasi dosen, program studi, dan TS ini.</div>';
                return;
            }

            els.detailBody.innerHTML = payload.items.map((item) => `
                <article class="detail-item">
                    <strong>${escapeHtml(item.nama_mahasiswa || '-')}</strong>
                    <div class="detail-grid">
                        <div><span>NIM</span><br>${escapeHtml(item.nim || '-')}</div>
                        <div><span>Fakultas</span><br>${escapeHtml(item.fakultas || '-')}</div>
                        <div><span>Program Studi</span><br>${escapeHtml(item.program_studi || '-')}</div>
                        <div><span>Judul</span><br>${escapeHtml(item.judul_tugas_akhir || '-')}</div>
                        <div><span>Jenis</span><br>${escapeHtml(item.jenis_tugas_akhir || '-')}</div>
                        <div><span>Tahun</span><br>${escapeHtml(String(item.tahun || '-'))}</div>
                        <div><span>Status</span><br>${escapeHtml(item.status || '-')}</div>
                        <div><span>Sumber Data</span><br>${escapeHtml(item.sumber_data || '-')}</div>
                    </div>
                </article>
            `).join('');
        }

        function showDrawer() {
            els.drawer.classList.add('open');
            els.drawerBackdrop.classList.add('open');
        }

        function closeDrawer() {
            els.drawer.classList.remove('open');
            els.drawerBackdrop.classList.remove('open');
        }

        function activateTable(tableId) {
            document.querySelectorAll('.menu button').forEach((button) => {
                button.classList.toggle('active', button.dataset.tableId === tableId);
            });

            const table = tables.find((item) => item.id === tableId);
            els.pageTitle.textContent = table?.name || 'SAPTO';

            if (tableId === activeTableId) {
                els.pageDescription.textContent = 'Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.';
                els.tablePage.hidden = false;
                els.placeholderPage.hidden = true;
                loadSummary();
            } else {
                els.pageDescription.textContent = 'Tahap pertama pengembangan SAPTO.';
                els.tablePage.hidden = true;
                els.placeholderPage.hidden = false;
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
            const button = event.target.closest('.number-link');

            if (button) {
                openDetail(button);
            }
        });

        document.querySelectorAll('.menu button').forEach((button) => {
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
</body>
</html>
