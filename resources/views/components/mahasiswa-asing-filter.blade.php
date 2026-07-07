{{--
    resources/views/components/mahasiswa-asing-filter.blade.php

    Filter role untuk Tabel 2.b. Selector Role ada di topbar (global); di sini
    Fakultas + Program Studi menyesuaikan role — lihat wiring #ma-fakultas /
    #ma-program-studi di sapto.blade.php. Semua difilter di sisi klien karena
    datanya kecil (hanya prodi yang punya mahasiswa asing).
--}}
<div class="mb-5 grid grid-cols-1 gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[1.3fr_1.5fr_1fr]">

    <div class="flex flex-col gap-1.5" id="ma-fakultas-wrap">
        <label for="ma-fakultas" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fakultas</label>
        <select id="ma-fakultas" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5" id="ma-prodi-wrap">
        <label for="ma-program-studi" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Program Studi</label>
        <select id="ma-program-studi" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Unit Aktif</span>
        <div
            id="ma-active-unit"
            class="inline-flex min-h-[38px] w-fit items-center rounded-full border border-accent-line bg-accent-soft px-3 text-[12px] font-semibold text-accent-dark"
        >
            Universitas Sriwijaya
        </div>
    </div>
</div>
