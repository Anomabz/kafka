{{-- resources/views/components/card-sheet.blade.php --}}
{{-- Bottom sheet detail satu baris untuk tampilan kartu (layar < sm). Isinya
     diisi renderSheet() di sapto.blade.php. Tidak pernah tampil di >= sm. --}}
<div id="sheet-backdrop" class="m-sheet-backdrop"></div>

<section id="card-sheet" class="m-sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
    <div class="m-sheet-handle" aria-hidden="true"></div>

    <header class="m-sheet-head">
        <div class="min-w-0 flex-1">
            <h3 id="sheet-title" class="m-sheet-title"></h3>
            <div id="sheet-subtitle" class="m-sheet-sub"></div>
        </div>
        <button type="button" id="sheet-close" class="m-btn-secondary m-btn-compact">Tutup</button>
    </header>

    <div id="sheet-body" class="m-sheet-body"></div>

    <footer class="m-sheet-foot">
        <button type="button" id="sheet-prev" class="m-btn-secondary">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            Sebelumnya
        </button>
        <span id="sheet-position" class="m-sheet-pos" aria-live="polite"></span>
        <button type="button" id="sheet-next" class="m-btn-secondary">
            Berikutnya
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
        </button>
    </footer>
</section>
