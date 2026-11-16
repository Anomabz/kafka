@extends('layouts.admin')

@section('title', 'Sinkronisasi PD UNSRI')
@section('subtitle', 'Sinkronkan satu tabel atau seluruh tabel sumber PD UNSRI ke database SAPTO.')

@push('styles')
<style>
    .sync-page {
        --sync-ok: #15803d;
        --sync-ok-soft: rgba(21, 128, 61, 0.11);
        --sync-ok-line: rgba(21, 128, 61, 0.30);
        --sync-err: #b91c1c;
        --sync-err-soft: rgba(185, 28, 28, 0.09);
        --sync-err-line: rgba(185, 28, 28, 0.30);
        display: grid;
        gap: 1rem;
    }

    :root[data-theme='dark'] .sync-page {
        --sync-ok: #4ade80;
        --sync-ok-soft: rgba(74, 222, 128, 0.13);
        --sync-ok-line: rgba(74, 222, 128, 0.30);
        --sync-err: #f87171;
        --sync-err-soft: rgba(248, 113, 113, 0.12);
        --sync-err-line: rgba(248, 113, 113, 0.32);
    }

    .sync-h2 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: -0.01em; color: var(--color-ink); }
    .sync-muted { color: var(--color-muted); }
    .sync-num { font-variant-numeric: tabular-nums; }

    /* --- Kartu ringkasan ------------------------------------------------ */
    .sync-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.75rem; }

    .sync-card { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; padding: 1rem 1.1rem; border-radius: 1.1rem; }
    .sync-card-label { font-size: 11px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; color: var(--color-muted); }
    .sync-card-value { display: flex; align-items: center; gap: 0.5rem; font-size: 17px; font-weight: 800; line-height: 1.25; color: var(--color-ink); }
    .sync-card-sub { font-size: 12.5px; line-height: 1.5; color: var(--color-muted); overflow-wrap: anywhere; }
    .sync-card-sub b { font-weight: 700; color: var(--color-ink); }

    .sync-dot { flex-shrink: 0; width: 10px; height: 10px; border-radius: 999px; background: var(--color-muted); box-shadow: 0 0 0 4px var(--color-slate-soft); }
    .sync-dot.is-ok { background: var(--sync-ok); box-shadow: 0 0 0 4px var(--sync-ok-soft); }
    .sync-dot.is-warn { background: var(--color-warn); box-shadow: 0 0 0 4px var(--color-warn-soft); }
    .sync-dot.is-err { background: var(--sync-err); box-shadow: 0 0 0 4px var(--sync-err-soft); }
    .sync-dot.is-run { background: var(--color-accent); box-shadow: 0 0 0 4px var(--color-accent-soft); animation: sync-pulse 1.6s ease-in-out infinite; }

    @keyframes sync-pulse { 50% { box-shadow: 0 0 0 7px transparent; } }

    /* --- Panel berjalan ------------------------------------------------- */
    .sync-run { padding: 1.1rem 1.1rem 1.2rem; }
    .sync-run-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 0.9rem; }
    .sync-run-head > div:first-child { min-width: 0; flex: 1 1 18rem; }
    .sync-run-title { display: flex; align-items: center; gap: 0.55rem; margin: 0; font-size: 17px; font-weight: 800; color: var(--color-ink); }
    .sync-run-sub { margin: 0.3rem 0 0; font-size: 12.5px; line-height: 1.55; color: var(--color-muted); overflow-wrap: anywhere; }

    .sync-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .sync-actions form { margin: 0; }
    .sync-table-select {
        min-width: min(20rem, 70vw); max-width: 28rem; min-height: 40px; padding: 0.5rem 0.75rem;
        border: 1px solid var(--input-border); border-radius: 0.75rem; color: var(--color-ink); background: var(--input-bg);
    }

    .sync-btn {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.7rem 1.1rem; border-radius: 0.8rem;
        font: inherit; font-size: 13.5px; font-weight: 800; cursor: pointer;
        transition: transform 120ms ease, filter 120ms ease, border-color 120ms ease, opacity 120ms ease;
    }
    .sync-btn svg { width: 16px; height: 16px; }
    .sync-btn-primary { border: 0; color: #ffffff; background: #4b7a42; box-shadow: none; }
    .sync-btn-primary:hover:not(:disabled) { filter: brightness(1.06); }
    .sync-btn-ghost { border: 1px solid var(--input-border); color: var(--color-ink); background: var(--input-bg); }
    .sync-btn-ghost:hover:not(:disabled) { border-color: var(--color-accent-line); }
    .sync-btn-danger { border: 1px solid var(--sync-err-line); color: var(--sync-err); background: var(--sync-err-soft); }
    .sync-btn-danger:hover:not(:disabled) { filter: brightness(1.05); }
    .sync-btn-danger-solid { border: 0; color: #ffffff; background: #b91c1c; }
    .sync-btn:disabled { cursor: not-allowed; opacity: 0.55; box-shadow: none; }

    .sync-progress { margin-top: 1rem; }
    .sync-track { position: relative; height: 10px; border-radius: 999px; background: var(--color-line); overflow: hidden; }
    .sync-fill { height: 100%; width: 0; border-radius: 999px; background: #4b7a42; transition: width 400ms ease; }
    .sync-fill.is-failed { background: #b45309; }
    .sync-fill.is-done { background: #15803d; }
    .sync-progress-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.25rem 1rem; margin-top: 0.45rem; font-size: 12px; font-weight: 700; color: var(--color-muted); }

    .sync-current { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.9rem; margin-top: 0.9rem; padding: 0.75rem 0.9rem; border-radius: 0.9rem; font-size: 13px; color: var(--color-ink); background: var(--color-accent-soft); box-shadow: inset 0 0 0 1px var(--color-accent-line); }
    .sync-current code { font-weight: 700; overflow-wrap: anywhere; }
    .sync-current .sync-mini { flex: 1 1 10rem; min-width: 8rem; }
    .sync-mini .sync-track { display: block; height: 6px; }

    .sync-note { display: flex; gap: 0.6rem; margin-top: 0.8rem; padding: 0.75rem 0.9rem; border-radius: 0.9rem; font-size: 12.5px; line-height: 1.55; color: var(--color-muted); background: var(--input-bg); box-shadow: inset 0 0 0 1px var(--color-line); }
    .sync-note svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; }
    .sync-note.is-warn { color: var(--color-warn); background: var(--color-warn-soft); box-shadow: inset 0 0 0 1px var(--color-warn-line); }
    .sync-note.is-err { color: var(--sync-err); background: var(--sync-err-soft); box-shadow: inset 0 0 0 1px var(--sync-err-line); }
    .sync-note b { color: inherit; }

    /* --- Daftar tabel --------------------------------------------------- */
    .sync-rows { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--color-line); }
    .sync-row {
        display: grid;
        grid-template-areas: "main status" "rows rows" "when when" "reason reason";
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.35rem 0.9rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--color-line);
    }
    .sync-row:last-child { border-bottom: 0; }
    .sync-row:hover { background: var(--row-hover); }
    .sync-row.is-running { box-shadow: inset 3px 0 0 var(--color-accent); }
    .sync-row.is-failed { box-shadow: inset 3px 0 0 var(--sync-err); }
    .sync-row.is-skipped { box-shadow: inset 3px 0 0 var(--color-warn); }

    @media (min-width: 820px) {
        .sync-row {
            grid-template-areas: "main status rows when" "reason reason reason reason";
            grid-template-columns: minmax(0, 1.5fr) 8.5rem minmax(0, 1fr) minmax(0, 0.9fr);
        }
    }

    .sync-row-main { grid-area: main; min-width: 0; }
    .sync-tname { display: block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; font-weight: 700; color: var(--color-ink); overflow-wrap: anywhere; }
    .sync-codes { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.3rem; }
    .sync-code { padding: 0.1rem 0.45rem; border-radius: 0.4rem; font-size: 10.5px; font-weight: 800; color: var(--color-accent); background: var(--color-accent-soft); }
    .sync-row-sync { margin-top: 0.45rem; padding: 0.35rem 0.7rem; border-radius: 0.6rem; font-size: 11px; }

    .sync-status { grid-area: status; display: inline-flex; align-items: center; justify-self: start; gap: 0.35rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 11.5px; font-weight: 800; white-space: nowrap; color: var(--color-muted); background: var(--color-slate-soft); }
    .sync-status::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
    .sync-status.is-ok { color: var(--sync-ok); background: var(--sync-ok-soft); }
    .sync-status.is-run { color: var(--color-accent); background: var(--color-accent-soft); }
    .sync-status.is-warn { color: var(--color-warn); background: var(--color-warn-soft); }
    .sync-status.is-err { color: var(--sync-err); background: var(--sync-err-soft); }
    .sync-status .sync-est { font-weight: 700; opacity: 0.75; }

    .sync-rows-info, .sync-when { font-size: 12.5px; line-height: 1.45; color: var(--color-muted); min-width: 0; }
    .sync-rows-info { grid-area: rows; }
    .sync-when { grid-area: when; }
    .sync-rows-info b, .sync-when b { font-weight: 700; color: var(--color-ink); }
    @media (min-width: 820px) { .sync-status { justify-self: start; } }

    .sync-reason { grid-area: reason; font-size: 12.5px; }
    .sync-reason summary { cursor: pointer; font-weight: 700; color: var(--color-muted); }
    .sync-reason summary:hover { color: var(--color-ink); }
    .sync-reason p { margin: 0.4rem 0 0; padding: 0.6rem 0.75rem; border-radius: 0.7rem; line-height: 1.5; color: var(--color-ink); background: var(--input-bg); box-shadow: inset 0 0 0 1px var(--color-line); overflow-wrap: anywhere; }

    /* --- Riwayat + cara kerja ------------------------------------------- */
    .sync-two { display: grid; gap: 1rem; }
    @media (min-width: 960px) { .sync-two { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; } }

    .sync-sect-head { padding: 1rem 1rem 0.6rem; }
    .sync-history { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--color-line); }
    .sync-history li { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem 0.75rem; padding: 0.7rem 1rem; border-bottom: 1px solid var(--color-line); font-size: 12.5px; color: var(--color-muted); }
    .sync-history li:last-child { border-bottom: 0; }
    .sync-history .sync-h-when { flex: 1 1 12rem; min-width: 0; font-weight: 700; color: var(--color-ink); }
    .sync-history .sync-h-meta { flex-basis: 100%; }

    .sync-how { padding: 0.25rem 1rem 1rem; }
    .sync-how > summary { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.75rem 0; cursor: pointer; list-style: none; font-size: 15px; font-weight: 800; color: var(--color-ink); }
    .sync-how > summary::-webkit-details-marker { display: none; }
    .sync-how > summary::after { content: '+'; font-size: 18px; font-weight: 700; color: var(--color-muted); }
    .sync-how[open] > summary::after { content: '–'; }
    .sync-steps { margin: 0; padding: 0; list-style: none; counter-reset: step; display: grid; gap: 0.6rem; }
    .sync-steps li { position: relative; padding-left: 2.1rem; font-size: 13px; line-height: 1.55; color: var(--color-muted); counter-increment: step; }
    .sync-steps li::before { content: counter(step); position: absolute; left: 0; top: 0; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 999px; font-size: 11.5px; font-weight: 800; color: var(--color-accent); background: var(--color-accent-soft); }
    .sync-steps b { color: var(--color-ink); }
    .sync-how p { margin: 0.8rem 0 0; font-size: 12.5px; line-height: 1.55; color: var(--color-muted); }

    .sync-updated { margin: 0; font-size: 11.5px; text-align: right; color: var(--color-muted); }

    /* --- Dialog (disalin dari admin/peran.blade.php, style-nya lokal di sana) */
    .adm-dialog { margin: auto; width: min(34rem, calc(100vw - 2rem)); max-width: none; max-height: min(44rem, calc(100dvh - 2rem)); padding: 0; border: 0; border-radius: 1.25rem; overflow: hidden; color: var(--color-ink); background: var(--color-surface); box-shadow: 0 0 0 1px var(--color-line); }
    .adm-dialog[open] { display: flex; flex-direction: column; animation: adm-pop 180ms cubic-bezier(0.16, 1, 0.3, 1); }
    .adm-dialog::backdrop { background: rgba(8, 12, 28, 0.55); }
    @keyframes adm-pop { from { opacity: 0; transform: translateY(12px) scale(0.98); } to { opacity: 1; transform: none; } }
    @keyframes adm-sheet { from { transform: translateY(100%); } to { transform: none; } }
    .adm-dialog-head { position: relative; padding: 1.2rem 1.35rem 1rem; border-bottom: 1px solid var(--color-line); }
    .adm-dialog-head::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #4b7a42; }
    .adm-dialog-head.is-danger::before { background: #b91c1c; }
    .adm-dialog-head h2 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
    .adm-dialog-head p { margin: 0.25rem 0 0; font-size: 12.5px; color: var(--color-muted); }
    .adm-dialog-body { overflow-y: auto; padding: 1rem 1.35rem; }
    .adm-dialog-body ul { margin: 0; padding-left: 1.1rem; display: grid; gap: 0.45rem; font-size: 13px; line-height: 1.55; color: var(--color-muted); }
    .adm-dialog-body b { color: var(--color-ink); }
    .adm-dialog-foot { display: flex; justify-content: flex-end; gap: 0.6rem; padding: 0.9rem 1.35rem; border-top: 1px solid var(--color-line); background: var(--color-surface); }
    .adm-dialog-foot form { margin: 0; }
    @media (max-width: 640px) {
        .adm-dialog { width: 100vw; max-height: 92dvh; margin: auto 0 0; border-radius: 1.25rem 1.25rem 0 0; }
        .adm-dialog[open] { animation-name: adm-sheet; }
        .adm-dialog-foot > * { flex: 1; }
        .adm-dialog-foot .sync-btn { width: 100%; justify-content: center; }
    }

    @media (prefers-reduced-motion: reduce) {
        .sync-dot.is-run, .sync-track.is-running .sync-fill { animation: none; }
        .sync-fill, .sync-btn { transition: none; }
        .adm-dialog[open] { animation: none; }
    }
</style>
@endpush

@section('content')
    @if (session('status'))
        <div class="adm-toast adm-toast-ok" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <noscript>
        <div class="adm-toast adm-toast-err">Halaman ini butuh JavaScript untuk menampilkan status sinkronisasi.</div>
    </noscript>

    <div class="sync-page" id="sync-app"
         data-status-url="{{ route('admin.sync-pd-unsri.status') }}"
         data-api-url="{{ url('/api/sapto/pd-unsri/status') }}">

        <section class="sync-cards" aria-label="Ringkasan sinkronisasi">
            <article class="sync-card glass-strong">
                <span class="sync-card-label">Status API PD UNSRI</span>
                <span class="sync-card-value"><span class="sync-dot" id="api-dot"></span><span id="api-label">Memeriksa…</span></span>
                <span class="sync-card-sub" id="api-sub">Lewat Gravitee API Gateway</span>
            </article>
            <article class="sync-card glass-strong">
                <span class="sync-card-label">Sinkronisasi terakhir</span>
                <span class="sync-card-value" id="last-label">—</span>
                <span class="sync-card-sub" id="last-sub"></span>
            </article>
            <article class="sync-card glass-strong">
                <span class="sync-card-label">Jadwal berikutnya</span>
                <span class="sync-card-value" id="next-label">—</span>
                <span class="sync-card-sub" id="next-sub">Otomatis tiap minggu</span>
            </article>
            <article class="sync-card glass-strong">
                <span class="sync-card-label">Tabel sumber dipantau</span>
                <span class="sync-card-value sync-num" id="tables-label">—</span>
                <span class="sync-card-sub" id="tables-sub">Sumber data laporan LKPS yang sudah berjalan</span>
            </article>
        </section>

        <section class="glass-strong adm-panel sync-run" aria-labelledby="run-title">
            <div class="sync-run-head">
                <div>
                    <h2 class="sync-run-title" id="run-title"><span class="sync-dot" id="run-dot"></span><span id="run-title-text">—</span></h2>
                    <p class="sync-run-sub" id="run-sub"></p>
                </div>
                <div class="sync-actions">
                    <form method="POST" action="{{ route('admin.sync-pd-unsri.cancel') }}" id="form-cancel" hidden>
                        @csrf
                        <button type="submit" class="sync-btn sync-btn-danger" id="btn-cancel" data-dialog="dialog-cancel">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                            Batalkan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.sync-pd-unsri.store') }}" id="form-start-all">
                        @csrf
                        <input type="hidden" name="all" value="1">
                        <button type="submit" class="sync-btn sync-btn-ghost" id="btn-start-all" data-dialog="dialog-start-all">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><path d="m8 9 2 2 4-4"/></svg>
                            Sinkronkan Semua Tabel
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.sync-pd-unsri.store') }}" id="form-start">
                        @csrf
                        <label class="sr-only" for="sync-table">Pilih tabel yang akan disinkronkan</label>
                        <select class="sync-table-select" name="table" id="sync-table" required>
                            @foreach ($syncTables as $slug => $table)
                                <option value="{{ $table }}">{{ $table }} ({{ $slug }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="sync-btn sync-btn-primary" id="btn-start" data-dialog="dialog-start">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                            Sinkronkan Tabel Ini
                        </button>
                    </form>
                </div>
            </div>

            <div class="sync-progress" id="run-progress" hidden>
                <div class="sync-track" id="run-track" role="progressbar" aria-label="Progres sinkronisasi" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="sync-fill" id="run-fill"></div>
                </div>
                <div class="sync-progress-meta"><span id="run-count" class="sync-num"></span><span id="run-elapsed" class="sync-num"></span></div>
            </div>

            <div id="run-current" aria-live="polite"></div>
            <div id="run-notes"></div>
        </section>

        <section class="glass-strong adm-panel" aria-labelledby="tables-title">
            <div class="adm-toolbar">
                <h2 class="sync-h2" id="tables-title">Tabel sumber</h2>
                <div class="adm-chips" role="group" aria-label="Saring menurut status" id="filter-chips">
                    <button type="button" class="adm-chip" data-filter="semua" aria-pressed="true">Semua <b data-count="semua">0</b></button>
                    <button type="button" class="adm-chip" data-filter="done" aria-pressed="false">Selesai <b data-count="done">0</b></button>
                    <button type="button" class="adm-chip adm-chip-warn" data-filter="skipped" aria-pressed="false">Dilewati <b data-count="skipped">0</b></button>
                    <button type="button" class="adm-chip adm-chip-warn" data-filter="failed" aria-pressed="false">Gagal <b data-count="failed">0</b></button>
                </div>
            </div>
            <p class="adm-count" id="tables-note"></p>
            <ul class="sync-rows" id="table-rows"></ul>
        </section>

        <div class="sync-two">
            <section class="glass-strong adm-panel" aria-labelledby="history-title">
                <div class="sync-sect-head"><h2 class="sync-h2" id="history-title">Riwayat 5 sinkronisasi terakhir</h2></div>
                <ol class="sync-history" id="history"></ol>
            </section>

            <section class="glass-strong adm-panel">
                <details class="sync-how">
                    <summary>Cara kerja sinkronisasi</summary>
                    <ol class="sync-steps">
                        <li><b>Pemicu manual.</b> Sinkronkan satu tabel yang dipilih atau klik <b>Sinkronkan Semua Tabel</b> untuk menarik seluruh sumber.</li>
                        <li><b>Antrean.</b> Permintaan manual dijalankan di latar belakang oleh <i>queue worker</i>, jadi browser tidak perlu dibiarkan terbuka.</li>
                        <li><b>Tabel sementara.</b> Untuk tiap tabel dibuat salinan kosong (<code>_sync_staging</code>). Data baru ditarik ke sana dulu; tabel yang dibaca laporan belum disentuh.</li>
                        <li><b>Lewat Gravitee.</b> Data ditarik 1.000 baris per permintaan dari API PD UNSRI melalui Gravitee API Gateway. Hanya permintaan token login yang langsung ke server kampus.</li>
                        <li><b>Pemeriksaan keamanan.</b> Tabel <b>tidak</b> diganti kalau sumber membalas 0 baris, kalau baris yang diterima kurang dari 99% total sumber, atau kalau tabelnya punya relasi foreign key. Tabel yang tidak tersedia di sumber (404) atau belum diizinkan (403) dilewati.</li>
                        <li><b>Data pribadi dipertahankan.</b> Kolom yang sengaja dirahasiakan API (NIK, alamat, kontak, orang tua) disalin dari data lama supaya tidak hilang.</li>
                        <li><b>Ganti sekaligus.</b> Tabel sementara ditukar dengan tabel asli dalam satu langkah. Laporan tidak pernah melihat tabel kosong atau setengah terisi; kalau ada yang gagal, data lama tetap dipakai.</li>
                        <li><b>Hitung ulang laporan.</b> Setelah tabel terpilih selesai, cache laporan dihapus agar laporan membaca data terbaru.</li>
                    </ol>
                    <p>Kalau ada tabel gagal atau dilewati, buka alasannya di daftar di atas. Detail teknis ada di <code>storage/logs/laravel.log</code> (cari “PD UNSRI sync”) dan <code>storage/logs/queue-worker.log</code>.</p>
                </details>
            </section>
        </div>

        <p class="sync-updated" id="updated-at"></p>
    </div>

    <dialog class="adm-dialog" id="dialog-start" aria-labelledby="dialog-start-title">
        <div class="adm-dialog-head">
            <h2 id="dialog-start-title">Sinkronkan tabel terpilih?</h2>
            <p>Hanya tabel yang dipilih akan ditarik ulang dari PD UNSRI.</p>
        </div>
        <div class="adm-dialog-body">
            <ul>
                <li>Hanya satu tabel yang ditarik lewat Gravitee.</li>
                <li>Selama berjalan, laporan <b>tetap memakai data lama</b>. Tiap tabel baru diganti setelah tarikannya lengkap.</li>
                <li>Tabel yang gagal atau dilewati tetap memakai data lama, tidak dikosongkan.</li>
                <li>Setelah selesai, cache laporan dihitung ulang otomatis.</li>
            </ul>
            <div class="sync-note is-warn" id="dialog-api-warn" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <span id="dialog-api-warn-text"></span>
            </div>
        </div>
        <div class="adm-dialog-foot">
            <form method="dialog"><button class="sync-btn sync-btn-ghost" value="batal">Batal</button></form>
            <button type="submit" form="form-start" class="sync-btn sync-btn-primary">Ya, mulai</button>
        </div>
    </dialog>

    <dialog class="adm-dialog" id="dialog-start-all" aria-labelledby="dialog-start-all-title">
        <div class="adm-dialog-head">
            <h2 id="dialog-start-all-title">Sinkronkan seluruh tabel?</h2>
            <p>Sebanyak {{ count($syncTables) }} tabel sumber akan ditarik ulang dari PD UNSRI.</p>
        </div>
        <div class="adm-dialog-body">
            <ul>
                <li>Satu pekerjaan akan dibuat untuk setiap tabel dan dimasukkan ke batch antrean. Lama proses bergantung pada jumlah baris dan kapasitas worker.</li>
                <li>Selama proses berjalan, laporan tetap memakai data lama. Tiap tabel baru diganti setelah tarikannya lengkap.</li>
                <li>Tabel yang gagal atau dilewati tetap memakai data lama; cache laporan dihitung ulang setelah batch selesai.</li>
            </ul>
        </div>
        <div class="adm-dialog-foot">
            <form method="dialog"><button class="sync-btn sync-btn-ghost" value="batal">Batal</button></form>
            <button type="submit" form="form-start-all" class="sync-btn sync-btn-primary">Ya, sinkronkan semua</button>
        </div>
    </dialog>

    <dialog class="adm-dialog" id="dialog-cancel" aria-labelledby="dialog-cancel-title">
        <div class="adm-dialog-head is-danger">
            <h2 id="dialog-cancel-title">Batalkan sinkronisasi?</h2>
            <p>Sinkronisasi berhenti dengan aman. Tidak ada data yang rusak.</p>
        </div>
        <div class="adm-dialog-body">
            <ul>
                <li>Tabel yang <b>belum mulai</b> tidak ditarik dan tetap memakai data lama.</li>
                <li>Tabel yang <b>sedang ditarik</b> berhenti dengan aman atau diselesaikan dulu. Tabel tidak pernah tertukar setengah jadi.</li>
                <li>Tabel yang <b>sudah selesai</b> tetap memakai data barunya.</li>
            </ul>
        </div>
        <div class="adm-dialog-foot">
            <form method="dialog"><button class="sync-btn sync-btn-ghost" value="batal">Tidak jadi</button></form>
            <button type="submit" form="form-cancel" class="sync-btn sync-btn-danger-solid">Ya, batalkan</button>
        </div>
    </dialog>

    <script type="application/json" id="sync-initial">@json($payload)</script>
    <script>
    (function () {
        var app = document.getElementById('sync-app');
        var statusUrl = app.dataset.statusUrl;
        var apiUrl = app.dataset.apiUrl;
        var data = JSON.parse(document.getElementById('sync-initial').textContent);
        var apiState = null;
        var filter = 'semua';
        var skew = 0;
        var pollTimer = null;
        var stopped = false;

        var TZ = 'Asia/Jakarta';
        var fmtDate = new Intl.DateTimeFormat('id-ID', { timeZone: TZ, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        var fmtTime = new Intl.DateTimeFormat('id-ID', { timeZone: TZ, hour: '2-digit', minute: '2-digit' });
        var rtf = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });

        var $ = function (id) { return document.getElementById(id); };
        var now = function () { return Date.now() + skew; };
        var esc = function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        };
        var num = function (n) { return n == null ? '—' : Number(n).toLocaleString('id-ID'); };
        var abs = function (iso) { return iso ? fmtDate.format(new Date(iso)) + ' WIB' : '—'; };
        var clock = function (iso) { return iso ? fmtTime.format(new Date(iso)) + ' WIB' : '—'; };
        var rel = function (iso) {
            if (!iso) return '';
            var s = (new Date(iso).getTime() - now()) / 1000, a = Math.abs(s);
            if (a < 45) return 'baru saja';
            if (a < 3600) return rtf.format(Math.round(s / 60), 'minute');
            if (a < 86400) return rtf.format(Math.round(s / 3600), 'hour');
            return rtf.format(Math.round(s / 86400), 'day');
        };
        var dur = function (sec) {
            sec = Math.max(0, Math.round(sec));
            var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
            return h ? h + ' jam ' + m + ' mnt' : (m ? m + ' mnt ' + s + ' dtk' : s + ' dtk');
        };
        var since = function (iso) { return iso ? (now() - new Date(iso).getTime()) / 1000 : 0; };

        var STATUS = {
            done: ['Selesai', 'is-ok'],
            running: ['Menarik', 'is-run'],
            pending: ['Menunggu', ''],
            skipped: ['Dilewati', 'is-warn'],
            failed: ['Gagal', 'is-err'],
            cancelled: ['Dibatalkan', '']
        };
        var DIRECT_SYNC_TABLES = new Set([
            'aktivitas_mengajar_dosen',
            'lppm_pengabdian_dosen',
            'lppm_penelitian_dosen',
            'sister_list_kekayaan_intelektual',
            'sister_sdm'
        ]);
        var BATCH = {
            running: ['Sedang berjalan', 'is-run'],
            stalled: ['Terhenti — menunggu queue worker', 'is-warn'],
            finished: ['Selesai', 'is-ok'],
            cancelled: ['Dibatalkan', '']
        };

        var active = function () { return data.batch && (data.batch.state === 'running' || data.batch.state === 'stalled'); };
        var trigger = function (t) {
            if (!t) return 'tidak tercatat';
            if (t === 'jadwal') return 'jadwal mingguan';
            return t.indexOf('admin:') === 0 ? t.slice(6) : t;
        };
        var batchLabel = function (b) {
            var l = BATCH[b.state] || ['—', ''];
            if (b.state === 'finished' && b.failed > 0) return [b.failed + ' tabel gagal', 'is-warn'];
            return l;
        };
        var resultText = function (b, results) {
            if (results) {
                var parts = [results.done + ' selesai'];
                if (results.skipped) parts.push(results.skipped + ' dilewati');
                if (results.failed) parts.push(results.failed + ' gagal');
                if (results.cancelled) parts.push(results.cancelled + ' dibatalkan');
                return parts.join(' · ');
            }
            return b.processed + '/' + b.total + ' tabel diproses' + (b.failed ? ' · ' + b.failed + ' gagal' : '');
        };

        function renderCards() {
            var b = data.batch;
            var h = (data.history || [])[0];
            if (!b) {
                $('last-label').textContent = 'Belum pernah';
                $('last-sub').textContent = 'Belum ada sinkronisasi yang tercatat.';
            } else {
                var l = batchLabel(b);
                $('last-label').innerHTML = '<span class="sync-dot ' + l[1] + '"></span>' + esc(l[0]);
                $('last-sub').innerHTML = 'Mulai <b>' + esc(abs(b.started_at)) + '</b><br>' +
                    (active() ? 'Berjalan ' : 'Durasi ') + esc(dur(active() ? since(b.started_at) : b.duration_seconds)) +
                    ' · ' + esc(resultText(b, h && h.id === b.id ? h.results : null)) +
                    '<br>Dipicu oleh ' + esc(trigger(b.triggered_by));
            }

            $('next-label').textContent = abs(data.next_run_at);
            $('next-sub').textContent = 'Otomatis, ' + rel(data.next_run_at);

            var c = data.counts || {};
            $('tables-label').textContent = data.total_tables + ' tabel';
            var bits = [];
            if (c.done) bits.push(c.done + ' selesai');
            if (c.running) bits.push(c.running + ' sedang ditarik');
            if (c.pending) bits.push(c.pending + ' menunggu');
            if (c.skipped) bits.push(c.skipped + ' dilewati');
            if (c.failed) bits.push(c.failed + ' gagal');
            $('tables-sub').textContent = bits.length ? bits.join(' · ') : 'Sumber data laporan LKPS yang sudah berjalan';
            document.querySelectorAll('[data-total-tables]').forEach(function (el) { el.textContent = data.total_tables; });
        }

        function renderRun() {
            var b = data.batch;
            var isActive = active();

            $('btn-start').disabled = isActive;
            $('btn-start').title = isActive ? 'Tunggu sinkronisasi yang sedang berjalan selesai, atau batalkan dulu.' : '';
            $('btn-start-all').disabled = isActive;
            $('btn-start-all').title = isActive ? 'Tunggu sinkronisasi yang sedang berjalan selesai, atau batalkan dulu.' : '';
            $('form-cancel').hidden = !isActive;

            if (!b) {
                $('run-dot').className = 'sync-dot';
                $('run-title-text').textContent = 'Belum pernah disinkronkan';
                $('run-sub').textContent = 'Sinkronkan satu tabel atau seluruh tabel sumber secara manual.';
                $('run-progress').hidden = true;
                $('run-current').innerHTML = '';
                $('run-notes').innerHTML = '';
                return;
            }

            var l = batchLabel(b);
            $('run-dot').className = 'sync-dot ' + l[1];
            $('run-title-text').textContent = isActive ? l[0] : 'Tidak ada sinkronisasi berjalan';
            $('run-sub').innerHTML = (isActive ? 'Batch <b>' : 'Terakhir: batch <b>') + esc(b.short_id) + '</b> · dipicu oleh ' + esc(trigger(b.triggered_by)) +
                ' · mulai ' + esc(abs(b.started_at)) + (isActive ? '' : ' · ' + esc(l[0].toLowerCase()));

            $('run-progress').hidden = false;
            var track = $('run-track'), fill = $('run-fill');
            track.classList.toggle('is-running', b.state === 'running');
            fill.classList.toggle('is-failed', b.failed > 0 || b.state === 'stalled');
            fill.classList.toggle('is-done', b.state === 'finished' && b.failed === 0);
            fill.style.width = b.progress + '%';
            track.setAttribute('aria-valuenow', b.progress);
            $('run-count').textContent = b.processed + ' dari ' + b.total + ' tabel · ' + b.progress + '%';
            $('run-elapsed').dataset.since = isActive ? b.started_at : '';
            $('run-elapsed').textContent = isActive ? 'berjalan ' + dur(since(b.started_at)) : 'durasi ' + dur(b.duration_seconds);

            var cur = b.current;
            if (isActive && cur) {
                var mini = '';
                if (cur.rows_fetched != null && cur.rows_source) {
                    var pct = Math.min(100, Math.floor(cur.rows_fetched / cur.rows_source * 100));
                    mini = '<span class="sync-mini"><span class="sync-track"><span class="sync-fill" style="display:block;width:' + pct + '%"></span></span></span>' +
                        '<span class="sync-num">' + num(cur.rows_fetched) + ' / ' + num(cur.rows_source) + ' baris</span>';
                }
                $('run-current').innerHTML = '<div class="sync-current"><span>Sedang menarik <code>' + esc(cur.table) + '</code></span>' +
                    (cur.since ? '<span class="sync-muted">sejak ' + esc(clock(cur.since)) + ' · <span class="sync-num" data-since="' + esc(cur.since) + '">' + esc(dur(since(cur.since))) + '</span></span>' : '') +
                    mini + '</div>';
            } else {
                $('run-current').innerHTML = '';
            }

            var notes = [];
            if (b.state === 'stalled') {
                notes.push(['is-warn', '<b>Tidak ada queue worker yang berjalan.</b> Sinkronisasi berhenti di tabel ke-' + (b.processed + 1) +
                    ' dari ' + b.total + ' dan akan lanjut sendiri begitu worker hidup lagi (nyalakan ulang container app). Data lama tetap dipakai sementara itu.']);
            }
            if (b.tracked === false && (isActive || (data.history || [])[0] && data.history[0].id === b.id)) {
                notes.push(['', 'Batch ini dimulai sebelum pencatatan per tabel aktif, jadi status per tabel di bawah <b>diperkirakan</b> (≈) dari waktu tabel terakhir diganti dan antrean worker. Jumlah baris per tabel baru tercatat mulai sinkronisasi berikutnya.']);
            }
            if (!isActive && b.failed > 0) {
                notes.push(['is-err', '<b>' + b.failed + ' tabel gagal.</b> Tabel itu tetap memakai data lama. Lihat alasannya di daftar tabel (saring "Gagal").']);
            }
            if (apiState && apiState.status === 'offline' && isActive) {
                notes.push(['is-err', '<b>API PD UNSRI sedang offline.</b> Tabel yang sedang ditarik kemungkinan gagal; data lamanya tetap dipakai.']);
            }
            var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>';
            $('run-notes').innerHTML = notes.map(function (n) { return '<div class="sync-note ' + n[0] + '">' + icon + '<span>' + n[1] + '</span></div>'; }).join('');
        }

        function rowsInfo(t) {
            if (t.status === 'running' && t.rows_fetched != null) {
                return '<b class="sync-num">' + num(t.rows_fetched) + '</b> baris masuk' + (t.rows_source ? ' dari ' + num(t.rows_source) : '');
            }
            if (t.rows_fetched != null) {
                return '<b class="sync-num">' + num(t.rows_fetched) + '</b> baris diterima' + (t.rows_source != null ? '<br>dari ' + num(t.rows_source) + ' di sumber' : '');
            }
            if (t.last_success_rows != null) {
                return '<b class="sync-num">' + num(t.last_success_rows) + '</b> baris<br>sinkronisasi terakhir';
            }
            if (!t.exists) return 'Tabel lokal belum ada';
            return '<b class="sync-num">≈ ' + num(t.estimate_rows) + '</b> baris<br>perkiraan di database';
        }

        function whenInfo(t) {
            if (t.status === 'running' && t.started_at) {
                return 'Mulai <b title="' + esc(abs(t.started_at)) + '">' + esc(clock(t.started_at)) + '</b>';
            }
            if (t.finished_at) return 'Selesai <b title="' + esc(abs(t.finished_at)) + '">' + esc(rel(t.finished_at)) + '</b>';
            if (t.last_success_at) return 'Diperbarui <b title="' + esc(abs(t.last_success_at)) + '">' + esc(rel(t.last_success_at)) + '</b>';
            if (t.table_updated_at) return 'Tabel diganti <b title="' + esc(abs(t.table_updated_at)) + '">' + esc(rel(t.table_updated_at)) + '</b>';
            return '—';
        }

        function renderTables() {
            var rows = data.tables || [];
            var counts = { semua: rows.length, done: 0, skipped: 0, failed: 0 };
            rows.forEach(function (t) { if (counts[t.status] != null) counts[t.status]++; });
            document.querySelectorAll('[data-count]').forEach(function (el) { el.textContent = counts[el.dataset.count]; });

            var shown = rows.filter(function (t) { return filter === 'semua' || t.status === filter; });
            if (!shown.length) {
                $('table-rows').innerHTML = '<li class="adm-empty">Tidak ada tabel dengan status ini.</li>';
            } else {
                $('table-rows').innerHTML = shown.map(function (t) {
                    var s = STATUS[t.status] || ['—', ''];
                    var est = t.status_source === 'perkiraan' && t.status ? ' <span class="sync-est" title="Diperkirakan — batch ini dimulai sebelum pencatatan per tabel">≈</span>' : '';
                    var codes = (t.used_by || []).map(function (c) { return '<span class="sync-code">' + esc(c) + '</span>'; }).join('');
                    var reason = t.message && ['skipped', 'failed', 'cancelled'].indexOf(t.status) !== -1
                        ? '<details class="sync-reason"><summary>Lihat alasan</summary><p>' + esc(t.message) + '</p></details>' : '';
                    var syncButton = DIRECT_SYNC_TABLES.has(t.table)
                        ? '<button type="button" class="sync-btn sync-btn-ghost sync-row-sync" data-sync-table="' + esc(t.table) + '" aria-label="Sinkronkan ' + esc(t.table) + '"' + (active() ? ' disabled' : '') + '>Sinkronkan tabel ini</button>'
                        : '';
                    return '<li class="sync-row ' + (t.status ? 'is-' + esc(t.status) : '') + '">' +
                        '<div class="sync-row-main"><span class="sync-tname">' + esc(t.table) + '</span>' +
                        (codes ? '<div class="sync-codes" aria-label="Dipakai tabel laporan">' + codes + '</div>' : '') + syncButton + '</div>' +
                        '<span class="sync-status ' + s[1] + '">' + esc(s[0]) + est + '</span>' +
                        '<div class="sync-rows-info">' + rowsInfo(t) + '</div>' +
                        '<div class="sync-when">' + whenInfo(t) + '</div>' + reason + '</li>';
                }).join('');
            }

            $('tables-note').textContent = filter === 'semua'
                ? 'Kode di bawah nama tabel = tabel laporan LKPS yang memakainya.'
                : shown.length + ' dari ' + rows.length + ' tabel.';
        }

        function renderHistory() {
            var h = data.history || [];
            if (!h.length) {
                $('history').innerHTML = '<li class="adm-empty">Belum ada riwayat.</li>';
                return;
            }
            $('history').innerHTML = h.map(function (b) {
                var st = b.state === 'running' && data.batch && data.batch.id === b.id ? data.batch.state : b.state;
                var l = batchLabel({ state: st, failed: b.failed });
                var d = st === 'running' || st === 'stalled' ? since(b.started_at) : b.duration_seconds;
                return '<li><span class="sync-status ' + l[1] + '">' + esc(l[0]) + '</span>' +
                    '<span class="sync-h-when">' + esc(abs(b.started_at)) + '</span>' +
                    '<span class="sync-num">' + esc(dur(d)) + '</span>' +
                    '<span class="sync-h-meta">' + esc(resultText(b, b.results)) + ' · oleh ' + esc(trigger(b.triggered_by)) + ' · batch ' + esc(b.short_id) + '</span></li>';
            }).join('');
        }

        function renderApi() {
            var dot = $('api-dot'), label = $('api-label'), sub = $('api-sub');
            var warn = $('dialog-api-warn');
            if (!apiState) {
                dot.className = 'sync-dot';
                label.textContent = 'Status belum tersedia';
                sub.textContent = 'Pemeriksaan status API tidak bisa dibaca saat ini.';
                warn.hidden = true;
                return;
            }
            var map = { online: 'is-ok', lambat: 'is-warn', offline: 'is-err', memeriksa: '' };
            dot.className = 'sync-dot ' + (map[apiState.status] || '');
            label.textContent = apiState.label || apiState.status;
            var bits = [];
            if (apiState.via) bits.push('Lewat ' + apiState.via);
            if (apiState.latency_ms != null) bits.push(num(apiState.latency_ms) + ' ms');
            if (apiState.checked_at) bits.push('dicek ' + rel(apiState.checked_at));
            sub.textContent = (apiState.detail ? apiState.detail + ' · ' : '') + bits.join(' · ');
            warn.hidden = apiState.status !== 'offline' && apiState.status !== 'lambat';
            $('dialog-api-warn-text').textContent = apiState.status === 'offline'
                ? 'API PD UNSRI sedang offline — sinkronisasi kemungkinan gagal. Data lama tetap aman.'
                : 'API PD UNSRI sedang lambat — sinkronisasi bisa lebih lama dari biasanya.';
        }

        function render() {
            if (data.server_time) skew = new Date(data.server_time).getTime() - Date.now();
            renderCards();
            renderRun();
            renderTables();
            renderHistory();
            $('updated-at').textContent = 'Diperbarui ' + clock(data.server_time) + (active() ? ' · otomatis tiap 5 detik' : ' · otomatis tiap menit');
        }

        function schedule() {
            clearTimeout(pollTimer);
            if (stopped || document.hidden) return;
            pollTimer = setTimeout(poll, active() ? 5000 : 60000);
        }

        function poll() {
            fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    if (r.status === 401 || r.status === 403 || r.status === 419) {
                        stopped = true;
                        throw new Error('sesi');
                    }
                    if (!r.ok) throw new Error('http ' + r.status);
                    return r.json();
                })
                .then(function (json) { data = json; render(); })
                .catch(function (e) {
                    $('updated-at').textContent = e.message === 'sesi'
                        ? 'Sesi berakhir — muat ulang halaman untuk melanjutkan.'
                        : 'Gagal memperbarui status, mencoba lagi…';
                })
                .then(schedule);
        }

        function pollApi() {
            fetch(apiUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (json) { apiState = json && json.status ? json : null; })
                .catch(function () { apiState = null; })
                .then(function () { renderApi(); renderRun(); if (!stopped) setTimeout(pollApi, 60000); });
        }

        // Detik berjalan diperbarui lokal tiap detik; data sendiri tetap dari polling.
        setInterval(function () {
            document.querySelectorAll('[data-since]').forEach(function (el) {
                if (!el.dataset.since) return;
                var t = dur(since(el.dataset.since));
                el.textContent = el.id === 'run-elapsed' ? 'berjalan ' + t : t;
            });
        }, 1000);

        document.querySelectorAll('#filter-chips [data-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                filter = btn.dataset.filter;
                document.querySelectorAll('#filter-chips [data-filter]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
                renderTables();
            });
        });

        $('table-rows').addEventListener('click', function (event) {
            var button = event.target.closest('[data-sync-table]');
            if (!button || button.disabled || active()) return;

            var select = $('sync-table');
            select.value = button.dataset.syncTable;
            if (select.value !== button.dataset.syncTable) return;

            $('dialog-start-title').textContent = 'Sinkronkan ' + button.dataset.syncTable + '?';
            $('dialog-start').querySelector('.adm-dialog-head p').textContent = 'Hanya tabel ini yang akan ditarik ulang dari PD UNSRI.';

            $('btn-start').click();
        });

        $('sync-table').addEventListener('change', function () {
            $('dialog-start-title').textContent = 'Sinkronkan tabel terpilih?';
            $('dialog-start').querySelector('.adm-dialog-head p').textContent = 'Hanya tabel yang dipilih akan ditarik ulang dari PD UNSRI.';
        });

        // Tombol form membuka dialog konfirmasi dulu; tanpa <dialog> (browser
        // lama) form langsung terkirim seperti biasa.
        document.querySelectorAll('[data-dialog]').forEach(function (btn) {
            var dialog = $(btn.dataset.dialog);
            if (!dialog || typeof dialog.showModal !== 'function') return;
            btn.addEventListener('click', function (e) { e.preventDefault(); dialog.showModal(); });
        });

        ['form-start', 'form-start-all', 'form-cancel'].forEach(function (id) {
            $(id).addEventListener('submit', function () {
                document.querySelectorAll('button[form="' + id + '"], #' + id + ' button').forEach(function (b) { b.disabled = true; });
            });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) clearTimeout(pollTimer);
            else if (!stopped) poll();
        });

        render();
        renderApi();
        pollApi();
        schedule();
    })();
    </script>
@endsection
