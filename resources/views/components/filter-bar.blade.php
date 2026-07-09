{{-- resources/views/components/filter-bar.blade.php --}}
{{--
    Mobile (<640px) stacks all four fields full-width: Fakultas/Program Studi
    options and the Unit Aktif pill can run long (full faculty/prodi names),
    so a half-width column truncates them. From sm: up there's enough room
    for the original 4-across grid.
--}}
<div class="glass mb-4 grid grid-cols-1 gap-2.5 rounded-2xl p-3.5 sm:mb-5 sm:grid-cols-4 sm:gap-3 sm:p-4 lg:grid-cols-[110px_1fr_1.4fr_1fr]">

    <div class="flex flex-col gap-1.5">
        <label for="ts" class="text-[11px] font-semibold uppercase tracking-wide text-muted">TS</label>
        <select id="ts" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="fakultas" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Fakultas</label>
        <select id="fakultas" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="program-studi" class="text-[11px] font-semibold uppercase tracking-wide text-muted">Program Studi</label>
        <select id="program-studi" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Unit Aktif</span>
        <div id="active-unit" class="unit-pill">Universitas Sriwijaya</div>
    </div>
</div>
