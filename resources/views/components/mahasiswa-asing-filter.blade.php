{{--
    resources/views/components/mahasiswa-asing-filter.blade.php

    Korprodi biasa selalu discope server-side ke satu program studi (lihat
    MahasiswaController::data_mahasiswa_asing) — Fakultas/Program Studi di
    sini tersembunyi buat mereka, sama seperti filter-bar.blade.php. Akun
    admin (is_admin=true) dapat can_select_fakultas/can_select_program_studi
    dari API dan JS yang nampilin dua field ini.
--}}
<div id="ma-filter-bar" class="glass mb-4 grid grid-cols-1 gap-2.5 rounded-2xl p-3.5 sm:mb-5 sm:grid-cols-4 sm:gap-3 sm:p-4 lg:grid-cols-[110px_1fr_1.4fr_1fr]">
    <div class="flex flex-col gap-1.5">
        <label for="ma-ts" class="text-[11px] font-semibold uppercase tracking-wide text-muted">TS</label>
        <select id="ma-ts" class="filter-select"></select>
    </div>

    <div id="ma-fakultas-wrap" class="hidden flex-col gap-1.5">
        <label for="ma-fakultas" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Fakultas</label>
        <select id="ma-fakultas" class="filter-select"></select>
    </div>

    <div id="ma-program-studi-wrap" class="hidden flex-col gap-1.5">
        <label for="ma-program-studi" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Program Studi</label>
        <select id="ma-program-studi" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Unit Aktif</span>
        <div id="ma-active-unit" class="unit-pill">Universitas Sriwijaya</div>
    </div>
</div>
