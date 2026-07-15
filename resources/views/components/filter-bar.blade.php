{{-- resources/views/components/filter-bar.blade.php --}}
{{--
    Akses korprodi biasa selalu dikunci ke satu program studi (lihat
    SaptoUserContext::fromRequest) — Fakultas/Program Studi di sini tersembunyi
    buat mereka. Akun admin (is_admin=true) dapat can_select_fakultas/
    can_select_program_studi=true dari API, dan JS (sapto.blade.php) yang
    nampilin dua field ini buat akun itu.
--}}
<div class="glass mb-4 grid grid-cols-1 gap-2.5 rounded-2xl p-3.5 sm:mb-5 sm:grid-cols-4 sm:gap-3 sm:p-4 lg:grid-cols-[110px_1fr_1.4fr_1fr]">

    <div class="flex flex-col gap-1.5">
        <label for="ts" class="text-[11px] font-semibold uppercase tracking-wide text-muted">TS</label>
        <select id="ts" class="filter-select"></select>
    </div>

    <div id="fakultas-wrap" class="hidden flex-col gap-1.5">
        <label for="fakultas" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Fakultas</label>
        <select id="fakultas" class="filter-select"></select>
    </div>

    <div id="program-studi-wrap" class="hidden flex-col gap-1.5">
        <label for="program-studi" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Program Studi</label>
        <select id="program-studi" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Unit Aktif</span>
        <div id="active-unit" class="unit-pill">Universitas Sriwijaya</div>
    </div>
</div>
