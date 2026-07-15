{{-- resources/views/components/topbar.blade.php --}}
<header class="glass-strong sticky top-0 z-20 flex items-center gap-3 px-4 py-3 sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
    <button
        type="button"
        id="sidebar-toggle"
        aria-label="Buka/tutup sidebar"
        aria-controls="sidebar"
        aria-expanded="true"
        class="theme-toggle shrink-0"
    >
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
    </button>
    <div class="min-w-0 flex-1">
        <h1 id="page-title" class="truncate text-[15px] font-bold leading-tight text-ink sm:overflow-visible sm:whitespace-normal sm:text-[17px] lg:text-[21px]">
            Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir
        </h1>
        <p id="page-description" class="mt-1 hidden text-[12px] leading-relaxed text-muted sm:block sm:text-[13px]">
            Data jumlah mahasiswa yang dibimbing oleh dosen sebagai pembimbing utama tugas akhir berdasarkan program studi dan tahun TS.
        </p>
    </div>

    <button
        type="button"
        id="theme-toggle"
        class="theme-toggle shrink-0"
        aria-label="Ganti mode terang/gelap"
        title="Ganti mode terang/gelap"
    >
        <svg class="icon-moon h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
        </svg>
        <svg class="icon-sun h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        </svg>
    </button>
</header>
