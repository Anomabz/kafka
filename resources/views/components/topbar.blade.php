{{-- resources/views/components/topbar.blade.php --}}
<header class="flex flex-col gap-4 border-b border-line bg-surface px-4 py-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between lg:px-8">
    <div class="flex items-start gap-3">
        <button
            type="button"
            id="sidebar-toggle"
            aria-label="Buka/tutup sidebar"
            aria-controls="sidebar"
            aria-expanded="true"
            class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-line text-slate-500 transition hover:bg-slate-100 hover:text-ink"
        >
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
        </button>
        <div>
            <h1 id="page-title" class="text-[19px] font-bold leading-tight text-ink sm:text-[21px]">
                Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir
            </h1>
            <p id="page-description" class="mt-1 text-[13px] leading-relaxed text-muted">
                Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.
            </p>
        </div>
    </div>

    <div class="flex items-center gap-2.5">
        <label for="role" class="text-[12px] font-semibold text-slate-600">Role</label>
        <select
            id="role"
            aria-label="Role pengguna"
            class="min-h-[38px] rounded-lg border border-slate-300 bg-white px-3 text-[13px] text-ink
                   transition focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/25"
        >
            <option value="universitas">Pengelola SAPTO Universitas</option>
            <option value="fakultas">Pengelola Fakultas</option>
            <option value="prodi">Pengelola Program Studi</option>
        </select>
    </div>
</header>
