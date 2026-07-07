{{-- resources/views/components/table-panel.blade.php --}}
<section class="glass overflow-hidden rounded-2xl">
    <header class="flex items-center justify-between gap-3 px-4 py-3.5" style="border-bottom:1px solid var(--color-line)">
        <h2 class="text-[15px] font-bold text-ink">Dosen Pembimbing Utama Tugas Akhir</h2>
        <span id="table-status" class="text-[12px] text-muted">Memuat data...</span>
    </header>

    <div class="table-scroll">
        <table class="w-full min-w-[1120px] border-collapse">
            <thead id="table-head"></thead>
            <tbody id="table-body"></tbody>
            <tfoot id="table-foot"></tfoot>
        </table>
    </div>

    <div class="swipe-hint">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        Geser tabel ke samping untuk melihat kolom lain
    </div>

    <div id="table-pagination" class="flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row" style="border-top:1px solid var(--color-line)"></div>
</section>
