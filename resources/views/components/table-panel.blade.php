{{-- resources/views/components/table-panel.blade.php --}}
<section id="table-panel-section" class="glass overflow-hidden rounded-2xl">
    <header class="flex items-center justify-between gap-3 px-4 py-3.5" style="border-bottom:1px solid var(--color-line)">
        <h2 id="table-card-title" class="text-[15px] font-bold text-ink">Dosen Pembimbing Utama Tugas Akhir</h2>
        <span id="table-status" class="text-[12px] text-muted">Memuat data...</span>
    </header>

    <div class="flex flex-wrap items-center gap-2 px-4 py-3" style="border-bottom:1px solid var(--color-line)">
        <div class="relative min-w-[180px] flex-1">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <input
                id="table-search"
                type="search"
                placeholder="Cari di tabel ini..."
                aria-label="Cari di tabel ini"
                class="w-full rounded-xl py-2 pl-8 pr-3 text-[13px] text-ink transition focus:outline-none focus:ring-2 focus:ring-accent/25"
                style="background:var(--input-bg);border:1px solid var(--input-border)"
            >
        </div>
        <button
            type="button"
            id="table-export"
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

    <div class="table-scroll">
        <table class="w-full min-w-[1120px] border-collapse">
            <thead id="table-head"></thead>
            <tbody id="table-body"></tbody>
            <tfoot id="table-foot"></tfoot>
        </table>
    </div>

    <div class="swipe-hint">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        Geser tabel ke samping untuk melihat kolom lain
    </div>

    <div id="table-pagination" class="flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row" style="border-top:1px solid var(--color-line)"></div>
</section>
