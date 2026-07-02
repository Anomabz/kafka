{{-- resources/views/components/filter-bar.blade.php --}}
<div class="mb-5 grid grid-cols-2 gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-4 lg:grid-cols-[110px_1fr_1.4fr_1fr]">

    <div class="flex flex-col gap-1.5">
        <label for="ts" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">TS</label>
        <select id="ts" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="fakultas" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fakultas</label>
        <select id="fakultas" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="program-studi" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Program Studi</label>
        <select id="program-studi" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Unit Aktif</span>
        <div
            id="active-unit"
            class="inline-flex min-h-[38px] w-fit items-center rounded-full border border-accent-line bg-accent-soft px-3 text-[12px] font-semibold text-accent-dark"
        >
            Universitas Sriwijaya
        </div>
    </div>
</div>
