{{-- resources/views/components/filter-bar.blade.php --}}
{{--
    Akses selalu dikunci ke satu program studi milik akun yang login (lihat
    SaptoUserContext::fromRequest) — tidak ada lagi pilihan Fakultas/Program
    Studi, cuma TS dan tampilan Unit Aktif.
--}}
<div class="glass mb-4 grid grid-cols-1 gap-2.5 rounded-2xl p-3.5 sm:mb-5 sm:grid-cols-2 sm:gap-3 sm:p-4 lg:grid-cols-[110px_1fr]">

    <div class="flex flex-col gap-1.5">
        <label for="ts" class="text-[11px] font-semibold uppercase tracking-wide text-muted">TS</label>
        <select id="ts" class="filter-select"></select>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Unit Aktif</span>
        <div id="active-unit" class="unit-pill">Universitas Sriwijaya</div>
    </div>
</div>
