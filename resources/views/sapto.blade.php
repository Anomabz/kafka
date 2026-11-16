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

{{-- Beranda desktop (halaman virtual `beranda`; versi HP di mobile-pages). --}}
@include('components.desktop-home')

@include('components.dashboard-page')

<section id="table-page" class="hidden">
    <div class="metric-strip mb-4 grid grid-cols-2 gap-2.5 sm:mb-5 sm:gap-3 lg:grid-cols-4">
        @include('components.metric-card', ['id' => 'metric-dosen', 'labelId' => 'metric-dosen-label', 'label' => 'Total dosen pembimbing', 'icon' => $icons['users'], 'tint' => 'accent'])
        @include('components.metric-card', ['id' => 'metric-ps', 'labelId' => 'metric-ps-label', 'label' => 'Total mahasiswa bimbingan PS diakreditasi', 'icon' => $icons['cap'], 'tint' => 'info'])
        @include('components.metric-card', ['id' => 'metric-ps-lain', 'labelId' => 'metric-ps-lain-label', 'label' => 'Total mahasiswa bimbingan PS lain di PT', 'icon' => $icons['swap'], 'tint' => 'warn'])
        @include('components.metric-card', ['id' => 'metric-ts', 'labelId' => 'metric-ts-label', 'label' => 'TS aktif', 'icon' => $icons['calendar'], 'tint' => 'slate'])
    </div>

    @include('components.table-panel')
</section>

<section id="mahasiswa-asing-page" class="hidden">
    @include('components.mahasiswa-asing-filter')

    <div class="metric-strip mb-4 grid grid-cols-2 gap-2.5 sm:mb-5 sm:gap-3 lg:grid-cols-4">
        @include('components.metric-card', ['id' => 'ma-metric-prodi', 'label' => 'Total program studi', 'icon' => $icons['cap'], 'tint' => 'accent'])
        @include('components.metric-card', ['id' => 'ma-metric-aktif', 'label' => 'Total mahasiswa aktif (TS)', 'icon' => $icons['users'], 'tint' => 'info'])
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
@include('components.card-sheet')
@endsection

@section('scripts')
<script>
const tables = @json($tables);
const TABLE_VISIBILITY = {
'mahasiswa-asing': { exclude_levels: ['D3'] },
'dosen-industri': { only_levels: ['D3'] },
'publikasi-ilmiah-dtps': { exclude_levels: ['D3'] },
'pagelaran-dtps': { only_levels: ['D3'] },
'sitasi-dtps': { exclude_levels: ['D3'] },
'produk-jasa-dtps': { only_levels: ['D3'] },
'penelitian-melibatkan-mahasiswa': { exclude_levels: ['D3'] },
'penelitian-rujukan-tema': { exclude_levels: ['D3', 'S1'] },
'pkm-melibatkan-mahasiswa': { only_levels: ['D3'] },
'prestasi-non-akademik': { only_levels: ['D3'] },
};
const IS_ADMIN = @json((bool) data_get(session('sso_user'), 'is_admin', false));
const currentYear = new Date().getFullYear();

// Sama seperti $groupLabels di sidebar.blade.php (dikelompokkan dari
// digit pertama table.code) -- breadcrumb butuh nama kategori yang sama.
const GROUP_LABELS = {
    '1': 'Kerjasama Tridharma',
    '2': 'Seleksi & Mahasiswa Asing',
    '3': 'Dosen',
    '4': 'Penggunaan Dana',
    '5': 'Kurikulum & Pembelajaran',
    '6': 'Penelitian Melibatkan Mahasiswa',
    '7': 'PkM Melibatkan Mahasiswa',
    '8': 'Capaian & Profil Lulusan',
};
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

function pageSizeForCurrentTable() {
    return PAGE_SIZE;
}

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
    breadcrumbGroupWrap: document.getElementById('breadcrumb-group-wrap'),
    breadcrumbGroup: document.getElementById('breadcrumb-group'),
    toastStack: document.getElementById('toast-stack'),
    tableStatus: document.getElementById('table-status'),
    tableNotice: document.getElementById('table-notice'),
    tableSearch: document.getElementById('table-search'),
    tableExport: document.getElementById('table-export'),
    tableHead: document.getElementById('table-head'),
    tableBody: document.getElementById('table-body'),
    tableFoot: document.getElementById('table-foot'),
    tablePagination: document.getElementById('table-pagination'),
    tableDetailPanel: document.getElementById('table-detail-panel'),
    tableDetailTitle: document.getElementById('table-detail-title'),
    tableDetailStatus: document.getElementById('table-detail-status'),
    tableDetailHead: document.getElementById('table-detail-head'),
    tableDetailBody: document.getElementById('table-detail-body'),
    tableDetailPagination: document.getElementById('table-detail-pagination'),
    tableDetailExport: document.getElementById('table-detail-export'),
    tableDetailClose: document.getElementById('table-detail-close'),
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
    drawerDetailExport: document.getElementById('drawer-detail-export'),
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

// Delegasi klik menjaga navigasi menu tetap aktif meski item menu dibuat
// ulang oleh tampilan desktop/mobile.
document.addEventListener('click', (event) => {
    const menuItem = event.target.closest('.menu-item');
    if (menuItem) activateTable(menuItem.dataset.tableId);
});

const metricEls = [els.metricDosen, els.metricPs, els.metricPsLain, els.metricTs];
const maMetricEls = [els.maMetricProdi, els.maMetricAktif, els.maMetricFt, els.maMetricPt];
let maSummary = null;

// Model tampilan kartu (mobile) untuk tabel yang sedang tampil -- diisi
// di akhir jalur sukses render tabel, null selama loading/kosong/error.
// Lihat bagian "SAPTO Mobile" di bawah.
let cardModel = null;
const cardEls = {
    panel: document.getElementById('table-panel-section'),
    cards: document.getElementById('table-cards'),
    maPanel: document.getElementById('ma-panel-section'),
    maCards: document.getElementById('ma-table-cards'),
    viewButtons: document.querySelectorAll('[data-view-toggle]'),
    sheet: document.getElementById('card-sheet'),
    sheetBackdrop: document.getElementById('sheet-backdrop'),
    sheetTitle: document.getElementById('sheet-title'),
    sheetSubtitle: document.getElementById('sheet-subtitle'),
    sheetClose: document.getElementById('sheet-close'),
    sheetPrev: document.getElementById('sheet-prev'),
    sheetNext: document.getElementById('sheet-next'),
    sheetPosition: document.getElementById('sheet-position'),
    sheetBody: document.getElementById('sheet-body'),
};

// Filter halaman Mahasiswa Asing — khusus admin, dan cuma memuat prodi/
// fakultas yang benar-benar punya mahasiswa asing (dibangun dari data
// maSummary, bukan katalog penuh). Korprodi biasa tak melihat dropdown ini
// (server sudah scope ke satu prodi mereka).
const maFilter = { fakultasId: 'all', prodiId: 'all', searchQuery: '' };

let currentTableId = @json($activeTableId ?? 'kerjasama-pendidikan');

// Bookmarkable/shareable state: baca ?table=&ts=&fakultas=&prodi= dari URL
// saat load supaya link yang dibagikan membuka tabel+filter yang sama.
// Harus jalan sebelum loadFakultas/loadProgramStudi/activateTable pertama
// (lihat IIFE boot di akhir file) karena render select fakultas/prodi
// membaca state.fakultasId/programStudiId yang di-set di sini.
(() => {
    const params = new URLSearchParams(window.location.search);
    const tableParam = params.get('table');

    if (tableParam && tables.some((item) => item.id === tableParam)) {
        currentTableId = tableParam;
    }

    const tsParam = params.get('ts');
    if (tsParam) {
        state.ts = Number(tsParam);
        state.maTs = Number(tsParam);
    }

    const fakultasParam = params.get('fakultas');
    if (fakultasParam) {
        state.fakultasId = fakultasParam;
        maFilter.fakultasId = fakultasParam;
    }

    const prodiParam = params.get('prodi');
    if (prodiParam) {
        state.programStudiId = prodiParam;
        maFilter.prodiId = prodiParam;
    }
})();

// Tulis tabel+filter aktif ke URL (replaceState, tidak menambah entri
// history) supaya halaman ini bisa di-bookmark/dibagikan. Dipanggil dari
// activateTable() (ganti tabel) dan renderSummary()/renderMaSummary()
// (ganti TS/fakultas/prodi).
function syncUrl() {
    // Halaman virtual mobile (Beranda dst.) bukan tabel: tak ditulis ke URL.
    if (isVirtual(currentTableId)) {
        history.replaceState({ ...history.state, sapto: currentTableId }, '', location.pathname);

        return;
    }

    const params = new URLSearchParams();
    params.set('table', currentTableId);

    if (currentTableId === 'mahasiswa-asing') {
        if (state.maTs !== null) params.set('ts', state.maTs);
        if (maFilter.fakultasId !== 'all') params.set('fakultas', maFilter.fakultasId);
        if (maFilter.prodiId !== 'all') params.set('prodi', maFilter.prodiId);
    } else {
        if (state.ts !== null) params.set('ts', state.ts);
        if (state.canSelectFakultas && state.fakultasId !== 'all') params.set('fakultas', state.fakultasId);
        if (state.canSelectProgramStudi && state.programStudiId !== 'all') params.set('prodi', state.programStudiId);
    }

    const query = params.toString();
    // State dipertahankan: di HP, state itu yang dipakai tombol Kembali.
    history.replaceState({ ...history.state, sapto: currentTableId }, '', query ? `${location.pathname}?${query}` : location.pathname);
}

// Breadcrumb "Beranda / <kategori>" di atas judul halaman -- kategori
// sama dengan grup sidebar (lihat GROUP_LABELS di atas).
function updateBreadcrumb(table) {
    const groupKey = table ? String(table.code).split('.')[0] : null;
    const groupLabel = groupKey ? GROUP_LABELS[groupKey] : null;

    if (groupLabel) {
        els.breadcrumbGroup.textContent = groupLabel;
        els.breadcrumbGroupWrap.classList.remove('hidden');
        els.breadcrumbGroupWrap.classList.add('flex');
    } else {
        els.breadcrumbGroupWrap.classList.add('hidden');
        els.breadcrumbGroupWrap.classList.remove('flex');
    }
}

// Notifikasi kecil di pojok kanan bawah untuk konfirmasi aksi (mis.
// export berhasil) yang tidak perlu mengganggu alur kerja dengan modal.
let toastSeq = 0;
function showToast(message, type = 'success') {
    if (!els.toastStack) return;

    const el = document.createElement('div');
    el.id = `toast-${++toastSeq}`;
    el.className = `toast${type === 'error' ? ' is-error' : ''}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const iconPath = type === 'error'
        ? 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'
        : 'M22 11.08V12a10 10 0 1 1-5.93-9.14M22 4 12 14.01l-3-3';

    el.innerHTML = `
        <svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${iconPath}" /></svg>
        <span class="flex-1">${escapeHtml(message)}</span>
        <button type="button" class="toast-close" aria-label="Tutup notifikasi">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
    `;

    const dismiss = () => {
        el.classList.add('is-leaving');
        el.addEventListener('animationend', () => el.remove(), { once: true });
    };

    el.querySelector('.toast-close').addEventListener('click', dismiss);
    els.toastStack.appendChild(el);
    setTimeout(dismiss, 4000);
}

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

// Fakultas: hanya admin dan rektor yang can_select_fakultas=true dari API.
// Dekan dan korprodi tidak melihat field ini -- fakultas mereka dikunci di
// server (SaptoUserContext), bukan cuma disembunyikan di sini.
async function loadFakultas() {
    const payload = await fetchJson('/api/sapto/fakultas');
    state.canSelectFakultas = Boolean(payload.can_select_fakultas);
    state.fakultas = payload.data || [];

    els.fakultasWrap.classList.toggle('hidden', !state.canSelectFakultas);

    if (state.canSelectFakultas) {
        renderFakultasSelect();
    }
}

// Program studi: korprodi cuma dapat prodinya sendiri tanpa pilihan; dekan
// dapat daftar prodi di fakultasnya dan boleh memilih; admin/rektor dapat
// daftar penuh (can_select_program_studi=true untuk dekan, rektor, admin).
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

    updateSidebarForScope();
}

function jenjangKey(jenjang) {
    const value = String(jenjang || '').trim().toLowerCase();
    if (/\bd3\b|diploma\s+tiga/.test(value)) return 'D3';
    if (/\bd4\b|diploma\s+empat|sarjana\s+terapan/.test(value)) return 'D4';
    if (/\bs2\b|magister/.test(value)) return 'S2';
    if (/\bs3\b|doktor/.test(value)) return 'S3';
    if (/\bs1\b|sarjana/.test(value)) return 'S1';
    return null;
}

function tableVisibleForScope(tableId) {
    if (IS_ADMIN) return true;
    const rule = TABLE_VISIBILITY[tableId];
    if (!rule) return true;
    let scopedProgramStudis = state.programStudi;
    if (state.canSelectProgramStudi && state.programStudiId !== 'all') {
        const selected = state.programStudi.find((item) => String(item.id) === String(state.programStudiId));
        scopedProgramStudis = selected ? [selected] : [];
    }
    if (!scopedProgramStudis.length) return true;
    return scopedProgramStudis.some((programStudi) => {
        const level = jenjangKey(programStudi.jenjang);
        if (!level) return true;
        if (Array.isArray(rule.only_levels) && !rule.only_levels.includes(level)) return false;
        if (Array.isArray(rule.exclude_levels) && rule.exclude_levels.includes(level)) return false;
        return true;
    });
}

function applyMenuSearch() {
    const query = els.menuSearch.value.trim().toLowerCase();
    let visibleCount = 0;
    els.menuGroups.forEach((group) => {
        let groupHasMatch = false;
        group.querySelectorAll('.menu-item').forEach((button) => {
            const matches = button.dataset.levelVisible !== 'false'
                && (!query || button.dataset.search.includes(query));
            button.classList.toggle('hidden', !matches);
            if (matches) {
                groupHasMatch = true;
                visibleCount += 1;
            }
        });
        group.classList.toggle('hidden', !groupHasMatch);
        if (query && groupHasMatch && group.classList.contains('menu-group-dropdown')) {
            group.classList.remove('is-collapsed');
            group.querySelector('[data-group-toggle]')?.setAttribute('aria-expanded', 'true');
        }
    });
    els.menuEmpty.classList.toggle('hidden', visibleCount > 0);
}

function updateSidebarForScope() {
    document.querySelectorAll('.menu-item').forEach((button) => {
        button.dataset.levelVisible = String(tableVisibleForScope(button.dataset.tableId));
    });
    els.menuGroups.forEach((group) => {
        const count = group.querySelector('.menu-group-count');
        if (count) {
            count.textContent = String([...group.querySelectorAll('.menu-item')]
                .filter((button) => button.dataset.levelVisible === 'true').length);
        }
    });
    applyMenuSearch();
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

    // Dekan (bisa pilih prodi, tidak bisa pilih fakultas) yang belum
    // memilih prodi: unitnya fakultasnya sendiri, bukan universitas.
    // Semua prodi yang dikirim server untuk dekan berada di fakultas itu.
    if (!state.canSelectFakultas) {
        const fakultas = (state.programStudi[0] || {}).fakultas || (state.fakultas[0] || {}).nama;

        if (fakultas) {
            return fakultas;
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

// Skeleton beberapa baris x beberapa kolom (bukan satu bar penuh lebar)
// supaya sekilas terlihat seperti bentuk tabel sungguhan selagi data
// dimuat, bukan cuma satu garis abu-abu.
const SKELETON_BAR_WIDTHS = ['w-full', 'w-5/6', 'w-2/3', 'w-3/4', 'w-1/2'];

function skeletonRows(colCount, rowCount = 6) {
    return Array.from({ length: rowCount }).map((_, r) => `
        <tr>
            ${Array.from({ length: colCount }).map((__, c) => `
                <td class="sp-skeleton-td"><div class="sp-skeleton-bar ${SKELETON_BAR_WIDTHS[(r + c) % SKELETON_BAR_WIDTHS.length]}"></div></td>
            `).join('')}
        </tr>
    `).join('');
}

function setLoading() {
    closeTableDetail();
    metricEls.forEach((el) => el.classList.add('is-loading'));
    els.tableStatus.textContent = 'Memuat data...';
    if (els.tableNotice) {
        els.tableNotice.hidden = true;
        els.tableNotice.textContent = '';
    }
    els.tableHead.innerHTML = '';
    els.tableBody.innerHTML = skeletonRows(6);
    delete els.tablePage.dataset.state;
    renderCardsLoading();
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

    syncUrl();
    syncMobileAppbar();
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
    summary.rows = Array.isArray(summary?.rows) ? summary.rows : [];
    summary.columns = Array.isArray(summary?.columns) ? summary.columns : [];
    summary.metrics = Array.isArray(summary?.metrics) ? summary.metrics : [];
    els.tableCardTitle.textContent = summary.table_name || 'Data Tabel';
    setMetricCards(summary.metrics || []);
    const unit = GENERIC_TABLES[currentTableId]?.unit || 'baris';
    // notice + baris = keterangan sumber data; notice tanpa baris = tabel
    // ini memang belum bisa ditampilkan (mis. harus pilih prodi dulu).
    els.tableStatus.textContent = summary.rows.length
        // Di HP keterangan "sebagian kolom" tidak ditampilkan di sini
        // (terlalu teknis untuk baris status); catatan di bawah daftar
        // sudah menjelaskannya.
        ? `${summary.rows.length} ${unit}${summary.source?.partial && !mobileMq.matches ? ' - sebagian kolom belum tersedia' : ''}`
        : (summary.notice ? 'Pilih program studi' : 'Belum ada data');
    renderGenericTable(summary);
}

// Header dua baris untuk kolom yang punya `group` (mis. Tabel 8.d.1/8.d.2
// BAN-PT: "Jumlah Lulusan Terlacak dengan ..." membentang di atas
// beberapa sub-kolom). Kolom tanpa `group` dapat rowspan 2 supaya tetap
// sejajar; kolom `group` yang sama & berurutan digabung jadi satu <th
// colspan>. Dipisah dari renderGenericTable supaya jalur tanpa grup
// (mayoritas tabel) tidak kena overhead ini.
function renderGroupedHead(cols) {
    const top = [];
    const bottom = [];

    for (let i = 0; i < cols.length;) {
        const c = cols[i];

        if (!c.group) {
            top.push(`<th rowspan="2" class="sp-th ${c.align === 'num' ? 'sp-th-num' : ''} ${i === 0 ? 'sp-th-sticky' : ''}">${escapeHtml(c.label)}</th>`);
            i += 1;
            continue;
        }

        let span = 0;
        while (i + span < cols.length && cols[i + span].group === c.group) span += 1;
        top.push(`<th class="sp-th sp-th-group" colspan="${span}">${escapeHtml(c.group)}</th>`);

        for (let j = 0; j < span; j += 1) {
            bottom.push(`<th class="sp-th ${cols[i + j].align === 'num' ? 'sp-th-num' : ''}">${escapeHtml(cols[i + j].label)}</th>`);
        }

        i += span;
    }

    return `<tr>${top.join('')}</tr><tr>${bottom.join('')}</tr>`;
}

// Render tabel generik dari definisi kolom + baris envelope backend.
function renderGenericTable(summary) {
    cardModel = null;
    renderGenericTableBody(summary);
    refreshCards();
}

function renderGenericTableBody(summary) {
    const cols = summary.columns || [];
    const rows = Array.isArray(summary?.rows) ? summary.rows : [];
    const adaBaris = rows.length > 0;

    // Catatan sumber data ditampilkan di atas tabel selama masih ada
    // barisnya; kalau tidak, catatan itu yang menggantikan tabel.
    if (els.tableNotice) {
        els.tableNotice.textContent = summary.notice && adaBaris ? summary.notice : '';
        els.tableNotice.hidden = !(summary.notice && adaBaris);
    }

    // Pesan pengarah (mis. Tabel 8.c minta pilih prodi dulu).
    if (summary.notice && !adaBaris) {
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
    els.tableHead.innerHTML = cols.some((c) => c.group) ? renderGroupedHead(cols) : `<tr>${cols.map((c, i) =>
        `<th class="sp-th ${c.align === 'num' ? 'sp-th-num' : ''} ${i === 0 ? 'sp-th-sticky' : ''}">${escapeHtml(c.label)}</th>`).join('')}</tr>`;

    if (!rows.length) {
        els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="${cols.length || 1}">Belum ada data untuk kombinasi filter ini.</td></tr>`;
        els.tableFoot.innerHTML = '';
        els.tablePagination.innerHTML = '';
        return;
    }

    const filteredRows = filterGenericRows(rows, cols, state.searchQuery);

    if (!filteredRows.length) {
        els.tableBody.innerHTML = `<tr><td class="sp-empty-row" colspan="${cols.length || 1}">Tidak ada baris yang cocok dengan pencarian "${escapeHtml(state.searchQuery)}".</td></tr>`;
        els.tableFoot.innerHTML = '';
        els.tablePagination.innerHTML = '';
        return;
    }

    const pageSize = pageSizeForCurrentTable();
    const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
    state.page = Math.min(Math.max(state.page, 1), pageCount);
    const startIndex = (state.page - 1) * pageSize;
    const pageRows = filteredRows.slice(startIndex, startIndex + pageSize);

    els.tableBody.innerHTML = pageRows.map((row) =>
        `<tr class="sp-row">${cols.map((c, i) => genericCell(row, c, i === 0)).join('')}</tr>`).join('');
    els.tableFoot.innerHTML = '';
    cardModel = { cols, rows: filteredRows, roles: cardRoles(cols, filteredRows), start: startIndex, pageRows };
    renderPagination(filteredRows.length, GENERIC_TABLES[currentTableId]?.unit || 'baris');
}

function genericCell(row, col, isFirst) {
    const value = row[col.key];
    const sticky = isFirst ? ' sp-td-sticky' : '';

    if (col.align === 'num') {
        const display = value === null || value === undefined ? '-' : formatNumber(value);
        const content = isClickableDetail(row, col)
            ? detailTrigger(row, col, display)
            : display;

        return `<td class="sp-td sp-td-num${sticky}">${content}</td>`;
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

const DETAIL_TABLE_IDS = new Set(['penelitian-dtps', 'pkm-dtps', 'publikasi-ilmiah-dtps', 'pagelaran-dtps']);
const DETAIL_YEAR_KEYS = new Set(['ts_2', 'ts_1', 'ts']);
const GRADUATE_TABLE_IDS = new Set(['ipk-lulusan', 'masa-studi', 'waktu-tunggu', 'kesesuaian-bidang-kerja', 'tempat-kerja', 'referensi-kepuasan-pengguna']);
const SELECTION_DETAIL_CATEGORIES = {
    calon_lulus: { category: 'calon_lulus', jenisDaftar: null },
    mahasiswa_baru: { category: 'mahasiswa_baru', jenisDaftar: null },
    mahasiswa_aktif: { category: 'mahasiswa_aktif', jenisDaftar: null },
    baru_reguler: { category: 'mahasiswa_baru', jenisDaftar: 'reguler' },
    baru_transfer: { category: 'mahasiswa_baru', jenisDaftar: 'transfer' },
    aktif_reguler: { category: 'mahasiswa_aktif', jenisDaftar: 'reguler' },
    aktif_transfer: { category: 'mahasiswa_aktif', jenisDaftar: 'transfer' },
};

function isClickableDetail(row, col) {
    if (currentTableId === 'seleksi-mahasiswa') {
        return Number.isInteger(Number(row.detail_year)) && Object.hasOwn(SELECTION_DETAIL_CATEGORIES, col.key);
    }
    if (DETAIL_TABLE_IDS.has(currentTableId)) {
        return DETAIL_YEAR_KEYS.has(col.key) && Boolean(row.detail_key);
    }

    if (!GRADUATE_TABLE_IDS.has(currentTableId)) return false;
    if (currentTableId === 'ipk-lulusan' || ['waktu-tunggu', 'kesesuaian-bidang-kerja', 'tempat-kerja', 'referensi-kepuasan-pengguna'].includes(currentTableId)) {
        const tracerKeys = currentTableId === 'waktu-tunggu'
            ? ['jumlah_terlacak', 'wt_1', 'wt_2', 'wt_3']
            : ['jumlah_terlacak'];
        const hasYear = Number.isInteger(Number(row.tahun_lulus_tahun));
        const isTotal = row.tahun_lulus === 'Jumlah';
        return (col.key === 'jumlah_lulusan' && hasYear)
            || (tracerKeys.includes(col.key) && (hasYear || isTotal));
    }

    return col.key === 'diterima' || /^g_\d+$/.test(col.key) || col.key === 'lulus_sd_ts';
}

function detailTrigger(row, col, text) {
    const label = row.sumber_pembiayaan || row.jenis_publikasi || 'Rincian';
    if (currentTableId === 'seleksi-mahasiswa') {
        const selection = SELECTION_DETAIL_CATEGORIES[col.key];
        const category = selection.category;
        const labels = { calon_lulus: 'Lulus seleksi', mahasiswa_baru: 'Mahasiswa baru', mahasiswa_aktif: 'Mahasiswa aktif' };
        const jenisDaftar = selection.jenisDaftar || '';
        const daftarLabel = jenisDaftar ? ` · ${jenisDaftar}` : '';
        return `<button type="button" class="sp-number-chip sapto-detail-trigger" data-detail-type="seleksi-mahasiswa" data-detail-category="${escapeAttr(category)}" data-detail-jenis-daftar="${escapeAttr(jenisDaftar)}" data-detail-year="${escapeAttr(row.detail_year)}" data-detail-label="${escapeAttr(labels[category] + daftarLabel)}" aria-label="Lihat ${escapeAttr(labels[category] + daftarLabel)} tahun ${escapeAttr(row.detail_year)}">${text}</button>`;
    }
    if (GRADUATE_TABLE_IDS.has(currentTableId)) {
        if (col.key === 'jumlah_terlacak' || ['wt_1', 'wt_2', 'wt_3'].includes(col.key)) {
            const detailYears = Number.isInteger(Number(row.tahun_lulus_tahun))
                ? [Number(row.tahun_lulus_tahun)]
                : [state.summary.ts - 4, state.summary.ts - 3, state.summary.ts - 2];
            const yearLabel = detailYears.length === 1 ? String(detailYears[0]) : `${detailYears[0]}–${detailYears.at(-1)}`;
            return `<button type="button" class="sp-number-chip sapto-detail-trigger" data-detail-type="mahasiswa-tracer" data-detail-filter="${escapeAttr(col.key)}" data-detail-years="${escapeAttr(detailYears.join(','))}" aria-label="Lihat detail tracer lulusan tahun ${escapeAttr(yearLabel)}">${text}</button>`;
        }

        let kind = 'lulus';
        let year = row.tahun_lulus_tahun ?? '';

        if (currentTableId === 'masa-studi') {
            const back = col.key.match(/^g_(\d+)$/)?.[1];
            year = back !== undefined ? (row[`tahun_lulus_g_${back}`] ?? '') : '';
            kind = col.key === 'diterima' ? 'diterima' : 'lulus';
        }

        return `<button type="button" class="sp-number-chip sapto-detail-trigger" data-detail-type="mahasiswa-lulusan" data-detail-year="${escapeAttr(year)}" data-detail-cohort="${escapeAttr(row.tahun_masuk_tahun ?? '')}" data-detail-kind="${kind}" aria-label="Lihat daftar mahasiswa ${kind} ${escapeAttr(year || row.tahun_masuk_tahun || '')}">${text}</button>`;
    }

    return `<button type="button" class="sp-number-chip sapto-detail-trigger" data-detail-category="${escapeAttr(row.detail_key)}" data-detail-year-key="${escapeAttr(col.key)}" data-detail-label="${escapeAttr(label)}" aria-label="Lihat rincian ${escapeAttr(label)} ${escapeAttr(col.label)}">${text}</button>`;
}

function closeTableDetail() {
    if (!els.tableDetailPanel) return;
    els.tableDetailPanel.classList.add('hidden');
    if (els.tableDetailTitle) els.tableDetailTitle.textContent = 'Rincian data';
    if (els.tableDetailStatus) els.tableDetailStatus.textContent = '';
    if (els.tableDetailHead) els.tableDetailHead.innerHTML = '';
    if (els.tableDetailBody) els.tableDetailBody.innerHTML = '';
    if (els.tableDetailPagination) els.tableDetailPagination.innerHTML = '';
    selectionDetailContext = null;
    tableDetailExportContext = null;
    tableRecordDetailContext = null;
}

let tableDetailExportContext = null;
let tableRecordDetailContext = null;

function formatDetailValue(key, value) {
    if (value === null || value === undefined || value === '') return '-';
    if (key === 'ipk' && Number.isFinite(Number(value))) return (Math.trunc(Number(value) * 100) / 100).toFixed(2);
    return String(value);
}

async function openTableDetail(button) {
    const endpoint = GENERIC_TABLES[currentTableId]?.endpoint;
    if (!endpoint || !state.summary || !els.tableDetailPanel) return;

    if (button.dataset.detailType === 'seleksi-mahasiswa') {
        selectionDetailContext = { year: Number(button.dataset.detailYear), category: button.dataset.detailCategory, jenisDaftar: button.dataset.detailJenisDaftar || null, label: button.dataset.detailLabel, page: 1, perPage: 10 };
        await loadSelectionDetail();
        return;
    }

    selectionDetailContext = null;
    tableRecordDetailContext = null;

    const studentDetail = button.dataset.detailType === 'mahasiswa-lulusan';
    const tracerDetail = button.dataset.detailType === 'mahasiswa-tracer';
    const tracerYears = tracerDetail ? button.dataset.detailYears.split(',').map(Number) : [];
    const year = studentDetail ? Number(button.dataset.detailYear) : tracerDetail ? tracerYears[0] : state.summary.ts - ({ ts_2: 2, ts_1: 1, ts: 0 }[button.dataset.detailYearKey] ?? 0);
    const params = tracerDetail
        ? new URLSearchParams({ tahun: tracerYears.join(','), filter: button.dataset.detailFilter })
        : studentDetail
            ? new URLSearchParams()
            : new URLSearchParams({ year: String(year), category: button.dataset.detailCategory });
    if (studentDetail) {
        if (currentTableId === 'masa-studi') {
            params.set('ts', String(state.summary.ts));
            params.set('angkatan', button.dataset.detailCohort);
            params.set('jenis', button.dataset.detailKind);
            if (button.dataset.detailYear) params.set('tahun', button.dataset.detailYear);
        } else {
            params.set('tahun', String(year));
        }
    }
    if (state.canSelectFakultas && state.fakultasId !== 'all') params.set('fakultas_id', state.fakultasId);
    if (state.canSelectProgramStudi && state.programStudiId !== 'all') params.set('program_studi_id', state.programStudiId);

    els.tableDetailPanel.classList.remove('hidden');
    els.tableDetailTitle.textContent = tracerDetail
        ? `Rincian tracer lulusan · ${tracerYears.length === 1 ? year : `${tracerYears[0]}–${tracerYears.at(-1)}`}`
        : studentDetail
        ? (currentTableId === 'masa-studi'
            ? `Daftar mahasiswa ${button.dataset.detailKind} · angkatan ${button.dataset.detailCohort}${year ? ` · lulus ${year}` : ''}`
            : `Daftar mahasiswa lulusan · ${year}`)
        : `Rincian ${button.dataset.detailLabel} · ${year}`;
    els.tableDetailStatus.textContent = 'Memuat rincian...';
    els.tableDetailHead.innerHTML = '';
    els.tableDetailBody.innerHTML = '';
    if (els.tableDetailPagination) els.tableDetailPagination.innerHTML = '';

    const isPublication = currentTableId === 'publikasi-ilmiah-dtps' || currentTableId === 'pagelaran-dtps';
    const columns = tracerDetail
        ? [['nim', 'NIM'], ['nama', 'Nama Mahasiswa'], ['program_studi', 'Program Studi'], ['angkatan', 'Angkatan'], ['tahun_lulus', 'Tahun Lulus'], ['tahun_tracer', 'Tahun Tracer'], ['nama_kegiatan_tracer', 'Kegiatan Tracer'], ['kategori', 'Kategori'], ['masa_tunggu_bulan', 'Masa Tunggu (Bulan)'], ['gaji_penghasilan', 'Penghasilan'], ['ump', 'UMP'], ['jenis_tempat_kerja_studi', 'Jenis Tempat Kerja'], ['posisi', 'Posisi'], ['tanggal_respon', 'Tanggal Respon'], ['catatan', 'Catatan']]
        : studentDetail
        ? [['nim', 'NIM'], ['nama', 'Nama Mahasiswa'], ['program_studi', 'Program Studi'], ['angkatan', 'Angkatan'], ['tahun_lulus', 'Tahun Lulus'], ['ipk', 'IPK'], ['status', 'Status']]
        : isPublication
            ? [['judul', 'Judul'], ['jenis', 'Jenis Publikasi'], ['tahun', 'Tahun'], ['quartile', 'Kuartil']]
            : [['judul', 'Judul'], ['jenis', 'Skema'], ['tahun', 'Tahun'], ['dana_dikti', 'Dana DIKTI'], ['dana_pt', 'Dana Perguruan Tinggi'], ['dana_lain', 'Dana Institusi Lain']];
    if (!studentDetail && button.dataset.detailCategory === 'all') columns.splice(1, 0, ['kategori', isPublication ? 'Kelompok' : 'Sumber Pembiayaan']);
    els.tableDetailHead.innerHTML = `<tr>${columns.map(([, label]) => `<th class="sp-th">${escapeHtml(label)}</th>`).join('')}</tr>`;

    try {
        const payload = await fetchJson(`/api/sapto/tables/${endpoint}/detail?${params}`);
        const items = payload.items || payload.data || [];
        const exportItems = studentDetail
            ? items.map((item) => ({ ...item, status: item.status || (item.tahun_lulus ? 'Lulus' : item.status) }))
            : items;
        tableDetailExportContext = { columns, items: exportItems, title: els.tableDetailTitle.textContent };
        const paginatedDetail = DETAIL_TABLE_IDS.has(currentTableId);
        if (paginatedDetail) {
            tableRecordDetailContext = { columns, items, page: 1, perPage: 10, title: els.tableDetailTitle.textContent };
            renderRecordDetailPage();
        } else {
            els.tableDetailStatus.textContent = `${formatNumber(items.length)} ${studentDetail || tracerDetail ? 'mahasiswa' : 'judul'} ditemukan`;
            els.tableDetailBody.innerHTML = items.length
                ? items.map((item) => `<tr class="sp-row">${columns.map(([key], colIndex) => {
                const value = key === 'status' && studentDetail && item.tahun_lulus ? (item[key] || 'Lulus') : item[key];
                return `<td class="sp-td${colIndex === 0 ? ' sp-td-sticky' : ''}">${escapeHtml(formatDetailValue(key, value))}</td>`;
                }).join('')}</tr>`).join('')
                : `<tr><td class="sp-empty-row" colspan="${columns.length}">Tidak ada detail untuk kategori dan tahun ini.</td></tr>`;
        }
        els.tableDetailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        els.tableDetailStatus.textContent = error.message;
        els.tableDetailBody.innerHTML = `<tr><td class="sp-empty-row" colspan="${columns.length}">Rincian gagal dimuat.</td></tr>`;
    }
}

function renderRecordDetailPage() {
    const detail = tableRecordDetailContext;
    if (!detail) return;

    const total = detail.items.length;
    const pageCount = Math.max(1, Math.ceil(total / detail.perPage));
    detail.page = Math.min(Math.max(1, detail.page), pageCount);
    const startIndex = (detail.page - 1) * detail.perPage;
    const pageItems = detail.items.slice(startIndex, startIndex + detail.perPage);
    const start = total ? startIndex + 1 : 0;
    const end = Math.min(startIndex + detail.perPage, total);

    els.tableDetailStatus.textContent = `${formatNumber(total)} judul ditemukan`;
    els.tableDetailBody.innerHTML = pageItems.length
        ? pageItems.map((item) => `<tr class="sp-row">${detail.columns.map(([key], colIndex) =>
            `<td class="sp-td${colIndex === 0 ? ' sp-td-sticky' : ''}">${escapeHtml(formatDetailValue(key, item[key]))}</td>`
        ).join('')}</tr>`).join('')
        : `<tr><td class="sp-empty-row" colspan="${detail.columns.length}">Tidak ada detail untuk kategori dan tahun ini.</td></tr>`;

    els.tableDetailPagination.innerHTML = `<div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-[12px] text-muted">Tampilkan <select id="record-detail-page-size" class="rounded-lg px-2 py-1 text-ink" style="background:var(--input-bg);border:1px solid var(--input-border)"><option value="10" ${detail.perPage === 10 ? 'selected' : ''}>10</option><option value="20" ${detail.perPage === 20 ? 'selected' : ''}>20</option><option value="50" ${detail.perPage === 50 ? 'selected' : ''}>50</option></select> data</label><span class="text-[12px] text-muted">Menampilkan ${start}–${end} dari ${formatNumber(total)} judul</span></div><div class="flex items-center gap-2"><button type="button" class="sp-page-btn" data-detail-page="${detail.page - 1}" ${detail.page <= 1 ? 'disabled' : ''}>Sebelumnya</button><span class="text-[12px] text-muted">Halaman ${detail.page} dari ${pageCount}</span><button type="button" class="sp-page-btn" data-detail-page="${detail.page + 1}" ${detail.page >= pageCount ? 'disabled' : ''}>Berikutnya</button></div>`;
}

let selectionDetailContext = null;

async function loadSelectionDetail() {
    const detail = selectionDetailContext;
    if (!detail || !els.tableDetailPanel) return;
    const params = new URLSearchParams({ tahun: String(detail.year), kategori: detail.category, page: String(detail.page), per_page: String(detail.perPage) });
    if (detail.jenisDaftar) params.set('jenis_daftar', detail.jenisDaftar);
    if (state.canSelectFakultas && state.fakultasId !== 'all') params.set('fakultas_id', state.fakultasId);
    if (state.canSelectProgramStudi && state.programStudiId !== 'all') params.set('program_studi_id', state.programStudiId);
    els.tableDetailPanel.classList.remove('hidden');
    els.tableDetailTitle.textContent = `${detail.label} · Tahun akademik ${detail.year}/${detail.year + 1}`;
    els.tableDetailStatus.textContent = 'Memuat rincian...';
    els.tableDetailHead.innerHTML = '<tr><th class="sp-th">NIM</th><th class="sp-th">Nama Mahasiswa</th><th class="sp-th">Program Studi</th><th class="sp-th">Periode</th><th class="sp-th">Jenis Daftar</th><th class="sp-th">Status</th></tr>';
    els.tableDetailBody.innerHTML = '';
    els.tableDetailPagination.innerHTML = '';
    try {
        const payload = await fetchJson(`/api/sapto/tables/seleksi-mahasiswa/detail?${params}`);
        const items = Array.isArray(payload.data) ? payload.data : [];
        const pagination = payload.pagination || { total: items.length, page: 1, last_page: 1 };
        tableDetailExportContext = {
            selection: true,
            columns: [['nim', 'NIM'], ['nama', 'Nama Mahasiswa'], ['program_studi', 'Program Studi'], ['periode', 'Periode'], ['jenis_daftar', 'Jenis Daftar'], ['status', 'Status']],
            title: els.tableDetailTitle.textContent,
        };
        els.tableDetailStatus.textContent = `${formatNumber(pagination.total)} mahasiswa ditemukan`;
        els.tableDetailBody.innerHTML = items.length
            ? items.map((item) => `<tr class="sp-row"><td class="sp-td sp-td-sticky">${escapeHtml(item.nim || '-')}</td><td class="sp-td">${escapeHtml(item.nama || '-')}</td><td class="sp-td">${escapeHtml(item.program_studi || '-')}</td><td class="sp-td">${escapeHtml(item.periode || '-')}</td><td class="sp-td">${escapeHtml(item.jenis_daftar || '-')}</td><td class="sp-td">${escapeHtml(item.status || '-')}</td></tr>`).join('')
            : '<tr><td class="sp-empty-row" colspan="6">Tidak ada rincian mahasiswa untuk pilihan ini.</td></tr>';
        renderSelectionDetailPagination(pagination);
        els.tableDetailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        els.tableDetailStatus.textContent = error.message;
        els.tableDetailBody.innerHTML = `<tr><td class="sp-empty-row" colspan="6">Rincian gagal dimuat: ${escapeHtml(error.message)}</td></tr>`;
    }
}

function renderSelectionDetailPagination(pagination) {
    const detail = selectionDetailContext;
    if (!detail || !els.tableDetailPagination) return;
    const page = Number(pagination.page || detail.page);
    const lastPage = Number(pagination.last_page || 1);
    const total = Number(pagination.total || 0);
    const start = total ? (page - 1) * detail.perPage + 1 : 0;
    const end = Math.min(page * detail.perPage, total);
    els.tableDetailPagination.innerHTML = `<div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-[12px] text-muted">Tampilkan <select id="selection-detail-page-size" class="rounded-lg px-2 py-1 text-ink" style="background:var(--input-bg);border:1px solid var(--input-border)"><option value="10" ${detail.perPage === 10 ? 'selected' : ''}>10</option><option value="20" ${detail.perPage === 20 ? 'selected' : ''}>20</option><option value="50" ${detail.perPage === 50 ? 'selected' : ''}>50</option></select> data</label><span class="text-[12px] text-muted">Menampilkan ${start}–${end} dari ${formatNumber(total)} mahasiswa</span></div><div class="flex items-center gap-2"><button type="button" class="sp-page-btn" data-detail-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Sebelumnya</button><span class="text-[12px] text-muted">Halaman ${page} dari ${lastPage}</span><button type="button" class="sp-page-btn" data-detail-page="${page + 1}" ${page >= lastPage ? 'disabled' : ''}>Berikutnya</button></div>`;
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
    cardModel = null;
    renderDosenTableBody(summary);
    refreshCards();
}

function renderDosenTableBody(summary) {
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
    const pageSize = pageSizeForCurrentTable();
    const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
    state.page = Math.min(Math.max(state.page, 1), pageCount);
    const startIndex = (state.page - 1) * pageSize;
    const pageRows = filteredRows.slice(startIndex, startIndex + pageSize);

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
    cardModel = dosenCardModel(summary, filteredRows, pageRows, startIndex);
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
    const pageSize = pageSizeForCurrentTable();
    const pageCount = Math.max(1, Math.ceil(total / pageSize));
    const start = total === 0 ? 0 : (state.page - 1) * pageSize + 1;
    const end = Math.min(state.page * pageSize, total);
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
    const pageCount = Math.max(1, Math.ceil(total / pageSizeForCurrentTable()));
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
    els.tablePage.dataset.state = 'error';
    cardModel = null;
    refreshCards();
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
    drawerDetailExportContext = null;
    showDrawer();

    try {
        const payload = await fetchJson(`/api/sapto/tables/dosen-pembimbing-ta/detail?${params}`);
        drawerDetailExportContext = {
            columns: [['nama_mahasiswa', 'Nama Mahasiswa'], ['nim', 'NIM'], ['fakultas', 'Fakultas'], ['program_studi', 'Program Studi'], ['judul_tugas_akhir', 'Judul'], ['jenis_tugas_akhir', 'Jenis'], ['tahun', 'Tahun'], ['status', 'Status'], ['sumber_data', 'Sumber Data']],
            items: Array.isArray(payload.items) ? payload.items : [],
            title: els.detailTitle.textContent,
        };
        renderDetail(payload);
    } catch (error) {
        els.detailBody.innerHTML = `<div class="text-sm text-warn">${escapeHtml(error.message)}</div>`;
    }
}

let drawerDetailExportContext = null;

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
// Dropdown prodi: dekan, rektor, admin. Dropdown fakultas: hanya rektor
// dan admin -- dekan dikunci ke fakultasnya, jadi dropdown fakultas yang
// cuma berisi satu pilihan tidak ditampilkan.
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
    els.maFakultasWrap.classList.toggle('hidden', !state.canSelectFakultas);
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
    els.maTableBody.innerHTML = skeletonRows(6);
    renderCardsLoading();
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

    if (!state.canSelectFakultas) {
        const row = (maSummary.data || [])[0];
        const fakultas = (row && row.fakultas) || (state.fakultas[0] || {}).nama;

        if (fakultas) {
            els.maActiveUnit.textContent = fakultas;
            return;
        }
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
    syncUrl();
    syncMobileAppbar();
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
    cardModel = null;
    renderMaTableBody(rows, totals);
    refreshCards();
}

function renderMaTableBody(rows, totals) {
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
    cardModel = maCardModel(rows, y);
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
    cardModel = null;
    refreshCards();
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
    drawerDetailExportContext = null;
    showDrawer();

    try {
        const payload = await fetchJson(`/sapto/mahasiswa-asing/detail?${params}`);

        if (!payload.success) {
            throw new Error(payload.message || 'Permintaan detail gagal.');
        }

        drawerDetailExportContext = {
            columns: [['nim', 'NIM'], ['nama', 'Nama Mahasiswa'], ['angkatan', 'Angkatan'], ['program_studi', 'Program Studi'], ['status', 'Status'], ['kewarganegaraan', 'Kewarganegaraan']],
            items: Array.isArray(payload.data) ? payload.data : [],
            title: `${els.detailTitle.textContent} · TS ${tahun}`,
        };
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
    const wasOpen = els.drawer.classList.contains('is-open');
    els.drawer.classList.add('is-open');
    els.drawerBackdrop.classList.add('is-open');

    // Di HP rincian ini punya entri history sendiri (tombol Kembali).
    if (!wasOpen) {
        pushOverlay('drawer');
    }
}

function hideDrawer() {
    els.drawer.classList.remove('is-open');
    els.drawerBackdrop.classList.remove('is-open');
}

function closeDrawer() {
    drawerDetailExportContext = null;
    dismissOverlay('drawer', hideDrawer);
}

// Kerjasama (Tabel 1) bersifat institusi — repository mengabaikan
// fakultas/prodi (lihat SaptoKerjasamaRepository), jadi dropdown itu tak
// menyaring apa-apa dan disembunyikan untuk tabel-tabel ini.
const INSTITUTION_WIDE_TABLES = ['kerjasama-pendidikan', 'kerjasama-penelitian', 'kerjasama-pkm', 'penggunaan-dana'];

// Tabel generik: memakai kembali #table-page (filter, kartu metrik, panel,
// pagination) tapi render dari envelope {columns, rows, metrics} backend.
const GENERIC_TABLES = {
    'rekognisi-dosen': {
        endpoint: 'rekognisi-dtps',
        unit: 'rekognisi',
        description: 'Pengakuan/rekognisi DTPS (pembicara, orasi ilmiah, penghargaan, anggota organisasi profesi, visiting scientist) pada program studi terpilih (Tabel 3.b.1). Kolom tingkat hanya terisi untuk kegiatan yang sumbernya mencantumkan tingkat.',
        icons: ['users', 'cap', 'chart', 'calendar'],
    },
    'dosen-industri': {
        endpoint: 'dosen-industri',
        unit: 'dosen',
        description: 'Dosen industri/praktisi yang mengajar pada program studi (Tabel 3.a.5, diisi program Diploma Tiga/Sarjana Terapan). Data PD UNSRI belum memuat penanda dosen industri, jadi tabel ini masih kosong.',
        icons: ['users', 'cap', 'swap', 'calendar'],
    },
    'kurikulum': {
        endpoint: 'kurikulum',
        unit: 'mata kuliah',
        description: 'Kurikulum yang berlaku pada TS beserta bobot sks dan ketersediaan RPS per mata kuliah (Tabel 5.a). Kolom konversi kredit ke jam dan capaian pembelajaran belum tersedia di sumber data.',
        icons: ['cap', 'chart', 'calendar', 'users'],
    },
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
        description: 'Jumlah lulusan per tahun lulus (TS-4/TS-3/TS-2) untuk satu program studi, mengikuti format Tabel 8.d.1 BAN-PT. Data terlacak dan waktu tunggu dilengkapi dari tracert_iku dengan pencocokan NIM; kolom Dipesan Sebelum Lulus tetap 0 karena tidak tersedia di sumber tersebut.',
        icons: ['cap', 'users', 'swap', 'calendar'],
    },
    'kesesuaian-bidang-kerja': {
        endpoint: 'kesesuaian-bidang-kerja',
        unit: 'tahun lulus',
        description: 'Jumlah lulusan dan lulusan terlacak per tahun lulus untuk satu program studi. Jumlah terlacak dicocokkan dari tracert_iku berdasarkan NIM. Sumber tidak menyatakan tingkat kesesuaian bidang kerja, sehingga kategori Rendah/Sedang/Tinggi tetap 0.',
        icons: ['cap', 'users', 'swap', 'calendar'],
    },
    'tempat-kerja': {
        endpoint: 'tempat-kerja',
        unit: 'tahun lulus',
        description: 'Jumlah lulusan dan lulusan terlacak per tahun lulus untuk satu program studi. Jumlah terlacak dicocokkan dari tracert_iku berdasarkan NIM. Sumber belum menyediakan skala tempat kerja Lokal/Nasional/Multinasional.',
        icons: ['cap', 'users', 'briefcase', 'calendar'],
    },
    'referensi-kepuasan-pengguna': {
        endpoint: 'referensi-kepuasan-pengguna',
        unit: 'tahun lulus',
        description: 'Jumlah lulusan dan alumni yang terlacak per tahun lulus, dicocokkan dari tracert_iku berdasarkan NIM. Sumber ini berisi respons tracer alumni, bukan jawaban survei kepuasan pengguna/atasan.',
        icons: ['cap', 'users', 'chart', 'calendar'],
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
    'pkm-dtps': {
        endpoint: 'pkm-dtps',
        unit: 'kategori sumber pembiayaan',
        description: 'Jumlah judul PkM (Pengabdian kepada Masyarakat) dosen tetap program studi (DTPS) per sumber pembiayaan pada TS-2/TS-1/TS, mengikuti format Tabel 3.b.3 BAN-PT -- struktur sama seperti Tabel 3.b.2 Penelitian DTPS. Klasifikasi "Lembaga Luar Negeri" berbasis skema PkM (heuristik teks, perlu diverifikasi manual); judul yang didanai lebih dari satu sumber dihitung di tiap kategori yang berlaku.',
        icons: ['briefcase', 'book', 'book', 'calendar'],
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
    'buku-dtps': {
        endpoint: 'buku-dtps',
        unit: 'judul buku/book chapter',
        description: 'Tabel 3.b.7 Bagian IV: daftar judul buku ber-ISBN & book chapter DTPS (Monograf, Buku Referensi, Buku, Book Chapter Nasional/Internasional) pada TS-2/TS-1/TS -- beda dari tabel 3.b lain, ini daftar per judul, bukan matriks hitung. Sumber sister_detail_publikasi, DTPS lewat roster homebase SISTER (sister_list_penugasan) sama seperti Tabel 3.b.4, mencakup semua jenjang (tidak dibagi akademik/vokasi).',
        icons: ['book', 'book', 'book', 'calendar'],
    },
    'teknologi-dtps': {
        endpoint: 'teknologi-dtps',
        unit: 'judul luaran',
        description: 'Tabel 3.b.7 Bagian III: daftar judul teknologi tepat guna, produk (terstandarisasi/tersertifikasi), karya seni, dan rekayasa sosial DTPS pada TS-2/TS-1/TS -- daftar per judul, sama bentuknya dengan Bagian IV (Buku ber-ISBN). Sumber sister_detail_kekayaan_intelektual, DTPS lewat roster homebase SISTER (sister_list_penugasan) sama seperti Tabel 3.b.4, mencakup semua jenjang (tidak dibagi akademik/vokasi).',
        icons: ['book', 'book', 'book', 'calendar'],
    },
    'hki-paten-dtps': {
        endpoint: 'hki-paten-dtps',
        unit: 'judul luaran',
        description: 'Tabel 3.b.7 Bagian I: daftar HKI Paten & Paten Sederhana DTPS pada TS-2/TS-1/TS.',
        icons: ['book', 'book', 'book', 'calendar'],
    },
    'hki-hak-cipta-dtps': {
        endpoint: 'hki-hak-cipta-dtps',
        unit: 'judul luaran',
        description: 'Tabel 3.b.7 Bagian II: daftar HKI Hak Cipta, Desain Produk Industri, dll. DTPS pada TS-2/TS-1/TS -- daftar per judul, sama bentuknya dengan Bagian I/III/IV. Sumber sister_detail_kekayaan_intelektual (kode hak cipta nasional/internasional, mengecualikan Produk Teknologi Tepat Guna yang sudah dihitung di Bagian III), DTPS lewat roster homebase SISTER (sister_list_penugasan) sama seperti Tabel 3.b.4, mencakup semua jenjang (tidak dibagi akademik/vokasi).',
        icons: ['book', 'book', 'book', 'calendar'],
    },
    'seleksi-mahasiswa': {
        endpoint: 'seleksi-mahasiswa',
        unit: 'tahun akademik',
        description: 'Seleksi Mahasiswa Baru (Tabel 2.a BAN-PT) per angkatan TS-4..TS. Lulus dan mahasiswa baru dihitung dari semua registrasi; mahasiswa aktif dihitung dari registrasi berstatus AKTIF. Tampil untuk seluruh universitas, dan bisa difilter per fakultas lalu per program studi. Pendaftar dan Daya Tampung belum tersedia di sumber data.',
        icons: ['users', 'cap', 'calendar', 'swap'],
    },
    'ewmp-dosen-tetap': {
        endpoint: 'ewmp-dosen',
        unit: 'dosen',
        description: 'Ekuivalen Waktu Mengajar Penuh (EWMP) dosen tetap PS dalam sks per semester pada TS, dengan perincian: EWMP Pendidikan (PS diakreditasi, PS lain di PT, PS lain di luar PT), Penelitian, PkM, dan Tugas Tambahan/Penunjang (Tabel 3.a.3). Data EWMP aktual dihitung dari SISTER bila tersedia.',
        icons: ['users', 'book', 'calendar', 'chart'],
    },
    'kepuasan-mahasiswa': {
        endpoint: 'kepuasan-mahasiswa',
        unit: 'aspek',
        description: 'Tingkat kepuasan mahasiswa terhadap layanan akademik berdasarkan 5 aspek SERVQUAL (Keandalan, Daya Tanggap, Kepastian, Empati, Bukti Fisik) dan rencana tindak lanjut UPPS/PS (Tabel 5.c BAN-PT). Data survei belum tersedia -- placeholder.',
        icons: ['users', 'chart', 'calendar', 'swap'],
    },
    'kepuasan-pengguna': {
        endpoint: 'kepuasan-pengguna',
        unit: 'kemampuan',
        description: 'Tingkat kepuasan pengguna lulusan terhadap 7 jenis kemampuan (Etika, Keahlian, Bahasa Asing, TI, Komunikasi, Kerjasama, Pengembangan Diri) dan rencana tindak lanjut (Tabel 8.e.2 BAN-PT). Data tracer study belum tersedia -- placeholder.',
        icons: ['users', 'chart', 'calendar', 'swap'],
    },
    'penggunaan-dana': {
        endpoint: 'penggunaan-dana',
        unit: 'kategori',
        description: 'Penggunaan dana perguruan tinggi per kategori (Biaya Operasional Pendidikan, Kemahasiswaan, Penelitian, PkM, Investasi SDM/Sarana/Prasarana) dan的单位 (Tabel 4 BAN-PT). Data keuangan belum tersedia -- placeholder.',
        icons: ['calendar', 'calendar', 'calendar', 'chart'],
    },
    'sitasi-dtps': {
        endpoint: 'sitasi-dtps',
        unit: 'artikel disitasi',
        description: 'Karya ilmiah DTPS yang disitasi per dosen tetap PS pada TS-2/TS-1/TS (Tabel 3.b.5 BAN-PT).',
        icons: ['book', 'chart', 'calendar', 'swap'],
    },
    'produk-jasa-dtps': {
        endpoint: 'produk-jasa-dtps',
        unit: 'produk/jasa',
        description: 'Produk/jasa DTPS yang dihasilkannya dan dapat diakses oleh industri atau masyarakat pada TS-2/TS-1/TS (Tabel 3.b.6 BAN-PT).',
        icons: ['book', 'briefcase', 'calendar', 'swap'],
    },
    'penelitian-melibatkan-mahasiswa': {
        endpoint: 'penelitian-melibatkan-mahasiswa',
        unit: 'baris penelitian/mahasiswa',
        description: 'Penelitian yang melibatkan mahasiswa pada TS-2/TS-1/TS (Tabel 6.a BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'penelitian-rujukan-tema': {
        endpoint: 'penelitian-rujukan-tema',
        unit: 'tema',
        description: 'Penelitian DTPS yang digunakan sebagai rujukan tema tesis/disertasi mahasiswa pada TS-2/TS-1/TS (Tabel 6.b BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'pkm-melibatkan-mahasiswa': {
        endpoint: 'pkm-melibatkan-mahasiswa',
        unit: 'kegiatan',
        description: 'PkM yang melibatkan mahasiswa pada TS-2/TS-1/TS (Tabel 7.a/b/c BAN-PT).',
        icons: ['briefcase', 'users', 'calendar', 'swap'],
    },
    'integrasi-pembelajaran': {
        endpoint: 'integrasi-pembelajaran',
        unit: 'mata kuliah',
        description: 'Integrasi kegiatan penelitian/PkM ke dalam pembelajaran pada TS (Tabel 5.b BAN-PT).',
        icons: ['cap', 'book', 'calendar', 'swap'],
    },
    'sitasi-mahasiswa': {
        endpoint: 'sitasi-mahasiswa',
        unit: 'artikel disitasi',
        description: 'Karya ilmiah mahasiswa (skripsi/tugas akhir) yang disitasi pada TS-2/TS-1/TS (Tabel 8.f.2 BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'produk-jasa-mahasiswa': {
        endpoint: 'produk-jasa-mahasiswa',
        unit: 'produk/jasa',
        description: 'Produk/jasa mahasiswa yang dihasilkan dari kegiatan penelitian/PkM dan dapat diakses oleh industri atau masyarakat (Tabel 8.f.3 BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'hki-paten-mahasiswa': {
        endpoint: 'hki-paten-mahasiswa',
        unit: 'paten',
        description: 'Luaran Penelitian/PkM -- HKI Paten dan Paten Sederhana mahasiswa (Tabel 8.f.4.I BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'hki-hak-cipta-mahasiswa': {
        endpoint: 'hki-hak-cipta-mahasiswa',
        unit: 'HKI',
        description: 'Luaran Penelitian/PkM -- HKI Hak Cipta, Desain Produk Industri, dll. mahasiswa (Tabel 8.f.4.II BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'teknologi-mahasiswa': {
        endpoint: 'teknologi-mahasiswa',
        unit: 'luaran',
        description: 'Luaran Penelitian/PkM -- Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial mahasiswa (Tabel 8.f.4.III BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'buku-mahasiswa': {
        endpoint: 'buku-mahasiswa',
        unit: 'buku',
        description: 'Luaran Penelitian/PkM -- Buku Ber-ISBN dan Book Chapter mahasiswa (Tabel 8.f.4.IV BAN-PT).',
        icons: ['book', 'users', 'calendar', 'swap'],
    },
    'publikasi-mahasiswa': {
        endpoint: 'publikasi-mahasiswa',
        unit: 'jenis publikasi',
        description: 'Untuk program AKADEMIK (Sarjana/Magister/Doktor). Jumlah judul publikasi yang mencantumkan mahasiswa program studi ini sebagai penulis di SISTER, dengan baris yang sama seperti Tabel 3.b.4. Angka minimal: hanya publikasi yang daftar penulisnya tersedia di PD UNSRI yang bisa diperiksa.',
        icons: ['book', 'book', 'book', 'calendar'],
    },
    'pagelaran-mahasiswa': {
        endpoint: 'pagelaran-mahasiswa',
        unit: 'jenis publikasi',
        description: 'Untuk program TERAPAN (Sarjana Terapan/Magister Terapan/Doktor Terapan). Jumlah judul publikasi yang mencantumkan mahasiswa program studi ini sebagai penulis di SISTER. Baris pagelaran/pameran selalu 0 karena jenis itu tidak ada di SISTER -- isi manual.',
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
    closeTableDetail();
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
    els.pageTitle.textContent = table?.name || (tableId === 'beranda' ? 'Beranda' : 'SAPTO');
    updateBreadcrumb(table);

    els.tablePage.classList.add('hidden');
    els.maPage.classList.add('hidden');
    els.placeholderPage.classList.add('hidden');
    els.dashboardPage.classList.add('hidden');
    mEls.pages.forEach((page) => page.classList.add('hidden'));
    resetMobileOverlays();

    // Filter bar dipakai bersama keluarga tabel "table-page"
    // (dosen-pembimbing-ta + tabel generik); mahasiswa-asing punya filter
    // sendiri, placeholder tidak butuh filter sama sekali.
    // Beranda desktop memakai filter yang sama (TS/fakultas/prodi) untuk
    // seluruh ringkasannya; Beranda HP tidak (filternya dilipat di app bar).
    const usesSharedFilterBar = tableId === 'dosen-pembimbing-ta' || isGeneric(tableId)
        || (tableId === 'beranda' && !mobileMq.matches);
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

    syncUrl();
    syncMobileShell();
    window.scrollTo(0, 0);
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

// ==== SAPTO Mobile (< 640px) ====
//
// Tampilan HP: tabel sebagai kartu + bottom sheet, dan "shell" tiga tab
// (Beranda, Tabel, Akun) dengan app bar di halaman tabel. Markup di
// components/mobile-*.blade.php & card-sheet.blade.php, gaya di bagian
// "SAPTO Mobile" app.css. Semua elemennya display:none di >= sm, jadi
// jalur desktop tidak tersentuh; fungsi di sini hanya dipanggil dari
// hook kecil di activateTable()/render*Table()/syncUrl().

// ---------- Kartu baris ----------
//
// Kartu memakai kolom & baris yang SAMA dengan tabel. Render tabel mengisi
// `cardModel` di akhir jalur suksesnya lalu wrapper-nya memanggil
// refreshCards(); jalur non-sukses (kosong, catatan, error) tidak punya
// model, dan pesan yang mereka tulis ke <tbody> disalin apa adanya.
//
// Peran kolom dideduksi dari ISI kolom (45 tabel, tanpa konfigurasi per
// tabel):
//   title   kolom teks pertama yang berisi nama/kalimat
//   sub     kolom teks pendek & hampir selalu terisi (NIDN, tahun, ...)
//   metrics kolom angka, diutamakan nilai TS berjalan & jumlah/rata-rata;
//           TS-1/TS-2 hanya dipakai bila tak ada yang lebih penting
//   checks  kolom tanda centang (✓) -- digabung jadi satu nilai,
//           mis. tiga kolom tingkat kerjasama -> "Tingkat: Nasional"
//   facts   sisa kolom teks, dipakai bila metrik kurang dari empat
// Kolom "No" tidak ditampilkan di kartu. Semua kolom tetap ada di sheet.
const TS_CUR_RE = /\bTS\b(?!\s*-)/i;
const TS_1_RE = /\bTS-1\b/i;
const TS_2_RE = /\bTS-2\b/i;
const CHECK_RE = /^[✓✔]$/;
const VIEW_PREF_KEY = 'sapto.tableView';
const M_CHEVRON = '<svg class="m-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
const sheetState = { index: -1, opener: null };

const isBlank = (v) => v === null || v === undefined || String(v).trim() === '' || String(v).trim() === '-';

function cardRoles(cols, rows, override = {}) {
    const sample = rows.slice(0, 40);
    const filled = (c) => sample.filter((r) => !isBlank(r[c.key]));
    const byKey = (key) => cols.find((c) => c.key === key) || null;
    const no = cols.find((c) => /^no\.?$/i.test(String(c.label).trim())) || null;
    const checks = cols.filter((c) => c !== no && c.align !== 'num' && filled(c).length > 0
        && filled(c).every((r) => CHECK_RE.test(String(r[c.key]).trim())));
    const text = cols.filter((c) => c !== no && c.align !== 'num' && !checks.includes(c));
    // Nama/kalimat mengandung spasi; kode (NIDN, kode MK "BDB7009") tidak.
    const nameLike = (c) => {
        const f = filled(c);

        return f.length > 0 && f.filter((r) => String(r[c.key]).trim().includes(' ')).length >= f.length / 2;
    };
    const avgLen = (c) => {
        const f = filled(c);

        return f.length ? f.reduce((n, r) => n + String(r[c.key]).length, 0) / f.length : Infinity;
    };

    const title = override.title ? byKey(override.title) : (text.find(nameLike) || text[0] || cols.find((c) => c !== no) || null);
    const rest = text.filter((c) => c !== title);
    const sub = override.sub !== undefined
        ? byKey(override.sub)
        : (rest.find((c) => filled(c).length >= Math.max(1, sample.length / 2) && avgLen(c) <= 40) || null);

    let metrics;
    if (override.metrics) {
        metrics = override.metrics.map(byKey).filter(Boolean);
    } else {
        const score = (c) => {
            const label = String(c.label);
            let s = 0;
            if (TS_CUR_RE.test(label)) s += 3;
            if (TS_1_RE.test(label) || TS_2_RE.test(label)) s -= 2;
            if (/jumlah|total|rata/i.test(label)) s += 1;

            return s;
        };
        const ranked = cols.filter((c) => c.align === 'num' && c !== no)
            .map((c, i) => ({ c, i, s: score(c) }))
            .sort((a, b) => b.s - a.s || a.i - b.i);
        const important = ranked.filter((x) => x.s >= 0);
        metrics = (important.length >= 2 ? important : ranked).slice(0, 4)
            .sort((a, b) => a.i - b.i).map((x) => x.c);
    }

    return { no, title, sub, metrics, checks, facts: rest.filter((c) => c !== sub) };
}

function cardValue(row, col) {
    const value = row[col.key];

    if (isBlank(value)) {
        return '–';
    }

    if (col.align === 'num') {
        return formatNumber(value);
    }

    const text = String(value).trim();

    return CHECK_RE.test(text) ? 'Ya' : text;
}

const cardTitle = (model, row) => (model.roles.title ? cardValue(row, model.roles.title) : '–');

function cardSub(model, row) {
    const col = model.roles.sub;

    if (!col || isBlank(row[col.key])) {
        return '';
    }

    const value = cardValue(row, col);

    // Kode (NIDN, nomor) atau nilai sangat pendek ("1") tak bermakna
    // tanpa labelnya: "Semester 1", "NIDN/NIDK 0029096608".
    return value.length <= 4 || (/^[\w./-]{3,24}$/.test(value) && /\d/.test(value)) ? `${col.label} ${value}` : value;
}

function shortLabel(col) {
    const clip = (text, max) => (text.length > max ? `${text.slice(0, max).replace(/\s+\S*$/, '')}…` : text);

    if (col.short) {
        return `${col.short} · ${col.label}`;
    }

    // Nama grup hanya ditambahkan bila label sendiri ambigu ("TS",
    // "Rata-rata"); label yang sudah jelas ("PS yang Diakreditasi") tidak
    // perlu diawali judul grup panjang.
    const ambiguous = String(col.label).length <= 8 || /^(TS\b|rata-rata|jumlah)/i.test(col.label);

    return col.group && ambiguous ? `${clip(col.group, 32)} · ${col.label}` : col.label;
}

// Kolom centang digabung: satu kolom -> "Ya/Tidak"; beberapa -> label
// kolom yang dicentang (mis. tingkat kerjasama).
function checkItem(roles, row) {
    if (!roles.checks.length) {
        return null;
    }

    if (roles.checks.length === 1) {
        const c = roles.checks[0];

        return { label: c.label, value: isBlank(row[c.key]) ? 'Tidak' : 'Ya' };
    }

    const on = roles.checks.filter((c) => !isBlank(row[c.key])).map((c) => c.label);
    const labels = roles.checks.map((c) => c.label).join(' ');
    const label = roles.checks[0].group
        || (/internasional|nasional|wilayah|lokal/i.test(labels) ? 'Tingkat' : 'Kategori');

    return { label, value: on.length ? on.join(', ') : '–' };
}

function cardItems(model, row) {
    const { roles } = model;
    const items = roles.metrics.map((c) => ({ label: shortLabel(c), value: cardValue(row, c), num: true }));
    const check = checkItem(roles, row);

    if (check && items.length < 4) {
        items.push(check);
    }

    for (const c of roles.facts) {
        if (items.length >= 4) {
            break;
        }

        if (!isBlank(row[c.key]) && String(row[c.key]).length <= 120) {
            items.push({ label: c.label, value: cardValue(row, c) });
        }
    }

    return items;
}

function kvHtml(items) {
    return items.length ? `
        <span class="m-kv">
            ${items.map((it) => `
                <span class="m-kv-item">
                    <span class="m-kv-label">${escapeHtml(it.label)}</span>
                    <span class="m-kv-value${it.num ? ' is-num' : ''}${it.value === '–' ? ' is-muted' : ''}">${escapeHtml(it.value)}</span>
                </span>`).join('')}
        </span>` : '';
}

function cardHtml(model, row, index) {
    const sub = cardSub(model, row);

    return `
        <button type="button" class="m-row-card" data-card-index="${index}">
            <span class="m-row-card-head">
                <span class="m-row-card-titles">
                    <span class="m-row-card-title">${escapeHtml(cardTitle(model, row))}</span>
                    ${sub ? `<span class="m-row-card-sub">${escapeHtml(sub)}</span>` : ''}
                </span>
                ${M_CHEVRON}
            </span>
            ${kvHtml(cardItems(model, row))}
        </button>
    `;
}

// Tabel Mahasiswa Asing punya panel sendiri, jadi daftar kartunya juga.
const cardsTarget = () => (currentTableId === 'mahasiswa-asing' ? cardEls.maCards : cardEls.cards);

function refreshCards() {
    const target = cardsTarget();

    if (!target) {
        return;
    }

    // Petunjuk "ketuk kartu" hanya tampil bila memang ada kartu.
    target.closest('section').dataset.hasRows = cardModel ? '1' : '';

    if (cardModel) {
        target.innerHTML = cardModel.pageRows.map((row, i) => cardHtml(cardModel, row, cardModel.start + i)).join('');

        return;
    }

    const body = currentTableId === 'mahasiswa-asing' ? els.maTableBody : els.tableBody;
    const alert = body.querySelector('.sp-alert-warn');
    const cell = body.querySelector('td[colspan], td.sp-empty-row');
    target.innerHTML = alert ? alert.outerHTML : (cell ? `<div class="m-empty">${cell.innerHTML}</div>` : '');
}

function renderCardsLoading() {
    hideSheet();
    cardModel = null;
    cardsTarget().innerHTML = Array.from({ length: 3 }).map(() => `
        <div class="m-row-card" aria-hidden="true">
            <div class="m-skel" style="width:70%"></div>
            <div class="m-skel" style="width:40%"></div>
            <div class="m-skel" style="width:90%;margin-top:6px"></div>
        </div>
    `).join('');
}

// Tabel Dosen Pembimbing: bentuk barisnya bukan columns/rows generik
// (angka bersarang di ps_diakreditasi/ps_lain_pt), jadi diratakan ke
// kolom-kolom yang sama seperti header tabelnya. `chip` menandai kolom
// yang di tabel berupa tombol angka pembuka rincian mahasiswa; di sheet
// tombol itu dipertahankan.
function dosenCardModel(summary, filteredRows, pageRows, start) {
    const y = summary.year_columns;
    const G_PS = 'Jumlah Mahasiswa yang Dibimbing pada PS yang Diakreditasi';
    const G_LAIN = 'Jumlah Mahasiswa yang Dibimbing pada PS Lain di PT';
    const yearCols = (prefix, group, short, scope) => [['ts_2', 'TS-2'], ['ts_1', 'TS-1'], ['ts', 'TS']].map(([k, lbl]) => ({
        key: `${prefix}_${k}`, label: `${lbl} (${y[k]})`, align: 'num', group, short, chip: { scope, yearKey: k },
    }));
    const cols = [
        { key: 'no', label: 'No' },
        { key: 'nama_dosen', label: 'Nama Dosen' },
        { key: 'identifier', label: 'NIDN/NIDK/NIP' },
        ...yearCols('a', G_PS, 'PS diakreditasi', 'ps_diakreditasi'),
        { key: 'a_rata', label: 'Rata-rata', align: 'num', group: G_PS, short: 'PS diakreditasi' },
        ...yearCols('b', G_LAIN, 'PS lain di PT', 'ps_lain_pt'),
        { key: 'b_rata', label: 'Rata-rata', align: 'num', group: G_LAIN, short: 'PS lain di PT' },
        { key: 'avg', label: 'Rata-rata bimbingan di semua PS', align: 'num' },
    ];
    const flatten = (r) => ({
        dosen_id: r.dosen_id,
        no: r.no,
        nama_dosen: r.nama_dosen,
        identifier: r.identifier,
        a_ts_2: r.ps_diakreditasi.ts_2, a_ts_1: r.ps_diakreditasi.ts_1, a_ts: r.ps_diakreditasi.ts, a_rata: r.ps_diakreditasi.rata_rata,
        b_ts_2: r.ps_lain_pt.ts_2, b_ts_1: r.ps_lain_pt.ts_1, b_ts: r.ps_lain_pt.ts, b_rata: r.ps_lain_pt.rata_rata,
        avg: r.rata_rata_semua_ps,
    });
    const rows = filteredRows.map(flatten);

    return {
        cols,
        rows,
        roles: cardRoles(cols, rows, { title: 'nama_dosen', sub: 'identifier', metrics: ['a_ts', 'b_ts', 'a_rata', 'avg'] }),
        start,
        pageRows: pageRows.map(flatten),
    };
}

// Tabel Mahasiswa Asing (2.b): diratakan ke kolom header tabelnya. Hanya
// kolom Full-time yang berupa tombol rincian (lihat komentar di
// mahasiswa-asing-panel.blade.php); di sheet dipertahankan lewat chip.ma.
function maCardModel(rows, y) {
    const G_AKTIF = 'Jumlah Mahasiswa Aktif';
    const G_FT = 'Jumlah Mahasiswa Asing Penuh Waktu (Full-time)';
    const G_PT = 'Jumlah Mahasiswa Asing Paruh Waktu (Part-time)';
    const yearCols = (prefix, group, short, clickable) => [['ts_2', 'TS-2', 'ts2'], ['ts_1', 'TS-1', 'ts1'], ['ts', 'TS', 'ts']].map(([k, lbl, suffix]) => ({
        key: `${prefix}${suffix}`, label: `${lbl} (${y[k]})`, align: 'num', group, short, chip: clickable ? { ma: true, tahun: y[k] } : null,
    }));
    const cols = [
        { key: 'no', label: 'No' },
        { key: 'program_studi', label: 'Program Studi' },
        ...yearCols('mhs_aktif_', G_AKTIF, 'Mahasiswa aktif', false),
        ...yearCols('asing_ft_', G_FT, 'Asing penuh waktu', true),
        ...yearCols('asing_pt_', G_PT, 'Asing paruh waktu', false),
    ];
    const numbered = rows.map((row, index) => ({ ...row, no: index + 1 }));

    return {
        cols,
        rows: numbered,
        roles: cardRoles(cols, numbered, { title: 'program_studi', sub: null, metrics: ['mhs_aktif_ts', 'asing_ft_ts', 'asing_pt_ts'] }),
        start: 0,
        pageRows: numbered,
    };
}

// ---------- Bottom sheet detail baris ----------

// Tren tiga TS: tiga kolom angka berurutan berlabel TS-2, TS-1, TS
// dalam grup yang sama, digambar sebagai tiga batang kecil.
function sheetTrendsHtml(row, cols) {
    const blocks = [];

    for (let i = 0; i + 2 < cols.length; i += 1) {
        const [a, b, c] = [cols[i], cols[i + 1], cols[i + 2]];

        if (![a, b, c].every((x) => x.align === 'num' && (x.group || null) === (a.group || null))
            || !TS_2_RE.test(a.label) || !TS_1_RE.test(b.label) || !TS_CUR_RE.test(c.label)) {
            continue;
        }

        const values = [a, b, c].map((x) => Number(row[x.key]) || 0);
        const max = Math.max(...values, 0);
        const title = a.short || a.group || String(a.label).replace(/\s*\bTS-2\b.*$/i, '') || 'Jumlah';

        blocks.push(`
            <div class="m-trend">
                <div class="m-trend-title">${escapeHtml(title)}</div>
                <div class="m-trend-bars">
                    ${values.map((v, k) => `
                        <div class="m-trend-col ${k === 2 ? 'is-current' : ''}">
                            <span class="m-trend-val">${escapeHtml(formatNumber(v))}</span>
                            <span class="m-trend-bar" style="height:${max > 0 ? Math.max(3, Math.round((v / max) * 40)) : 3}px"></span>
                        </div>`).join('')}
                </div>
                <div class="m-trend-lbls"><span>TS-2</span><span>TS-1</span><span>TS</span></div>
            </div>
        `);
        i += 2;
    }

    return blocks.length ? `<h4 class="m-sheet-section">Tren tiga tahun</h4><div class="m-trends">${blocks.join('')}</div>` : '';
}

function sheetValueHtml(row, col) {
    const text = escapeHtml(cardValue(row, col));

    if (isClickableDetail(row, col)) {
        return detailTrigger(row, col, text);
    }

    if (col.chip?.ma) {
        return `<button type="button" class="sp-number-chip" data-id-prodi="${escapeAttr(row.id_prodi || '')}" data-program-studi="${escapeAttr(row.program_studi || '')}" data-tahun="${escapeAttr(col.chip.tahun)}">${text}</button>`;
    }

    if (col.chip) {
        return `<button type="button" class="sp-number-chip" data-dosen-id="${escapeAttr(row.dosen_id)}" data-dosen-name="${escapeAttr(row.nama_dosen || '')}" data-scope="${col.chip.scope}" data-year-key="${col.chip.yearKey}">${text}</button>`;
    }

    return text;
}

function sheetListHtml(row, cols) {
    let out = '';
    let lastGroup = null;
    let open = false;

    cols.filter((c) => c !== cardModel.roles.no).forEach((c) => {
        const group = c.group || null;

        if (group !== lastGroup) {
            out += open ? '</dl>' : '';
            out += group ? `<h5 class="m-dl-group">${escapeHtml(group)}</h5>` : '';
            out += '<dl class="m-dl">';
            open = true;
            lastGroup = group;
        } else if (!open) {
            out += '<dl class="m-dl">';
            open = true;
        }

        out += `<div><dt>${escapeHtml(c.label)}</dt><dd>${sheetValueHtml(row, c)}</dd></div>`;
    });

    return `<h4 class="m-sheet-section">Semua kolom</h4>${out}${open ? '</dl>' : ''}`;
}

function renderSheet() {
    const row = cardModel?.rows[sheetState.index];

    if (!row) {
        hideSheet();

        return;
    }

    cardEls.sheetTitle.textContent = cardTitle(cardModel, row);
    cardEls.sheetSubtitle.textContent = cardSub(cardModel, row);
    cardEls.sheetPosition.textContent = `${sheetState.index + 1} dari ${cardModel.rows.length}`;
    cardEls.sheetPrev.disabled = sheetState.index <= 0;
    cardEls.sheetNext.disabled = sheetState.index >= cardModel.rows.length - 1;
    cardEls.sheetBody.innerHTML = sheetTrendsHtml(row, cardModel.cols) + sheetListHtml(row, cardModel.cols);
    cardEls.sheetBody.scrollTop = 0;
}

function openSheet(index, { push = true } = {}) {
    if (!cardModel?.rows[index]) {
        return;
    }

    sheetState.index = index;
    sheetState.opener = document.activeElement;
    renderSheet();
    cardEls.sheet.classList.add('is-open');
    cardEls.sheetBackdrop.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    if (push) {
        pushOverlay('sheet');
    }

    cardEls.sheetClose.focus();
}

function hideSheet(restoreFocus = false) {
    if (!cardEls.sheet.classList.contains('is-open')) {
        return;
    }

    cardEls.sheet.classList.remove('is-open');
    cardEls.sheetBackdrop.classList.remove('is-open');
    document.body.style.overflow = '';
    sheetState.index = -1;

    if (restoreFocus && sheetState.opener?.isConnected) {
        sheetState.opener.focus({ preventScroll: true });
    }
    sheetState.opener = null;
}

// Ditutup oleh pengguna (tombol, latar, Esc): lewat history bila sheet
// punya entri sendiri, supaya tombol Kembali HP tetap sinkron.
const dismissSheet = () => dismissOverlay('sheet', () => hideSheet(true));

// Pindah baris lewat tombol di sheet juga memindah halaman kartu di
// belakangnya bila baris itu ada di halaman lain.
function stepSheet(delta) {
    const next = sheetState.index + delta;

    if (!cardModel?.rows[next]) {
        return;
    }

    sheetState.index = next;
    renderSheet();
}

// ---------- Mode tampilan Kartu / Tabel ----------
function applyViewMode(mode) {
    cardEls.panel.dataset.view = mode;
    cardEls.maPanel.dataset.view = mode;
    cardEls.viewButtons.forEach((button) => {
        button.textContent = mode === 'tabel' ? 'Lihat sebagai kartu' : 'Lihat sebagai tabel';
    });
}

function setViewMode(mode) {
    applyViewMode(mode);

    try {
        localStorage.setItem(VIEW_PREF_KEY, mode);
    } catch (error) {
        // Penyimpanan diblokir: pilihan tetap berlaku untuk sesi ini.
    }
}

function readViewPref() {
    try {
        return localStorage.getItem(VIEW_PREF_KEY) === 'tabel' ? 'tabel' : 'kartu';
    } catch (error) {
        return 'kartu';
    }
}

// ---------- Riwayat (tombol Kembali HP) ----------
//
// Di HP, pindah halaman dan membuka sheet/rincian menambah entri
// history, jadi tombol Kembali Android/iOS menutup lapisan teratas atau
// kembali ke halaman sebelumnya -- bukan keluar dari SAPTO. Desktop tetap
// memakai replaceState saja (lihat syncUrl), tidak ada yang berubah.
// state = { sapto: <id halaman>, overlays: ['sheet' | 'drawer', ...] }
const mobileMq = window.matchMedia('(max-width: 639.98px)');
let navDepth = 0;

function pushOverlay(name) {
    if (!mobileMq.matches) {
        return;
    }

    const current = history.state || {};
    history.pushState({ ...current, sapto: currentTableId, overlays: [...(current.overlays || []), name] }, '', location.href);
    navDepth += 1;
}

function dismissOverlay(name, hide) {
    const overlays = history.state?.overlays || [];

    if (mobileMq.matches && overlays[overlays.length - 1] === name) {
        history.back();
    } else {
        hide();
    }
}

// Pindah halaman dari kontrol mobile (tab bawah, daftar, beranda).
function go(page, { replace = false } = {}) {
    if (page === currentTableId) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    if (replace) {
        history.replaceState({ sapto: page }, '', location.href);
    } else {
        history.pushState({ sapto: page }, '', location.href);
        navDepth += 1;
    }

    activateTable(page);
}

window.addEventListener('popstate', (event) => {
    if (!mobileMq.matches) {
        return;
    }

    navDepth = Math.max(0, navDepth - 1);
    const st = event.state || {};
    const overlays = st.overlays || [];

    if (!overlays.includes('drawer')) hideDrawer();
    if (!overlays.includes('sheet')) hideSheet(true);

    if (st.sapto && st.sapto !== currentTableId) {
        activateTable(st.sapto);
    }
});

// ---------- Shell: halaman virtual ----------
//
// Beranda, daftar tabel, dan Akun adalah "halaman virtual": ikut
// TABLE_HANDLERS/activateTable() seperti tabel biasa, tetapi bukan tabel
// BAN-PT (tidak ada di `tables`, tidak ditulis ke URL).
const VIRTUAL_PAGES = ['beranda', 'tabel-picker', 'akun'];
const RECENT_KEY = 'sapto.recentTables';
const KESIAPAN_KEY = 'sapto.kesiapan.v3:';
const mEls = {
    beranda: document.getElementById('beranda-page'),
    picker: document.getElementById('tabel-picker-page'),
    akun: document.getElementById('akun-page'),
    navItems: document.querySelectorAll('.m-nav-item'),
    apiSlot: document.getElementById('m-api-slot'),
    greet: document.getElementById('m-greet'),
    unit: document.getElementById('m-unit'),
    ringArc: document.getElementById('m-ring-arc'),
    ringPct: document.getElementById('m-ring-pct'),
    readyTitle: document.getElementById('m-ready-title'),
    readySync: document.getElementById('m-ready-sync'),
    readyStats: document.getElementById('m-ready-stats'),
    attn: document.getElementById('m-attn'),
    attnTitle: document.getElementById('m-attn-title'),
    criteria: document.getElementById('m-criteria'),
    recentWrap: document.getElementById('m-recent-wrap'),
    recent: document.getElementById('m-recent'),
    pickerSearch: document.getElementById('m-picker-search'),
    pickerList: document.getElementById('m-picker-list'),
    pickerSegments: document.querySelectorAll('#tabel-picker-page [data-picker-filter]'),
    countSemua: document.getElementById('m-count-semua'),
    countKosong: document.getElementById('m-count-kosong'),
    themeLabel: document.getElementById('m-account-theme-label'),
    title: document.getElementById('m-title'),
    subtitle: document.getElementById('m-subtitle'),
    unitStatic: document.getElementById('m-unit-static'),
};
mEls.pages = [mEls.beranda, mEls.picker, mEls.akun];

const kesiapan = { data: null, key: '', error: null, byId: {}, loading: null };
const picker = { filter: 'semua', scrollGroup: null };

const isVirtual = (id) => VIRTUAL_PAGES.includes(id);
const tableById = (id) => tables.find((t) => t.id === id) || null;
const groupOf = (t) => String(t.code).split('.')[0];

// "Tabel 3.a.1) Dosen Tetap Perguruan Tinggi" -> "Dosen Tetap Perguruan
// Tinggi": nomornya sudah tampil terpisah sebagai kode.
function shortTableName(table) {
    const stripped = table.name.replace(/^Tabel(?:\s+Referensi)?\s+[0-9][0-9a-z.]*\)?\s*/i, '').trim();

    return stripped || table.name;
}

const mSvg = (inner) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${inner}</svg>`;
const M_ICON = {
    warn: mSvg('<path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>'),
    check: mSvg('<path d="M20 6 9 17l-5-5"/>'),
};
const M_GROUP_ICONS = {
    1: '<path d="M15 7h3a5 5 0 0 1 0 10h-3m-6 0H6A5 5 0 0 1 6 7h3"/><line x1="8" y1="12" x2="16" y2="12"/>',
    2: '<path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/>',
    3: '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    4: '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    5: '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    6: '<path d="M9 2v6.5L4 18a2 2 0 0 0 1.8 3h12.4a2 2 0 0 0 1.8-3l-5-9.5V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/>',
    7: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
    8: '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>',
};
const groupIcon = (g) => mSvg(M_GROUP_ICONS[g] || M_GROUP_ICONS[3]);

function relTime(iso) {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return null;
    }

    const minutes = Math.max(0, Math.round((Date.now() - then) / 60000));

    if (minutes < 1) return 'baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    if (minutes < 60 * 24) return `${Math.round(minutes / 60)} jam lalu`;

    return `${Math.round(minutes / (60 * 24))} hari lalu`;
}

function storageGet(key, fallback) {
    try {
        const raw = localStorage.getItem(key);

        return raw === null ? fallback : JSON.parse(raw);
    } catch (error) {
        return fallback;
    }
}

function storageSet(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        // Penyimpanan diblokir (mode privat/kuota): fitur sampingan saja.
    }
}

const readRecent = () => (Array.isArray(storageGet(RECENT_KEY, [])) ? storageGet(RECENT_KEY, []) : []).filter((id) => tableById(id));
const rememberRecent = (id) => storageSet(RECENT_KEY, [id, ...readRecent().filter((x) => x !== id)].slice(0, 5));

// Baris daftar standar: ikon/kode, judul, keterangan, sisi kanan.
function listRow({ attrs = '', lead = '', title, sub = '', trail = M_CHEVRON, extra = '' }) {
    return `
        <button type="button" class="m-list-row" ${attrs}>
            ${lead}
            <span class="m-row-main">
                <span class="m-row-title">${title}</span>
                ${sub ? `<span class="m-row-sub">${sub}</span>` : ''}
                ${extra}
            </span>
            ${trail}
        </button>`;
}

function tableRow(t, sub = '') {
    const st = kesiapan.byId[t.id]?.status;
    const status = {
        kosong: '<span class="m-status"><i class="m-dot is-empty"></i>Belum terisi</span>',
        perlu_prodi: '<span class="m-status">Pilih prodi</span>',
        tidak_wajib: '<span class="m-status">Tidak wajib</span>',
    }[st] || '';
    const trail = status + M_CHEVRON;

    return listRow({
        attrs: `data-table-id="${escapeAttr(t.id)}"`,
        lead: `<span class="m-code">${escapeHtml(t.code)}</span>`,
        title: escapeHtml(shortTableName(t)),
        sub,
        trail,
    });
}

// ---------- App bar & filter ----------
//
// Chip filter mencerminkan select filter desktop (TS, fakultas, prodi)
// yang sedang TAMPIL untuk halaman ini. Perubahan diteruskan ke select
// asli lalu event change-nya dipicu, jadi logika muat ulang tetap satu.
const M_FILTERS = [
    { key: 'ts', label: (t) => `Tahun TS ${t}`, src: () => (currentTableId === 'mahasiswa-asing' ? els.maTs : els.ts), wrap: () => null },
    { key: 'fak', label: (t) => t, src: () => (currentTableId === 'mahasiswa-asing' ? els.maFakultas : els.fakultas), wrap: () => (currentTableId === 'mahasiswa-asing' ? els.maFakultasWrap : els.fakultasWrap) },
    { key: 'prodi', label: (t) => t, src: () => (currentTableId === 'mahasiswa-asing' ? els.maProgramStudi : els.programStudi), wrap: () => (currentTableId === 'mahasiswa-asing' ? els.maProgramStudiWrap : els.programStudiWrap) },
];

function syncMobileFilters() {
    let prodiShown = false;

    M_FILTERS.forEach((f) => {
        const chip = document.getElementById(`m-${f.key}-wrap`);
        const select = document.getElementById(`m-${f.key}`);
        const label = document.getElementById(`m-${f.key}-label`);
        const src = f.src();
        const wrap = f.wrap();
        const shown = Boolean(src && src.options.length && (!wrap || !wrap.classList.contains('hidden')));

        chip.hidden = !shown;

        if (!shown) {
            return;
        }

        if (f.key === 'prodi') prodiShown = true;
        select.innerHTML = src.innerHTML;
        select.value = src.value;
        label.textContent = f.label(src.selectedOptions[0]?.textContent.trim() || '-');
    });

    const unitEl = currentTableId === 'mahasiswa-asing' ? els.maActiveUnit : els.activeUnit;
    mEls.unitStatic.hidden = prodiShown;
    mEls.unitStatic.textContent = unitEl?.textContent.trim() || '';
}

function syncMobileAppbar() {
    if (isVirtual(currentTableId)) {
        return;
    }

    const table = tableById(currentTableId);
    const group = table ? GROUP_LABELS[groupOf(table)] : null;

    mEls.title.textContent = table ? shortTableName(table) : 'SAPTO';
    mEls.subtitle.textContent = table ? `Tabel ${table.code}${group ? ` · ${group}` : ''}` : '';
    syncMobileFilters();
}

function syncMobileShell() {
    const virtual = isVirtual(currentTableId);
    const activeNav = { beranda: 'beranda', akun: 'akun' }[currentTableId] || 'tabel';

    document.body.dataset.mobilePage = virtual ? currentTableId : 'tabel';
    mEls.navItems.forEach((item) => {
        if (item.dataset.nav === activeNav) {
            item.setAttribute('aria-current', 'page');
        } else {
            item.removeAttribute('aria-current');
        }
    });

    if (!virtual) {
        rememberRecent(currentTableId);
    }

    syncMobileAppbar();
}

// Dipanggil di awal activateTable(): lapisan dari halaman sebelumnya
// tidak boleh tertinggal di atas halaman baru.
function resetMobileOverlays() {
    hideSheet();
}

// ---------- Beranda ----------
function kesiapanUrl() {
    const params = new URLSearchParams();

    if (state.ts !== null) params.set('ts', state.ts);
    if (state.canSelectFakultas && state.fakultasId !== 'all') params.set('fakultas_id', state.fakultasId);
    if (state.canSelectProgramStudi && state.programStudiId !== 'all') params.set('program_studi_id', state.programStudiId);

    return `/api/sapto/kesiapan?${params}`;
}

function setKesiapan(data, key) {
    kesiapan.data = data;
    kesiapan.key = key;
    kesiapan.error = null;
    kesiapan.byId = Object.fromEntries((data.tables || []).map((e) => [e.id, e]));
}

// Stale-while-revalidate: hasil terakhir (localStorage) langsung tampil,
// lalu diperbarui dari server. Perhitungan 45 tabel bisa ~8 detik saat
// cache server dingin; tanpa ini Beranda kosong selama itu.
function ensureKesiapan(force = false) {
    const key = kesiapanUrl();

    if (!kesiapan.data || kesiapan.key !== key) {
        const cached = storageGet(KESIAPAN_KEY + key, null);

        if (cached?.tables) {
            setKesiapan(cached, key);
        }
    }

    if (!force && kesiapan.key === key && kesiapan.fresh === key) {
        return Promise.resolve();
    }

    if (kesiapan.loading?.key === key) {
        return kesiapan.loading.promise;
    }

    const promise = fetchJson(key)
        .then((data) => {
            // Kalau TS baru terisi (dari dashboard) SETELAH permintaan ini
            // dikirim dan sama dengan yang dijawab server, simpan dengan
            // kunci yang membawa ts: pemanggilan berikutnya tidak menghitung
            // ulang 45 tabel (~8 dtk saat cache server dingin) tanpa perlu.
            const settled = state.ts !== null && state.ts === data.ts ? kesiapanUrl() : key;
            setKesiapan(data, settled);
            kesiapan.fresh = settled;
            storageSet(KESIAPAN_KEY + settled, data);
        })
        .catch((error) => {
            if (kesiapan.key !== key) {
                kesiapan.data = null;
            }
            kesiapan.error = error.message;
        })
        .finally(() => {
            kesiapan.loading = null;
        });

    kesiapan.loading = { key, promise };

    return promise;
}

function greeting() {
    const h = new Date().getHours();

    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';

    return 'Selamat malam';
}

function renderBerandaHero() {
    const unit = activeUnitLabel(activeUnitFallback());
    const fak = !state.canSelectFakultas ? (state.programStudi[0] || {}).fakultas : null;
    const ts = state.ts ?? kesiapan.data?.ts ?? null;

    mEls.greet.textContent = greeting();
    mEls.unit.textContent = [unit, fak && fak !== unit ? fak : null, ts ? `TS ${ts}` : null].filter(Boolean).join(' · ');
}

function renderBerandaSkeleton() {
    mEls.ringPct.textContent = '–';
    mEls.ringArc.setAttribute('stroke-dasharray', '0 264');
    mEls.readyTitle.textContent = 'Sedang memeriksa tabel…';
    mEls.readySync.textContent = 'Mohon tunggu sebentar';
    mEls.readyStats.innerHTML = '';
    const skelRow = '<div class="m-list-row" aria-hidden="true"><span class="m-row-main"><span class="m-skel" style="width:70%"></span><span class="m-skel" style="width:40%;margin-top:6px"></span></span></div>';
    mEls.attn.innerHTML = skelRow.repeat(3);
    mEls.criteria.innerHTML = skelRow.repeat(4);
}

function renderBerandaError() {
    mEls.ringPct.textContent = '–';
    mEls.ringArc.setAttribute('stroke-dasharray', '0 264');
    mEls.readyTitle.textContent = 'Ringkasan belum bisa ditampilkan';
    mEls.readySync.textContent = kesiapan.error || '';
    mEls.readyStats.innerHTML = '';
    mEls.attn.innerHTML = listRow({
        attrs: 'data-action="retry"',
        lead: `<span class="m-row-icon is-empty">${M_ICON.warn}</span>`,
        title: 'Gagal memuat ringkasan',
        sub: 'Ketuk untuk mencoba lagi',
        trail: '',
    });
    mEls.criteria.innerHTML = '';
}

// Keterangan singkat kenapa sebuah tabel kosong. `note` dari server
// adalah `notice` tabel -- kadang alasan singkat, kadang paragraf.
function emptyReason(entry) {
    if (entry.note === 'Gagal dimuat') return 'Gagal dimuat, coba lagi nanti';
    if (entry.note === 'Belum tersedia') return 'Belum tersedia di SAPTO';
    if (entry.note === 'Perlu diisi manual') return 'Tidak ada di sumber data, perlu diisi manual';
    if (entry.note) return 'Data belum tersedia dari sistem';

    return 'Belum ada data';
}

function renderBeranda() {
    renderBerandaHero();

    if (!kesiapan.data) {
        if (kesiapan.error) {
            renderBerandaError();
        } else {
            renderBerandaSkeleton();
        }

        return;
    }

    const { totals, tables: entries, last_sync_at: lastSync } = kesiapan.data;
    const terisi = totals.terisi ?? (totals.lengkap + totals.sebagian);
    const percent = totals.percent;
    const sync = lastSync ? relTime(lastSync) : null;

    mEls.ringPct.textContent = `${percent}%`;
    mEls.ringArc.setAttribute('stroke-dasharray', `${(percent / 100 * 263.9).toFixed(1)} 264`);
    mEls.ringArc.style.stroke = percent >= 75 ? 'var(--m-ok)' : '';
    mEls.readyTitle.textContent = `${terisi} dari ${totals.total} tabel sudah terisi`;
    // Tabel per-prodi di lingkup fakultas/universitas tidak dihitung
    // (lihat status `perlu_prodi` di SaptoKesiapanService).
    const perProdi = totals.perlu_prodi || 0;
    const tidakWajib = totals.tidak_wajib || 0;
    mEls.readySync.textContent = [
        perProdi ? `${perProdi} tabel lain dilihat per program studi` : null,
        tidakWajib ? `${tidakWajib} tabel tidak wajib untuk jenjang ini` : null,
        sync ? `Data diperbarui ${sync}` : null,
    ].filter(Boolean).join(' · ');
    mEls.readyStats.innerHTML = `
        <div><dt><i class="m-dot is-ok"></i>Sudah terisi</dt><dd>${terisi} tabel</dd></div>
        <div><dt><i class="m-dot is-empty"></i>Belum terisi</dt><dd>${totals.kosong} tabel</dd></div>`;

    const order = tables.map((t) => t.id);
    const empty = entries.filter((e) => e.status === 'kosong' && tableById(e.id))
        .sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));

    mEls.attnTitle.textContent = empty.length ? `Belum terisi (${empty.length})` : 'Belum terisi';
    mEls.attn.innerHTML = empty.length
        ? empty.slice(0, 4).map((e) => {
            const t = tableById(e.id);

            return listRow({
                attrs: `data-table-id="${escapeAttr(t.id)}"`,
                lead: `<span class="m-code">${escapeHtml(t.code)}</span>`,
                title: escapeHtml(shortTableName(t)),
                sub: escapeHtml(emptyReason(e)),
            });
        }).join('')
        : listRow({
            lead: `<span class="m-row-icon is-ok">${M_ICON.check}</span>`,
            title: 'Semua tabel sudah terisi',
            sub: 'Tidak ada yang perlu dilengkapi',
            trail: '',
        });

    const groups = {};
    tables.forEach((t) => {
        const g = groupOf(t);
        const st = kesiapan.byId[t.id]?.status;
        groups[g] ??= { total: 0, filled: 0 };
        // Belum bisa dinilai di lingkup ini / bukan kewajiban jenjang ini.
        if (st === 'perlu_prodi' || st === 'tidak_wajib') return;
        groups[g].total += 1;
        if (st === 'lengkap' || st === 'sebagian') groups[g].filled += 1;
    });

    mEls.criteria.innerHTML = Object.keys(groups).sort().map((g) => {
        const { total, filled } = groups[g];
        const pct = total ? Math.round(filled / total * 100) : 0;
        const color = pct === 100 ? 'var(--m-ok)' : (pct === 0 ? 'var(--color-warn)' : 'var(--color-accent)');

        return listRow({
            attrs: `data-group="${escapeAttr(g)}"`,
            lead: `<span class="m-row-icon">${groupIcon(g)}</span>`,
            title: escapeHtml(GROUP_LABELS[g] || `Kriteria ${g}`),
            sub: total ? `${filled} dari ${total} tabel sudah terisi` : (kesiapan.data?.totals?.tidak_wajib ? 'Tidak wajib untuk jenjang ini' : 'Dilihat per program studi'),
            extra: `<span class="m-row-progress" aria-hidden="true"><span style="width:${pct}%;background:${color}"></span></span>`,
        });
    }).join('');

    renderRecent();
}

function renderRecent() {
    const recent = readRecent().slice(0, 3).map(tableById);

    mEls.recentWrap.hidden = !recent.length;
    mEls.recent.innerHTML = recent.map((t) => tableRow(t, escapeHtml(GROUP_LABELS[groupOf(t)] || ''))).join('');
}

function loadBeranda(force = false) {
    // Beranda desktop punya sumber data dan render sendiri (lihat
    // "Beranda desktop" di bawah); yang di sini hanya versi HP.
    if (!mobileMq.matches) {
        return loadHome(force);
    }

    renderBeranda();

    return ensureKesiapan(force).then(() => {
        if (currentTableId === 'beranda') {
            renderBeranda();
        }
    });
}

// ---------- Daftar tabel ----------
function renderPicker() {
    const query = mEls.pickerSearch.value.trim().toLowerCase();
    const onlyEmpty = picker.filter === 'kosong';
    const emptyCount = tables.filter((t) => kesiapan.byId[t.id]?.status === 'kosong').length;

    mEls.countSemua.textContent = tables.length;
    mEls.countKosong.textContent = kesiapan.data ? emptyCount : '';
    mEls.pickerSegments.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.pickerFilter === picker.filter)));

    const groups = {};
    tables.forEach((t) => {
        if (query && !`${t.name} ${t.code}`.toLowerCase().includes(query)) return;
        if (onlyEmpty && kesiapan.byId[t.id]?.status !== 'kosong') return;
        (groups[groupOf(t)] ??= []).push(t);
    });

    const keys = Object.keys(groups).sort();

    if (onlyEmpty && !kesiapan.data) {
        mEls.pickerList.innerHTML = '<div class="m-list"><div class="m-note">Sedang memeriksa tabel yang belum terisi…</div></div>';

        return;
    }

    if (!keys.length) {
        mEls.pickerList.innerHTML = `<div class="m-list"><div class="m-note">${query
            ? `Tidak ada tabel yang cocok dengan “${escapeHtml(query)}”.`
            : 'Semua tabel sudah terisi.'}</div></div>`;

        return;
    }

    mEls.pickerList.innerHTML = keys.map((g) => `
        <section class="m-group" id="m-group-${escapeAttr(g)}">
            <h2 class="m-group-head">${groupIcon(g)}${escapeHtml(GROUP_LABELS[g] || `Kriteria ${g}`)}<span class="m-group-count">${groups[g].length}</span></h2>
            <div class="m-list">${groups[g].map((t) => tableRow(t)).join('')}</div>
        </section>`).join('');
}

function scrollToPickerGroup(g) {
    const el = document.getElementById(`m-group-${g}`);

    if (el) {
        window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 16 });
    }
}

// ---------- Akun ----------
function syncThemeLabel() {
    mEls.themeLabel.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? 'Gelap' : 'Terang';
}

// ---------- Pemasangan ----------
TABLE_HANDLERS.beranda = {
    description: 'Ringkasan kesiapan data akreditasi.',
    show() {
        (mobileMq.matches ? mEls.beranda : homeEls.page).classList.remove('hidden');
        loadBeranda();
    },
};
TABLE_HANDLERS['tabel-picker'] = {
    description: 'Daftar tabel LKPS per kriteria.',
    show() {
        mEls.picker.classList.remove('hidden');
        mEls.pickerSearch.value = '';
        renderPicker();

        const group = picker.scrollGroup;
        picker.scrollGroup = null;

        if (group) {
            requestAnimationFrame(() => scrollToPickerGroup(group));
        }

        ensureKesiapan().then(() => {
            if (currentTableId === 'tabel-picker') renderPicker();
        });
    },
};
TABLE_HANDLERS.akun = {
    description: 'Akun dan pengaturan.',
    show() {
        mEls.akun.classList.remove('hidden');
        syncThemeLabel();
    },
};

document.querySelectorAll('.m-nav-item').forEach((item) => item.addEventListener('click', () => {
    const target = item.dataset.nav;

    picker.filter = 'semua';
    go(target === 'tabel' ? 'tabel-picker' : target);
}));

document.getElementById('m-back').addEventListener('click', () => {
    if (navDepth > 0) {
        history.back();
    } else {
        go('beranda', { replace: true });
    }
});

cardEls.viewButtons.forEach((button) => button.addEventListener('click', () => {
    setViewMode(cardEls.panel.dataset.view === 'kartu' ? 'tabel' : 'kartu');
}));

M_FILTERS.forEach((f) => {
    document.getElementById(`m-${f.key}`).addEventListener('change', (event) => {
        const src = f.src();

        if (!src) return;

        src.value = event.target.value;
        src.dispatchEvent(new Event('change'));
        document.getElementById(`m-${f.key}-label`).textContent = f.label(event.target.selectedOptions[0]?.textContent.trim() || '-');
    });
});

// Ganti halaman daftar kartu: kembali ke kartu pertama, bukan tetap di
// dasar halaman (di bawah app bar yang lengket).
els.tablePagination.addEventListener('click', (event) => {
    if (!mobileMq.matches || !event.target.closest('.sp-page-btn:not(:disabled)')) {
        return;
    }

    const appbar = document.getElementById('mobile-appbar').getBoundingClientRect().height;
    const top = els.tableSearch.getBoundingClientRect().top + window.scrollY - appbar - 12;
    window.scrollTo({ top: Math.max(0, top) });
});

// Satu delegasi untuk semua baris daftar di halaman virtual.
[mEls.beranda, mEls.picker].forEach((page) => page.addEventListener('click', (event) => {
    const target = event.target.closest('[data-table-id], [data-group], [data-action], [data-go], [data-picker-filter]');

    if (!target) {
        return;
    }

    if (target.dataset.tableId) {
        go(target.dataset.tableId);
    } else if (target.dataset.action === 'retry') {
        kesiapan.error = null;
        loadBeranda(true);
    } else if (target.dataset.go) {
        picker.filter = target.dataset.pickerFilter || 'semua';
        go(target.dataset.go);
    } else if (target.dataset.pickerFilter) {
        picker.filter = target.dataset.pickerFilter;
        renderPicker();
    } else if (target.dataset.group) {
        picker.filter = 'semua';
        picker.scrollGroup = target.dataset.group;
        go('tabel-picker');
    }
}));

mEls.pickerSearch.addEventListener('input', renderPicker);

document.getElementById('m-account-profile').addEventListener('click', () => document.getElementById('profile-trigger')?.click());
document.getElementById('m-account-theme').addEventListener('click', () => {
    document.getElementById('theme-toggle')?.click();
    syncThemeLabel();
});

[cardEls.cards, cardEls.maCards].forEach((container) => container.addEventListener('click', (event) => {
    const target = event.target.closest('[data-card-index]');

    if (target) {
        openSheet(Number(target.dataset.cardIndex));
    }
}));

cardEls.sheetBody.addEventListener('click', (event) => {
    const detailButton = event.target.closest('.sapto-detail-trigger');

    if (detailButton) {
        openTableDetail(detailButton);
        dismissSheet();
        return;
    }

    const button = event.target.closest('.sp-number-chip');

    if (button) {
        // Angka Mahasiswa Asing membawa data-tahun, dosen pembimbing data-dosen-id.
        (button.dataset.tahun !== undefined ? openMaDetail : openDetail)(button);
    }
});

cardEls.sheetClose.addEventListener('click', dismissSheet);
cardEls.sheetBackdrop.addEventListener('click', dismissSheet);
cardEls.sheetPrev.addEventListener('click', () => stepSheet(-1));
cardEls.sheetNext.addEventListener('click', () => stepSheet(1));

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (els.drawer.classList.contains('is-open')) {
        closeDrawer();
    } else if (cardEls.sheet.classList.contains('is-open')) {
        dismissSheet();
    }
});

// Badge status API PD UNSRI tinggal di #topbar, yang disembunyikan di
// layar sempit: dipindah ke header Beranda selama layar sempit.
const apibEl = document.querySelector('#topbar [data-api-badge]');
const apibHome = apibEl ? { parent: apibEl.parentNode, next: apibEl.nextSibling } : null;

function placeApiBadge(mobile) {
    if (!apibEl || !mEls.apiSlot) {
        return;
    }

    if (mobile) {
        mEls.apiSlot.appendChild(apibEl);
    } else {
        apibHome.parent.insertBefore(apibEl, apibHome.next);
    }
}

placeApiBadge(mobileMq.matches);
applyViewMode(readViewPref());

mobileMq.addEventListener('change', (event) => {
    placeApiBadge(event.matches);

    if (!event.matches) {
        hideSheet();

        // Daftar tabel dan Akun hanya ada di HP; Beranda punya versi
        // desktop sendiri.
        if (isVirtual(currentTableId) && currentTableId !== 'beranda') {
            activateTable('beranda');

            return;
        }
    }

    // Ukuran layar melewati batas: tampilkan versi Beranda yang cocok.
    if (currentTableId === 'beranda') {
        mEls.beranda.classList.toggle('hidden', !event.matches);
        homeEls.page.classList.toggle('hidden', event.matches);
        loadBeranda();
    }
});

// Halaman awal: di HP tanpa ?table= eksplisit, buka Beranda. Entri
// history pertama diberi state supaya Kembali bisa kembali ke sini.
function initialPage() {
    const explicit = new URLSearchParams(window.location.search).get('table');
    const page = mobileMq.matches && !explicit ? 'beranda' : currentTableId;

    history.replaceState({ sapto: page }, '', location.href);

    return page;
}

// ==== Beranda desktop (>= 640px) ====
//
// Beranda desktop dan Beranda HP adalah satu halaman virtual (`beranda`)
// dengan dua tampilan; CSS menyembunyikan yang tidak cocok dengan lebar
// layar, dan loadBeranda() merender yang sedang tampil. Data:
//   kesiapan  -> /api/sapto/kesiapan (dipakai kedua tampilan)
//   dash      -> /api/sapto/dashboard/summary (angka utama + tren)
//   sys       -> khusus admin: status sinkronisasi + API PD UNSRI
// Tiap sumber gagal/lambat sendiri-sendiri: bagian yang datanya belum
// ada tetap menampilkan skeleton atau pesan, tidak menahan bagian lain.
const homeEls = {
    page: document.getElementById('home-page'),
    scope: document.getElementById('home-scope'),
    greet: document.getElementById('home-greet'),
    sync: document.getElementById('home-sync'),
    refresh: document.getElementById('home-refresh'),
    alert: document.getElementById('home-alert'),
    heroFig: document.getElementById('home-hero-fig'),
    heroTitle: document.getElementById('home-hero-title'),
    heroSub: document.getElementById('home-hero-sub'),
    meter: document.getElementById('home-meter'),
    legend: document.getElementById('home-legend'),
    heroNote: document.getElementById('home-hero-note'),
    criteria: document.getElementById('home-criteria'),
    attn: document.getElementById('home-attn'),
    attnSub: document.getElementById('home-attn-sub'),
    attnMore: document.getElementById('home-attn-more'),
    charts: document.getElementById('home-charts'),
    recent: document.getElementById('home-recent'),
    system: document.getElementById('home-system'),
    tip: document.getElementById('home-tip'),
};
const isAdminUser = homeEls.page?.dataset.admin === '1';
const DASH_KEY = 'sapto.dash.v1:';
const dash = { data: null, key: '', fresh: '', error: null, loading: null };
const sys = { pd: null, sync: null, error: null, at: 0, loading: null };
let attnExpanded = false;
const ATTN_LIMIT = 6;

mEls.pages.push(homeEls.page);

// Grafik tren: satu definisi per kartu dashboard. `good` = arah yang
// dianggap membaik (null = netral, tidak diwarnai). `label`: awalan sumbu
// ("TS" = tahun akademik, "Angkatan" = tahun masuk).
const HOME_CHARTS = [
    { id: 'dosen-pembimbing-ta', title: 'Bimbingan tugas akhir', sub: 'Mahasiswa di program studi yang diakreditasi', kind: 'column', unit: 'mahasiswa', label: 'TS', good: null, decimals: 0 },
    { id: 'ipk-lulusan', title: 'IPK rata-rata lulusan', sub: 'Skala 4,00', kind: 'line', unit: '', label: 'TS', good: 'up', decimals: 2, domain: [3, 4] },
    { id: 'masa-studi', title: 'Masa studi lulusan', sub: 'Rata-rata per angkatan, dalam tahun', kind: 'line', unit: 'tahun', label: 'Angkatan', good: 'down', decimals: 2 },
    { id: 'mahasiswa-asing', title: 'Mahasiswa asing penuh waktu', sub: 'Jumlah per tahun akademik', kind: 'column', unit: 'mahasiswa', label: 'TS', good: 'up', decimals: 0, none: 'Tidak ada mahasiswa asing penuh waktu pada lingkup ini.' },
];

const fmtN = (v, d = null) => (d === null
    ? formatNumber(v)
    : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }));

// ---------- Pengambilan data ----------
function ensureDashboard(force = false) {
    const key = dashboardUrl();

    if (!dash.data || dash.key !== key) {
        const cached = storageGet(DASH_KEY + key, null);

        if (cached?.cards) {
            dash.data = cached;
            dash.key = key;
        }
    }

    if (!force && dash.key === key && dash.fresh === key) {
        return Promise.resolve();
    }

    if (dash.loading?.key === key) {
        return dash.loading.promise;
    }

    const promise = fetchJson(key)
        .then((data) => {
            // TS terpilih dari respons, seperti dashboard lama: dropdown TS
            // diisi dari tahun yang benar-benar ada di database.
            if (data.ts !== null && data.ts !== undefined) {
                state.ts = data.ts;
            }
            populateTsOptions(els.ts, data.available_years, data.ts);

            // Kunci dihitung ulang SETELAH state.ts terisi: tanpa ini
            // pemanggilan berikutnya (yang sudah membawa ts) dianggap
            // lingkup baru dan mengambil ulang dengan sia-sia.
            const settled = dashboardUrl();
            dash.data = data;
            dash.key = settled;
            dash.fresh = settled;
            dash.error = null;
            storageSet(DASH_KEY + settled, data);
        })
        .catch((error) => {
            if (dash.key !== key) {
                dash.data = null;
            }
            dash.error = error.message;
        })
        .finally(() => {
            dash.loading = null;
        });

    dash.loading = { key, promise };

    return promise;
}

// Status sistem (admin): dua sumber, tiap satu boleh gagal sendiri.
function ensureSystem(force = false) {
    if (!isAdminUser) {
        return Promise.resolve();
    }

    if (!force && sys.at && Date.now() - sys.at < 30000) {
        return Promise.resolve();
    }

    if (sys.loading) {
        return sys.loading;
    }

    sys.loading = Promise.allSettled([
        fetchJson('/api/sapto/pd-unsri/status'),
        fetchJson('/admin/sync-pd-unsri/status'),
    ]).then(([pd, sync]) => {
        sys.pd = pd.status === 'fulfilled' ? pd.value : null;
        sys.sync = sync.status === 'fulfilled' ? sync.value : null;
        sys.error = sys.pd || sys.sync ? null : 'Status sistem belum bisa dimuat.';
        sys.at = Date.now();
    }).finally(() => {
        sys.loading = null;
    });

    return sys.loading;
}

// ---------- Render ----------
function renderHome() {
    if (!homeEls.page || homeEls.page.classList.contains('hidden')) {
        return;
    }

    renderHomeHead();
    renderHomeHero();
    renderHomeStats();
    renderHomeCriteria();
    renderHomeAttention();
    renderHomeCharts();
    renderHomeRecent();

    if (isAdminUser) {
        renderHomeSystem();
    }

    const failed = [];
    if (kesiapan.error && !kesiapan.data) failed.push('kelengkapan data');
    if (dash.error && !dash.data) failed.push('angka dan tren');
    homeEls.alert.hidden = failed.length === 0;
    homeEls.alert.textContent = failed.length
        ? `Bagian ${failed.join(' dan ')} belum bisa dimuat. Klik “Muat ulang” untuk mencoba lagi.`
        : '';
}

function renderHomeHead() {
    const unit = activeUnitLabel(activeUnitFallback());
    const fak = !state.canSelectFakultas ? (state.programStudi[0] || {}).fakultas : null;
    const ts = state.ts ?? dash.data?.ts ?? kesiapan.data?.ts ?? null;
    const sync = kesiapan.data?.last_sync_at ? relTime(kesiapan.data.last_sync_at) : null;

    homeEls.scope.textContent = [unit, fak && fak !== unit ? fak : null, ts ? `TS ${ts}` : null].filter(Boolean).join(' · ');
    homeEls.greet.textContent = greeting();
    homeEls.sync.textContent = sync ? `Data PD UNSRI diperbarui ${sync}` : '';
}

function renderHomeHero() {
    const d = kesiapan.data;

    if (!d) {
        if (kesiapan.error) {
            homeEls.heroFig.textContent = '–';
            homeEls.heroTitle.textContent = 'Ringkasan belum bisa ditampilkan';
            homeEls.heroSub.textContent = kesiapan.error;
            homeEls.meter.innerHTML = '';
            homeEls.legend.innerHTML = '';
        }

        return;
    }

    const t = d.totals;
    const terisi = t.terisi ?? (t.lengkap + t.sebagian);
    const seg = (cls, n) => (n > 0 ? `<span class="${cls}" style="flex:${n} 1 0"></span>` : '');

    homeEls.heroFig.innerHTML = `${t.percent}<small>%</small>`;
    homeEls.heroTitle.textContent = `${terisi} dari ${t.total} tabel sudah terisi`;
    homeEls.heroSub.textContent = t.kosong > 0 ? `${t.kosong} tabel belum berisi data` : 'Semua tabel sudah berisi data';
    homeEls.meter.setAttribute('aria-label', `Lengkap ${t.lengkap}, kolom sebagian ${t.sebagian}, kosong ${t.kosong}, dari ${t.total} tabel`);
    homeEls.meter.innerHTML = seg('is-full', t.lengkap) + seg('is-part', t.sebagian) + seg('is-none', t.kosong);
    homeEls.legend.innerHTML = `
        <li><i class="home-swatch is-full"></i>Lengkap <b>${t.lengkap}</b></li>
        <li><i class="home-swatch is-part"></i>Kolom sebagian <b>${t.sebagian}</b></li>
        <li><i class="home-swatch is-none"></i>Kosong <b>${t.kosong}</b></li>`;

    const perProdi = t.perlu_prodi || 0;
    const tidakWajib = t.tidak_wajib || 0;
    homeEls.heroNote.hidden = perProdi === 0 && tidakWajib === 0;
    homeEls.heroNote.textContent = [
        perProdi ? `${perProdi} tabel lain disusun per program studi dan belum dinilai di lingkup ini. Pilih program studi pada filter di atas untuk melihatnya.` : null,
        tidakWajib ? `${tidakWajib} tabel tidak wajib untuk jenjang program studi ini menurut templat LKPS BAN-PT, jadi tidak dihitung.` : null,
    ].filter(Boolean).join(' ');
}

// Selisih tahun berjalan terhadap tahun sebelumnya. Warna mengikuti arah
// x apakah naik itu baik: bentuk "naik = hijau" tidak berlaku untuk
// masa studi, yang membaik justru saat turun.
function trendDelta(trend, meta) {
    const values = trend?.values;

    if (!Array.isArray(values) || values.length < 2) {
        return null;
    }

    const cur = Number(values[values.length - 1]);
    const prev = Number(values[values.length - 2]);
    const diff = cur - prev;

    if (!Number.isFinite(diff)) {
        return null;
    }

    const arrow = diff > 0 ? '▲' : (diff < 0 ? '▼' : '▬');
    const sign = diff > 0 ? '+' : (diff < 0 ? '−' : '');
    const cls = diff === 0 || !meta.good ? '' : ((meta.good === 'up') === (diff > 0) ? 'is-good' : 'is-bad');
    const from = trend.labels[trend.labels.length - 2];

    return { text: `${arrow} ${sign}${fmtN(Math.abs(diff), meta.decimals)} dibanding ${meta.label === 'Angkatan' ? 'angkatan' : 'TS'} ${from}`, cls };
}

function renderHomeStats() {
    const cards = new Map((dash.data?.cards || []).map((c) => [c.id, c]));

    homeEls.page.querySelectorAll('[data-stat]').forEach((el) => {
        const card = cards.get(el.dataset.stat);
        const value = el.querySelector('[data-role="value"]');
        const sub = el.querySelector('[data-role="sub"]');
        const open = el.querySelector('[data-role="open"]');

        if (!dash.data) {
            if (dash.error) {
                value.textContent = '–';
                sub.textContent = 'Belum bisa dimuat';
            }

            return;
        }

        const m = card?.metrics || [];
        open.hidden = false;

        if (!card || card.error || !m.length) {
            value.textContent = '–';
            sub.textContent = card?.error ? 'Belum bisa dimuat' : (card?.notice ? 'Pilih program studi untuk melihat angka ini' : 'Belum ada data');

            return;
        }

        if (card.id === 'dosen-tetap-pt') {
            value.textContent = fmtN(m[0].value);
            sub.textContent = m.slice(1).map((x) => `${x.label.replace(/^Pendidikan\s+/, '')}: ${fmtN(x.value)}`).join(' · ');
        } else if (card.id === 'ipk-lulusan') {
            value.textContent = fmtN(m[1]?.value ?? 0);
            sub.textContent = `IPK rata-rata ${fmtN(m[2]?.value ?? 0, 2)} · ${fmtN(m[0]?.value ?? 0)} lulusan dalam 3 tahun`;
        } else {
            value.textContent = fmtN(m[0].value);
            sub.textContent = `Membimbing ${fmtN(m[1]?.value ?? 0)} mahasiswa di program studi ini dan ${fmtN(m[2]?.value ?? 0)} di program studi lain`;
        }
    });
}

const CHEV = '<svg class="home-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';

function renderHomeCriteria() {
    if (!kesiapan.data) {
        if (kesiapan.error) {
            homeEls.criteria.innerHTML = '<li class="home-empty">Kelengkapan per kriteria belum bisa dimuat.</li>';
        }

        return;
    }

    const groups = {};
    tables.forEach((t) => {
        const g = groupOf(t);
        const st = kesiapan.byId[t.id]?.status;
        groups[g] ??= { total: 0, filled: 0, first: t.id };
        // Belum bisa dinilai di lingkup ini / bukan kewajiban jenjang ini.
        if (st === 'perlu_prodi' || st === 'tidak_wajib') return;
        groups[g].total += 1;
        if (st === 'lengkap' || st === 'sebagian') groups[g].filled += 1;
    });

    homeEls.criteria.innerHTML = Object.keys(groups).sort().map((g) => {
        const { total, filled, first } = groups[g];
        const pct = total ? Math.round(filled / total * 100) : null;

        return `
            <li class="home-row">
                <button type="button" class="home-row-btn" data-table-id="${escapeAttr(first)}">
                    <span class="home-ico">${groupIcon(g)}</span>
                    <span class="home-row-main">
                        <span class="home-row-title">${escapeHtml(GROUP_LABELS[g] || `Kriteria ${g}`)}</span>
                        <span class="home-row-sub">${total ? `${filled} dari ${total} tabel sudah terisi` : (kesiapan.data.totals.tidak_wajib ? 'Tidak wajib untuk jenjang ini' : 'Dilihat per program studi')}</span>
                        ${total ? `<span class="home-row-meter" aria-hidden="true"><span class="${pct < 50 ? 'is-low' : ''}" style="width:${pct}%"></span></span>` : ''}
                    </span>
                    <span class="home-row-end">${pct === null ? '–' : `${pct}%`}<small>Tabel ${escapeHtml(g)}</small></span>
                    ${CHEV}
                </button>
            </li>`;
    }).join('');
}

function renderHomeAttention() {
    if (!kesiapan.data) {
        if (kesiapan.error) {
            homeEls.attn.innerHTML = '<li class="home-empty">Daftar tindakan belum bisa dimuat.</li>';
        }

        return;
    }

    const order = tables.map((t) => t.id);
    const empty = kesiapan.data.tables
        .filter((e) => e.status === 'kosong' && tableById(e.id))
        .sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));

    homeEls.attnSub.textContent = empty.length ? `${empty.length} tabel belum berisi data.` : 'Tabel yang belum berisi data.';

    if (!empty.length) {
        homeEls.attn.innerHTML = '<li class="home-empty">Semua tabel sudah terisi. Tidak ada yang perlu dilengkapi.</li>';
        homeEls.attnMore.hidden = true;

        return;
    }

    const shown = attnExpanded ? empty : empty.slice(0, ATTN_LIMIT);

    homeEls.attn.innerHTML = shown.map((e) => {
        const t = tableById(e.id);

        return `
            <li class="home-row">
                <button type="button" class="home-row-btn" data-table-id="${escapeAttr(t.id)}">
                    <span class="home-code">${escapeHtml(t.code)}</span>
                    <span class="home-row-main">
                        <span class="home-row-title">${escapeHtml(shortTableName(t))}</span>
                        <span class="home-row-sub">${escapeHtml(emptyReason(e))}</span>
                    </span>
                    ${CHEV}
                </button>
            </li>`;
    }).join('');

    homeEls.attnMore.hidden = empty.length <= ATTN_LIMIT;
    homeEls.attnMore.textContent = attnExpanded ? 'Tampilkan lebih sedikit' : `Tampilkan semua (${empty.length})`;
}

// ---------- Grafik ----------
const niceCeil = (v) => {
    if (v <= 0) return 1;
    const p = 10 ** Math.floor(Math.log10(v));
    const n = v / p;

    return ([1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find((step) => n <= step) ?? 10) * p;
};

const chartHasData = (trend) => Array.isArray(trend?.values) && trend.values.some((v) => Number(v) > 0);

function axisLabel(meta, trend, i) {
    const n = trend.labels.length;

    if (meta.label === 'Angkatan') {
        return [String(trend.labels[i]), ''];
    }

    const off = n - 1 - i;

    return [off === 0 ? 'TS' : `TS-${off}`, String(trend.labels[i])];
}

function tipLabel(meta, trend, i) {
    const n = trend.labels.length;

    if (meta.label === 'Angkatan') {
        return `Angkatan ${trend.labels[i]}`;
    }

    const off = n - 1 - i;

    return `${off === 0 ? 'TS' : `TS-${off}`} ${trend.labels[i]}`;
}

// SVG dirender pada lebar asli wadahnya supaya batang tepat setebal
// spesifikasi (maks 24px) dan teks tidak ikut mengecil.
function chartSvg(meta, trend, W) {
    const values = trend.values.map(Number);
    const n = values.length;
    const H = 170;
    const padL = 44;
    const padR = 12;
    const padT = 22;
    const padB = meta.label === 'Angkatan' ? 26 : 40;
    const plotW = Math.max(120, W - padL - padR);
    const plotH = H - padT - padB;
    const [min, max] = meta.domain || [0, niceCeil(Math.max(...values) * 1.05)];
    const yOf = (v) => padT + plotH * (1 - (Math.min(Math.max(v, min), max) - min) / (max - min));
    const band = plotW / n;
    const cx = (i) => padL + band * (i + 0.5);
    const baseY = padT + plotH;
    const ticks = [min, (min + max) / 2, max];

    const grid = ticks.map((tv) => `
        <line class="home-grid" x1="${padL}" x2="${padL + plotW}" y1="${yOf(tv)}" y2="${yOf(tv)}"></line>
        <text x="${padL - 8}" y="${yOf(tv) + 4}" text-anchor="end">${escapeHtml(fmtN(tv, meta.decimals === 2 ? 1 : 0))}</text>`).join('');

    const xLabels = values.map((_, i) => {
        const [l1, l2] = axisLabel(meta, trend, i);

        return `<text x="${cx(i)}" y="${baseY + 16}" text-anchor="middle"${i === n - 1 ? ' style="fill:var(--color-ink);font-weight:700"' : ''}>${escapeHtml(l1)}</text>`
            + (l2 ? `<text x="${cx(i)}" y="${baseY + 30}" text-anchor="middle">${escapeHtml(l2)}</text>` : '');
    }).join('');

    const hit = (i, inner) => `
        <g class="home-pt" tabindex="0" role="img"
           aria-label="${escapeAttr(`${tipLabel(meta, trend, i)}: ${fmtN(values[i], meta.decimals)} ${meta.unit}`.trim())}"
           data-tip-v="${escapeAttr(`${fmtN(values[i], meta.decimals)}${meta.unit ? ` ${meta.unit}` : ''}`)}"
           data-tip-l="${escapeAttr(tipLabel(meta, trend, i))}">
            <rect class="home-hit" x="${padL + band * i}" y="${padT - 8}" width="${band}" height="${plotH + 8 + (padB - 6)}"></rect>
            ${inner}
        </g>`;

    let marks = '';

    if (meta.kind === 'column') {
        const bw = Math.min(24, band * 0.5);

        marks = values.map((v, i) => {
            const h = Math.max(2, baseY - yOf(v));
            const x0 = cx(i) - bw / 2;
            const x1 = cx(i) + bw / 2;
            const top = baseY - h;
            const r = Math.min(4, h / 2);
            const now = i === n - 1;
            const path = `M${x0} ${baseY}V${top + r}Q${x0} ${top} ${x0 + r} ${top}H${x1 - r}Q${x1} ${top} ${x1} ${top + r}V${baseY}Z`;

            return hit(i, `<path class="home-bar${now ? ' is-now' : ''}" d="${path}"></path>`
                + (now ? `<text class="home-val" x="${cx(i)}" y="${top - 6}" text-anchor="middle">${escapeHtml(fmtN(v, meta.decimals))}</text>` : ''));
        }).join('');
    } else {
        const pts = values.map((v, i) => [cx(i), yOf(v)]);
        const line = pts.map((p) => p.join(' ')).join(' L');
        const area = `M${pts[0][0]} ${baseY} L${line} L${pts[n - 1][0]} ${baseY}Z`;

        marks = `<path class="home-area" d="${area}"></path><path class="home-line" d="M${line}"></path>`
            + values.map((v, i) => {
                const now = i === n - 1;

                return hit(i, `<line class="home-cross" x1="${cx(i)}" x2="${cx(i)}" y1="${padT}" y2="${baseY}"></line>`
                    + `<circle class="home-ring" cx="${cx(i)}" cy="${yOf(v)}" r="6"></circle>`
                    + `<circle class="home-dot${now ? ' is-now' : ''}" cx="${cx(i)}" cy="${yOf(v)}" r="4"></circle>`
                    + (now ? `<text class="home-val" x="${cx(i)}" y="${yOf(v) - 12}" text-anchor="middle">${escapeHtml(fmtN(v, meta.decimals))}</text>` : ''));
            }).join('');
    }

    return `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" role="group" aria-label="${escapeAttr(meta.title)}">${grid}${marks}${xLabels}</svg>`;
}

function chartCard(meta, card) {
    const trend = card?.trend;
    const head = `
        <div class="home-chart-head">
            <div>
                <div class="home-chart-title">${escapeHtml(meta.title)}</div>
                <div class="home-chart-sub">${escapeHtml(meta.sub)}</div>
            </div>
        </div>`;

    if (!card || card.error || !chartHasData(trend)) {
        const why = card?.error ? 'Belum bisa dimuat.'
            : (card?.notice && !card?.metrics?.length ? 'Pilih program studi pada filter untuk melihat tren ini.' : (meta.none || 'Belum ada data untuk lingkup ini.'));

        return `<article class="home-card home-chart" data-chart="${meta.id}">${head}<div class="home-chart-empty">${escapeHtml(why)}</div></article>`;
    }

    const last = Number(trend.values[trend.values.length - 1]);
    const d = trendDelta(trend, meta);
    const rows = trend.values.map((v, i) => `<tr><th scope="row">${escapeHtml(tipLabel(meta, trend, i))}</th><td>${escapeHtml(fmtN(v, meta.decimals))}${meta.unit ? ` ${escapeHtml(meta.unit)}` : ''}</td></tr>`).join('');

    return `
        <article class="home-card home-chart" data-chart="${meta.id}">
            ${head}
            <div class="home-chart-now">${escapeHtml(fmtN(last, meta.decimals))}<small>${escapeHtml(tipLabel(meta, trend, trend.values.length - 1))}</small></div>
            <div class="home-stat-delta ${d?.cls || ''}">${d ? escapeHtml(d.text) : ''}</div>
            <div class="home-plot" data-plot></div>
            <div class="home-chart-foot"><button type="button" class="home-link" data-table-id="${escapeAttr(meta.id)}">Buka tabel <span aria-hidden="true">→</span></button></div>
            <details class="home-tv"><summary>Lihat sebagai tabel</summary><table><tbody>${rows}</tbody></table></details>
        </article>`;
}

function renderHomeCharts() {
    if (!dash.data) {
        if (dash.error) {
            homeEls.charts.innerHTML = '<article class="home-card home-chart"><div class="home-chart-empty">Tren belum bisa dimuat.</div></article>';
        }

        return;
    }

    const cards = new Map(dash.data.cards.map((c) => [c.id, c]));
    homeEls.charts.innerHTML = HOME_CHARTS.map((meta) => chartCard(meta, cards.get(meta.id))).join('');

    homeEls.charts.querySelectorAll('.home-chart[data-chart]').forEach((el) => {
        const plot = el.querySelector('[data-plot]');
        const card = cards.get(el.dataset.chart);
        const meta = HOME_CHARTS.find((m) => m.id === el.dataset.chart);

        if (plot && card?.trend) {
            plot.innerHTML = chartSvg(meta, card.trend, Math.max(220, Math.floor(plot.clientWidth || 260)));
        }
    });
}

// Satu tooltip untuk semua grafik. Nilai memimpin, keterangan menyusul;
// isi selalu ada juga di tabel data dan aria-label, jadi tooltip hanya
// memperkaya, tidak menjadi satu-satunya jalan ke angkanya.
function showTip(target, x, y) {
    const tip = homeEls.tip;
    tip.replaceChildren();
    const b = document.createElement('b');
    b.textContent = target.dataset.tipV;
    const s = document.createElement('span');
    s.textContent = target.dataset.tipL;
    tip.append(b, s);
    tip.hidden = false;

    const w = tip.offsetWidth;
    const h = tip.offsetHeight;
    tip.style.left = `${Math.min(Math.max(8, x - w / 2), window.innerWidth - w - 8)}px`;
    tip.style.top = `${Math.max(8, y - h - 14)}px`;
}

const hideTip = () => { homeEls.tip.hidden = true; };

homeEls.charts.addEventListener('pointermove', (event) => {
    const pt = event.target.closest('.home-pt');

    if (pt) {
        showTip(pt, event.clientX, event.clientY);
    } else {
        hideTip();
    }
});
homeEls.charts.addEventListener('pointerleave', hideTip);
homeEls.charts.addEventListener('focusin', (event) => {
    const pt = event.target.closest('.home-pt');

    if (pt) {
        const r = pt.getBoundingClientRect();
        showTip(pt, r.left + r.width / 2, r.top + 24);
    }
});
homeEls.charts.addEventListener('focusout', hideTip);
window.addEventListener('scroll', hideTip, { passive: true });

// ---------- Terakhir dibuka & kesehatan sistem ----------
function renderHomeRecent() {
    const recent = readRecent().slice(0, 5).map(tableById).filter(Boolean);

    homeEls.recent.innerHTML = recent.length ? recent.map((t) => `
        <li class="home-row">
            <button type="button" class="home-row-btn" data-table-id="${escapeAttr(t.id)}">
                <span class="home-code">${escapeHtml(t.code)}</span>
                <span class="home-row-main">
                    <span class="home-row-title">${escapeHtml(shortTableName(t))}</span>
                    <span class="home-row-sub">${escapeHtml(GROUP_LABELS[groupOf(t)] || '')}</span>
                </span>
                ${CHEV}
            </button>
        </li>`).join('') : '<li class="home-empty">Belum ada tabel yang dibuka. Pilih tabel dari menu di sebelah kiri.</li>';
}

function renderHomeSystem() {
    if (!sys.at) {
        return;
    }

    if (sys.error) {
        homeEls.system.innerHTML = `<li class="home-empty">${escapeHtml(sys.error)}</li>`;

        return;
    }

    const row = (dot, title, sub, href) => {
        const inner = `
            <span class="home-status-dot ${dot}" aria-hidden="true"></span>
            <span class="home-row-main">
                <span class="home-row-title">${escapeHtml(title)}</span>
                <span class="home-row-sub">${escapeHtml(sub)}</span>
            </span>
            ${href ? CHEV : ''}`;

        return `<li class="home-row">${href
            ? `<a class="home-row-btn" href="${escapeAttr(href)}">${inner}</a>`
            : `<div class="home-row-btn" style="cursor:default">${inner}</div>`}</li>`;
    };

    const rows = [];
    const pd = sys.pd;
    const sync = sys.sync;

    if (pd) {
        const dot = { online: 'is-ok', gangguan: 'is-warn', offline: 'is-down' }[pd.status] || '';
        rows.push(row(dot, 'API PD UNSRI (lewat Gravitee)', `${pd.label}${pd.latency_ms ? ` · ${pd.latency_ms} ms` : ''}. ${pd.detail || ''}`.trim(), null));
    }

    if (sync) {
        const batch = sync.batch;
        const stamp = batch?.finished_at || pd?.last_sync?.finished_at;
        const last = stamp ? relTime(stamp) : null;
        const next = sync.next_run_at
            ? new Date(sync.next_run_at).toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
            : null;
        const failed = Number(batch?.failed || 0);
        const states = {
            stalled: ['is-down', 'Terhenti: tidak ada pekerja antrean yang memprosesnya'],
            running: ['is-warn', `Sedang berjalan (${batch?.processed ?? 0} dari ${batch?.total ?? 0} tabel)`],
            cancelled: ['is-warn', `Terakhir dibatalkan${last ? ` ${last}` : ''} (${batch?.processed ?? 0} dari ${batch?.total ?? 0} tabel)`],
        };
        const [dot, head] = states[batch?.state]
            || (failed > 0 ? ['is-warn', `Terakhir ${last || 'selesai'}, ${failed} tabel gagal`]
                : ['is-ok', last ? `Terakhir ${last}` : 'Belum ada catatan']);

        rows.push(row(dot, 'Sinkronisasi data PD UNSRI',
            [head, next ? `berikutnya ${next} WIB` : null].filter(Boolean).join(' · '),
            '/admin/sync-pd-unsri'));

        if (sync.kafka) {
            rows.push(row(sync.kafka.status === 'ok' ? 'is-ok' : (sync.kafka.status === 'warn' ? 'is-warn' : ''),
                'Event Stream (Kafka)', `${sync.kafka.label}. ${sync.kafka.detail}`, sync.kafka.url || '/admin/kafka'));
        }

        rows.push(row(sync.worker_running ? 'is-ok' : 'is-down', 'Pekerja antrean',
            sync.worker_running ? 'Berjalan' : 'Tidak berjalan: sinkronisasi tidak akan diproses', null));
    }

    homeEls.system.innerHTML = rows.join('') || '<li class="home-empty">Status sistem belum tersedia.</li>';
}

// ---------- Pemuatan & interaksi ----------
function loadHome(force = false) {
    renderHome();

    const jobs = [ensureKesiapan(force), ensureDashboard(force)];

    if (isAdminUser) {
        jobs.push(ensureSystem(force));
    }

    // Tiap sumber merender sendiri begitu selesai, tanpa menunggu yang lain.
    jobs.forEach((job) => job.then(() => {
        if (currentTableId === 'beranda') {
            renderHome();
        }
    }));

    return Promise.all(jobs);
}

homeEls.refresh.addEventListener('click', () => {
    homeEls.refresh.classList.add('is-busy');
    homeEls.refresh.disabled = true;
    kesiapan.error = null;
    dash.error = null;

    loadHome(true).finally(() => {
        homeEls.refresh.classList.remove('is-busy');
        homeEls.refresh.disabled = false;
    });
});

homeEls.attnMore.addEventListener('click', () => {
    attnExpanded = !attnExpanded;
    renderHomeAttention();
});

homeEls.page.addEventListener('click', (event) => {
    const target = event.target.closest('[data-table-id]');

    if (target && !target.hasAttribute('href')) {
        activateTable(target.dataset.tableId);
    }
});

// Grafik dirender pada lebar pikselnya; lebar itu berubah bukan hanya
// saat jendela diubah, tetapi juga saat sidebar dilipat/dibuka. Karena
// itu yang diamati adalah wadah grafiknya, bukan window. Wadah tersembunyi
// (clientWidth 0) diabaikan; begitu Beranda tampil, pengamat menyala lagi.
let homeChartsWidth = 0;
let homeResizeTimer = null;
new ResizeObserver(() => {
    clearTimeout(homeResizeTimer);
    homeResizeTimer = setTimeout(() => {
        const width = homeEls.charts.clientWidth;

        if (width && width !== homeChartsWidth && currentTableId === 'beranda' && !mobileMq.matches) {
            homeChartsWidth = width;
            renderHomeCharts();
        }
    }, 120);
}).observe(homeEls.charts);

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
    if (currentTableId === 'beranda') return loadBeranda(true);
    if (isVirtual(currentTableId)) return Promise.resolve();
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
    updateSidebarForScope();
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
    const detailButton = event.target.closest('.sapto-detail-trigger');

    if (detailButton) {
        openTableDetail(detailButton);
        return;
    }

    const button = event.target.closest('.sp-number-chip');

    if (button) {
        openDetail(button);
    }
});

els.cards?.addEventListener('click', (event) => {
    const button = event.target.closest('.sapto-detail-trigger');
    if (button) openTableDetail(button);
});

els.tableDetailClose?.addEventListener('click', closeTableDetail);

els.tableDetailExport?.addEventListener('click', async () => {
    const context = tableDetailExportContext;
    if (!context) return;

    try {
        let items = context.items || [];

        if (context.selection && selectionDetailContext) {
            const detail = selectionDetailContext;
            const allItems = [];
            let pages = 1;
            for (let page = 1; page <= pages; page += 1) {
                const params = new URLSearchParams({ tahun: String(detail.year), kategori: detail.category, page: String(page), per_page: '50' });
                if (detail.jenisDaftar) params.set('jenis_daftar', detail.jenisDaftar);
                if (state.canSelectFakultas && state.fakultasId !== 'all') params.set('fakultas_id', state.fakultasId);
                if (state.canSelectProgramStudi && state.programStudiId !== 'all') params.set('program_studi_id', state.programStudiId);
                const response = await fetchJson(`/api/sapto/tables/seleksi-mahasiswa/detail?${params}`);
                pages = Math.max(1, Number(response.pagination?.last_page || 1));
                allItems.push(...(Array.isArray(response.data) ? response.data : []));
            }

            items = allItems;
        }

        const rows = [context.columns.map(([, label]) => label), ...items.map((item) => context.columns.map(([key]) => formatDetailValue(key, item[key])))];
        const safeTitle = (context.title || 'Rincian').replace(/[\\/:*?"<>|]/g, '-');
        downloadCsv(`SAPTO - ${safeTitle} - ${new Date().toISOString().slice(0, 10)}.csv`, rows);
        showToast(`${items.length.toLocaleString('id-ID')} baris detail berhasil diekspor ke Excel.`);
    } catch (error) {
        showToast(`Export detail gagal: ${error.message}`);
    }
});

els.drawerDetailExport?.addEventListener('click', () => {
    const context = drawerDetailExportContext;
    if (!context) return;
    const rows = [context.columns.map(([, label]) => label), ...context.items.map((item) => context.columns.map(([key]) => formatDetailValue(key, item[key])))];
    const safeTitle = (context.title || 'Rincian').replace(/[\\/:*?"<>|]/g, '-');
    downloadCsv(`SAPTO - ${safeTitle} - ${new Date().toISOString().slice(0, 10)}.csv`, rows);
    showToast(`${context.items.length.toLocaleString('id-ID')} baris detail berhasil diekspor ke Excel.`);
});

els.tablePagination.addEventListener('click', (event) => {
    const button = event.target.closest('.sp-page-btn');

    if (button && !button.disabled) {
        goToPage(Number(button.dataset.page));
    }
});

els.tableDetailPagination?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-detail-page]');
    if (!button || button.disabled) return;
    if (selectionDetailContext) {
        selectionDetailContext.page = Number(button.dataset.detailPage);
        loadSelectionDetail();
    } else if (tableRecordDetailContext) {
        tableRecordDetailContext.page = Number(button.dataset.detailPage);
        renderRecordDetailPage();
    }
});

els.tableDetailPagination?.addEventListener('change', (event) => {
    if (event.target.id === 'record-detail-page-size' && tableRecordDetailContext) {
        const perPage = Number(event.target.value);
        if (![10, 20, 50].includes(perPage)) return;
        tableRecordDetailContext.perPage = perPage;
        tableRecordDetailContext.page = 1;
        renderRecordDetailPage();
        return;
    }
    if (event.target.id !== 'selection-detail-page-size' || !selectionDetailContext) return;
    const perPage = Number(event.target.value);
    if (![10, 20, 50].includes(perPage)) return;
    selectionDetailContext.perPage = perPage;
    selectionDetailContext.page = 1;
    loadSelectionDetail();
});

els.maTableBody.addEventListener('click', (event) => {
    const button = event.target.closest('.sp-number-chip');

    if (button) {
        openMaDetail(button);
    }
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
            const matches = button.dataset.levelVisible !== 'false' && (!query || button.dataset.search.includes(query));
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
    showToast(`${Math.max(rows.length - 1, 0)} baris berhasil diekspor ke Excel.`);
});

els.maTableExport?.addEventListener('click', () => {
    if (!maSummary) {
        return;
    }

    const rows = exportMaRows();

    downloadCsv(exportFilenameFor('mahasiswa-asing'), rows);
    showToast(`${Math.max(rows.length - 1, 0)} baris berhasil diekspor ke Excel.`);
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
        if (meta) meta.setAttribute('content', next === 'dark' ? '#0a0a0c' : '#eef0f3');
        try { localStorage.setItem('sapto-theme', next); } catch (e) {}
    });
}

@if (session('admin_login_alert'))
    // Flash sekali pakai dari SsoController::callback() -- cuma tampil
    // tepat setelah login sebagai admin, bukan tiap kali halaman dibuka.
    showToast('Anda masuk sebagai Admin — akses penuh ke seluruh data SAPTO.', 'success');
@endif

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
        await activateTable(initialPage());
    } catch (error) {
        renderError(error.message);
    }
})();

// Tombol "Segarkan laporan" di badge API (components/api-status-badge)
// menghitung ulang cache lingkup pengguna di proses terpisah; begitu
// selesai, tabel yang sedang dibuka dimuat ulang supaya angkanya ikut baru.
window.addEventListener('sapto:reports-refreshed', function () {
    loadActivePage().catch(function (error) {
        console.error('Gagal memuat ulang setelah segarkan laporan:', error);
    });
});

</script>
@endsection
