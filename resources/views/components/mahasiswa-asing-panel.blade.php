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
<div class="mb-5 rounded-xl border border-line bg-surface px-4 py-3.5 text-[13px] leading-relaxed text-muted">
    Diisi oleh pengusul dari Program Studi pada program Sarjana/Sarjana Terapan/Magister/Magister Terapan/Doktor/Doktor Terapan.
</div>

<section class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5">
        <h2 class="text-[15px] font-bold text-ink">Mahasiswa Asing</h2>
        <span id="ma-table-status" class="text-[12px] text-muted">Memuat data...</span>
    </header>

    <div class="w-full overflow-x-auto">
        <table class="w-full min-w-[980px] border-collapse">
            <thead id="ma-table-head"></thead>
            <tbody id="ma-table-body"></tbody>
            <tfoot id="ma-table-foot"></tfoot>
        </table>
    </div>
</section>
