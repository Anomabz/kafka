{{-- resources/views/components/table-panel.blade.php --}}
<section class="glass overflow-hidden rounded-2xl">
    <header class="flex items-center justify-between gap-3 px-4 py-3.5" style="border-bottom:1px solid var(--color-line)">
        <h2 class="text-[15px] font-bold text-ink">Dosen Pembimbing Utama Tugas Akhir</h2>
        <span id="table-status" class="text-[12px] text-muted">Memuat data...</span>
    </header>

    <div class="w-full overflow-x-auto">
        <table class="w-full min-w-[1120px] border-collapse">
            <thead id="table-head"></thead>
            <tbody id="table-body"></tbody>
            <tfoot id="table-foot"></tfoot>
        </table>
    </div>

    <div id="table-pagination" class="flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row" style="border-top:1px solid var(--color-line)"></div>
</section>
