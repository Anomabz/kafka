{{--
    resources/views/components/mahasiswa-asing-panel.blade.php

    Table 2.b (BAN-PT format). Data comes from MahasiswaController@data_mahasiswa_asing
    (GET /sapto/mahasiswa-asing, no query params — TS/TS-1/TS-2 are hardcoded
    server-side) and @detail_mahasiswa_asing for the drilldown drawer. Only the
    "Full-time" column is clickable: the detail endpoint always filters to
    foreign students, so it can't back the "Aktif" count (local + foreign) or
    the "Part-time" count (not tracked yet, always 0) without showing numbers
    that don't match what the drawer actually returns.
--}}
<div class="ma-intro glass mb-5 rounded-2xl px-4 py-3.5 text-[13px] leading-relaxed text-muted">
    Diisi oleh pengusul dari Program Studi pada program Sarjana/Sarjana Terapan/Magister/Magister Terapan/Doktor/Doktor Terapan.
</div>

<section id="ma-panel-section" class="glass overflow-hidden rounded-2xl" data-view="kartu">
    <header class="tp-head flex items-center justify-between gap-3 px-4 py-3.5" style="border-bottom:1px solid var(--color-line)">
        <h2 class="text-[15px] font-bold text-ink">Mahasiswa Asing</h2>
        <span id="ma-table-status" class="text-[12px] text-muted">Memuat data...</span>
        {{-- Khusus HP: ganti tampilan kartu <-> tabel lengkap (lihat app.css). --}}
        <button type="button" class="tp-view-btn sm:hidden" data-view-toggle>Lihat sebagai tabel</button>
    </header>

    <div class="tp-toolbar flex flex-wrap items-center gap-2 px-4 py-3" style="border-bottom:1px solid var(--color-line)">
        <div class="relative min-w-[180px] flex-1">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <input
                id="ma-table-search"
                type="search"
                placeholder="Cari program studi..."
                aria-label="Cari program studi"
                class="w-full rounded-xl py-2 pl-8 pr-3 text-[13px] text-ink transition focus:outline-none focus:ring-2 focus:ring-accent/25"
                style="background:var(--input-bg);border:1px solid var(--input-border)"
            >
        </div>
        <button
            type="button"
            id="ma-table-export"
            title="Unduh tabel ini sebagai file Excel (.csv)"
            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-[12.5px] font-semibold text-ink transition hover:bg-accent-soft"
            style="background:var(--input-bg);border:1px solid var(--input-border)"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
            </svg>
            Export Excel
        </button>
    </div>

    <div id="ma-table-cards" class="table-cards"></div>

    <div class="table-scroll">
        <table class="w-full min-w-[980px] border-collapse">
            <thead id="ma-table-head"></thead>
            <tbody id="ma-table-body"></tbody>
            <tfoot id="ma-table-foot"></tfoot>
        </table>
    </div>

    <div class="swipe-hint">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        Geser tabel ke samping untuk melihat kolom lain
    </div>
</section>
