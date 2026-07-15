{{--
    resources/views/components/mahasiswa-asing-filter.blade.php

    Data /sapto/mahasiswa-asing sudah discope server-side ke satu program
    studi akun (lihat MahasiswaController::data_mahasiswa_asing) — tidak ada
    lagi pilihan Fakultas/Program Studi, cuma tampilan Unit Aktif.
--}}
<div class="glass mb-4 grid grid-cols-1 gap-2.5 rounded-2xl p-3.5 sm:mb-5 sm:p-4">
    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted">Unit Aktif</span>
        <div id="ma-active-unit" class="unit-pill">Universitas Sriwijaya</div>
    </div>
</div>
