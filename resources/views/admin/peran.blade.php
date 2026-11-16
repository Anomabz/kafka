@extends('layouts.admin')

@section('title', 'Kelola peran')
@section('subtitle', 'Tentukan siapa yang boleh masuk SAPTO dan sebatas apa. Akun SSO yang tidak terdaftar di sini ditolak saat login.')

@push('styles')
<style>
    .adm-toolbar-end {
        display: flex;
        flex: 1 1 22rem;
        justify-content: flex-end;
        gap: 0.6rem;
        max-width: 34rem;
    }

    .adm-add {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        gap: 0.45rem;
        padding: 0 1rem;
        border: 0;
        border-radius: 0.8rem;
        font: inherit;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
        color: #ffffff;
        background: var(--brand);
        box-shadow: none;
        cursor: pointer;
        transition: transform 120ms ease, filter 120ms ease;
    }

    .adm-add:hover { filter: brightness(1.06); }
    .adm-add svg { width: 16px; height: 16px; }

    /* --- Baris daftar ------------------------------------------------- */
    .adm-rows { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--color-line); }

    .adm-row {
        display: grid;
        grid-template-areas: "av main act" ". scope scope";
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.35rem 0.85rem;
        padding: 0.7rem 1rem;
        border-bottom: 1px solid var(--color-line);
        transition: background 100ms ease;
    }

    @media (min-width: 720px) {
        .adm-row {
            grid-template-areas: "av main scope act";
            grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr) auto;
        }
    }

    .adm-row:last-child { border-bottom: 0; }
    .adm-row:hover { background: var(--row-hover); }
    .adm-row.is-warn { box-shadow: inset 3px 0 0 var(--color-warn); }
    .adm-row.is-warn .adm-scope-sub { font-weight: 700; color: var(--color-warn); }

    .adm-row .adm-avatar { grid-area: av; }
    .adm-row-main { grid-area: main; min-width: 0; }
    .adm-row-scope { grid-area: scope; min-width: 0; }
    .adm-row-actions { grid-area: act; display: flex; align-items: center; gap: 0.35rem; }
    .adm-row-actions form { margin: 0; }

    .adm-icon-btn,
    .adm-revoke {
        height: 34px;
        border: 1px solid var(--input-border);
        border-radius: 0.7rem;
        font: inherit;
        color: var(--color-muted);
        background: var(--input-bg);
        cursor: pointer;
        transition: color 120ms ease, background 120ms ease, border-color 120ms ease;
    }

    .adm-icon-btn { display: grid; place-items: center; width: 34px; padding: 0; }
    .adm-icon-btn svg { width: 16px; height: 16px; }

    .adm-icon-btn:hover {
        color: var(--color-accent);
        border-color: var(--color-accent-line);
        background: var(--color-accent-soft);
    }

    .adm-revoke { padding: 0 0.75rem; font-size: 12px; font-weight: 700; white-space: nowrap; }

    .adm-revoke:hover {
        color: var(--color-warn);
        border-color: var(--color-warn-line);
        background: var(--color-warn-soft);
    }

    .adm-revoke.is-armed { color: #ffffff; border-color: transparent; background: #b91c1c; }

    /* --- Popup "Tambah akses" -----------------------------------------
       <dialog> native: fokus terkunci di dalam, Esc menutup, latar
       gelap lewat ::backdrop -- tanpa library. Kepala dan kaki tetap,
       hanya isi yang menggulir, jadi tombol simpan selalu terlihat
       berapa pun tinggi layarnya. */
    .adm-dialog {
        /* margin:auto yang memusatkan <dialog> modal dihapus oleh reset
           Tailwind (margin:0 untuk semua elemen) -- dipasang ulang di sini. */
        margin: auto;
        width: min(36rem, calc(100vw - 2rem));
        max-width: none;
        max-height: min(46rem, calc(100vh - 2rem));
        max-height: min(46rem, calc(100dvh - 2rem));
        padding: 0;
        border: 0;
        border-radius: 1.25rem;
        overflow: hidden;
        color: var(--color-ink);
        background: var(--color-surface);
        box-shadow: 0 0 0 1px var(--color-line);
    }

    .adm-dialog[open] {
        display: flex;
        flex-direction: column;
        animation: adm-pop 180ms cubic-bezier(0.16, 1, 0.3, 1);
    }

    .adm-dialog::backdrop {
        background: rgba(8, 12, 28, 0.55);
    }

    @keyframes adm-pop {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: none; }
    }

    @keyframes adm-sheet {
        from { transform: translateY(100%); }
        to { transform: none; }
    }

    /* Layar sempit: muncul dari bawah seperti bottom sheet. */
    @media (max-width: 640px) {
        .adm-dialog {
            width: 100vw;
            max-height: 92vh;
            max-height: 92dvh;
            margin: auto 0 0;
            border-radius: 1.25rem 1.25rem 0 0;
        }

        .adm-dialog[open] { animation-name: adm-sheet; }
    }

    @media (prefers-reduced-motion: reduce) {
        .adm-dialog[open] { animation: none; }
    }

    .adm-dialog form { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; margin: 0; }

    .adm-dialog-head {
        position: relative;
        display: flex;
        flex: none;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.2rem 1.35rem 1rem;
        border-bottom: 1px solid var(--color-line);
    }

    /* Garis aksen senada kartu login dan header admin. */
    .adm-dialog-head::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--brand);
    }

    .adm-dialog-head h2 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
    .adm-dialog-head p { margin: 0.2rem 0 0; font-size: 12.5px; color: var(--color-muted); }

    .adm-close {
        display: grid;
        flex-shrink: 0;
        place-items: center;
        width: 34px;
        height: 34px;
        border: 1px solid var(--input-border);
        border-radius: 999px;
        color: var(--color-muted);
        background: var(--input-bg);
        cursor: pointer;
    }

    .adm-close:hover { color: var(--color-ink); }
    .adm-close svg { width: 16px; height: 16px; }

    .adm-dialog-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 0.25rem 1.35rem 1.35rem; }

    .adm-dialog-foot {
        display: flex;
        flex: none;
        justify-content: flex-end;
        gap: 0.6rem;
        padding: 0.9rem 1.35rem;
        border-top: 1px solid var(--color-line);
        background: var(--color-surface);
    }

    .adm-btn {
        padding: 0.75rem 1.2rem;
        border-radius: 0.8rem;
        font: inherit;
        font-size: 13.5px;
        font-weight: 800;
        cursor: pointer;
        transition: transform 120ms ease, filter 120ms ease, border-color 120ms ease;
    }

    .adm-btn-ghost { border: 1px solid var(--input-border); color: var(--color-ink); background: var(--input-bg); }
    .adm-btn-ghost:hover { border-color: var(--color-accent-line); }

    .adm-btn-primary {
        border: 0;
        color: #ffffff;
        background: var(--brand);
        box-shadow: none;
    }

    .adm-btn-primary:hover { filter: brightness(1.06); }

    @media (max-width: 640px) {
        .adm-dialog-foot .adm-btn { flex: 1; }
    }

    /* Langkah bernomor: urutan pengisian terlihat tanpa perlu dibaca. */
    .adm-step {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 1.25rem 0 0.6rem;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--color-ink);
    }

    .adm-step i {
        display: grid;
        place-items: center;
        width: 22px;
        height: 22px;
        border-radius: 999px;
        font-size: 11px;
        font-style: normal;
        color: #ffffff;
        background: var(--color-accent);
    }

    .adm-step small { font-size: 11px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--color-muted); }

    .adm-label { display: block; margin: 0 0 0.35rem; font-size: 12px; font-weight: 700; color: var(--color-muted); }
    .adm-hint { margin: 0.45rem 0 0; font-size: 11.5px; line-height: 1.45; color: var(--color-muted); }

    .adm-fields { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }

    @media (min-width: 520px) {
        .adm-fields-2 { grid-template-columns: 1fr 1fr; }
    }

    .adm-roles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin: 0;
        padding: 0;
        border: 0;
    }

    .adm-role {
        position: relative;
        display: block;
        padding: 0.75rem 0.5rem;
        border: 1px solid var(--input-border);
        border-radius: 0.9rem;
        text-align: center;
        background: var(--input-bg);
        cursor: pointer;
        transition: border-color 120ms ease, background 120ms ease, box-shadow 120ms ease;
    }

    .adm-role input { position: absolute; opacity: 0; pointer-events: none; }
    .adm-role strong { display: block; font-size: 13px; font-weight: 800; color: var(--color-ink); }
    .adm-role span { display: block; margin-top: 0.15rem; font-size: 11px; line-height: 1.3; color: var(--color-muted); }

    .adm-role:has(input:checked) {
        border-color: var(--color-accent);
        background: var(--color-accent-soft);
        box-shadow: 0 0 0 3px var(--color-accent-soft);
    }

    .adm-role:has(input:focus-visible) { outline: 2px solid var(--color-accent); outline-offset: 2px; }

    .adm-chosen {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        padding: 0.55rem 0.75rem;
        border-radius: 0.8rem;
        font-size: 12.5px;
        color: var(--color-accent);
        background: var(--color-accent-soft);
        box-shadow: inset 0 0 0 1px var(--color-accent-line);
    }

    .adm-chosen svg { flex-shrink: 0; width: 15px; height: 15px; }
    .adm-chosen strong { font-weight: 800; }

    .adm-pick {
        max-height: 14rem;
        margin-top: 0.5rem;
        overflow-y: auto;
        border: 1px solid var(--input-border);
        border-radius: 0.85rem;
        background: var(--input-bg);
    }

    .adm-pick-group {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 0.35rem 0.75rem;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-muted);
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-line);
    }

    .adm-pick-item {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--color-line);
        cursor: pointer;
    }

    .adm-pick-item:last-child { border-bottom: 0; }
    .adm-pick-item:hover { background: var(--row-hover); }
    .adm-pick-item:has(input:checked) { background: var(--color-accent-soft); }
    .adm-pick-item input { flex-shrink: 0; accent-color: var(--color-accent); }
    .adm-pick-item span { min-width: 0; font-size: 13px; color: var(--color-ink); }

    .adm-pick-item small {
        margin-left: auto;
        padding-left: 0.5rem;
        font-size: 11px;
        color: var(--color-muted);
        font-variant-numeric: tabular-nums;
    }

    .adm-note {
        display: flex;
        gap: 0.6rem;
        padding: 0.85rem 0.95rem;
        border: 1px solid var(--input-border);
        border-radius: 0.9rem;
        font-size: 12.5px;
        line-height: 1.45;
        color: var(--color-muted);
        background: var(--input-bg);
    }

    .adm-note svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; color: var(--color-accent); }
</style>
@endpush

@section('content')
    @if (session('status'))
        <div class="adm-toast adm-toast-ok" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <section class="glass-strong adm-panel" data-filter="orang" aria-label="Daftar akses">
        <div class="adm-toolbar">
            <div class="adm-chips" role="group" aria-label="Saring menurut peran">
                <button type="button" class="adm-chip" data-chip="semua" aria-pressed="true">Semua <b>{{ $penugasans->count() }}</b></button>
                <button type="button" class="adm-chip" data-chip="rektor" aria-pressed="false">Rektor <b>{{ $jumlah->get('rektor', 0) }}</b></button>
                <button type="button" class="adm-chip" data-chip="dekan" aria-pressed="false">Dekan <b>{{ $jumlah->get('dekan', 0) }}</b></button>
                <button type="button" class="adm-chip" data-chip="korprodi" aria-pressed="false">Korprodi <b>{{ $jumlah->get('korprodi', 0) }}</b></button>
                @if ($jumlah->get('bermasalah', 0) > 0)
                    <button type="button" class="adm-chip adm-chip-warn" data-chip="bermasalah" aria-pressed="false"
                            title="Lingkupnya tidak ada di data referensi — orangnya bisa login tapi mentok di setiap tabel">
                        Bermasalah <b>{{ $jumlah->get('bermasalah') }}</b>
                    </button>
                @endif
            </div>
            <div class="adm-toolbar-end">
                <div class="adm-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" class="adm-input" data-filter-search placeholder="Cari nama, NIP, email, prodi…" aria-label="Cari di daftar akses" autocomplete="off">
                </div>
                <button type="button" class="adm-add" id="buka-tambah" aria-haspopup="dialog" aria-controls="dialog-tambah">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah akses
                </button>
            </div>
        </div>

        <div class="adm-count" data-filter-count aria-live="polite"></div>

        <ul class="adm-rows">
            @foreach ($penugasans as $p)
                <li @class(['adm-row', 'is-warn' => ! $p['lingkup']['ada']]) data-item data-tags="{{ $p['tags'] }}" data-search="{{ $p['search'] }}">
                    <span class="adm-avatar adm-av-{{ $p['peran'] }}" aria-hidden="true">{{ $p['inisial'] }}</span>

                    <div class="adm-row-main">
                        <div class="adm-name">{{ $p['nama'] }}</div>
                        <div class="adm-meta">{{ collect([$p['nip'] ? 'NIP '.$p['nip'] : null, $p['email']])->filter()->implode(' · ') }}</div>
                    </div>

                    <div class="adm-row-scope">
                        <div class="adm-scope-line">
                            <span class="adm-badge adm-badge-{{ $p['peran'] }}">{{ \App\Models\Penugasan::LABEL_PERAN[$p['peran']] ?? $p['peran'] }}</span>
                            <span class="adm-scope-name">{{ $p['lingkup']['nama'] }}</span>
                        </div>
                        @if ($p['lingkup']['sub'])
                            <div class="adm-scope-sub">{{ $p['lingkup']['sub'] }}</div>
                        @endif
                    </div>

                    <div class="adm-row-actions">
                        <form method="POST" action="{{ route('admin.login-as.store') }}">
                            @csrf
                            <input type="hidden" name="penugasan_id" value="{{ $p['id'] }}">
                            <button type="submit" class="adm-icon-btn" title="Lihat SAPTO sebagai {{ $p['nama'] }}" aria-label="Lihat SAPTO sebagai {{ $p['nama'] }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.peran.destroy', $p['id']) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="adm-revoke" data-arm="Yakin cabut?" aria-label="Cabut akses {{ $p['label'] }} untuk {{ $p['nama'] }}">Cabut</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="adm-empty" data-filter-empty hidden>
            <strong>Tidak ada yang cocok</strong>
            Coba kata kunci lain, pilih "Semua", atau daftarkan orangnya lewat tombol <b>Tambah akses</b>.
        </div>
    </section>

    @php($peranLama = old('peran', 'korprodi'))

    <dialog class="adm-dialog" id="dialog-tambah" aria-labelledby="dialog-tambah-judul" data-buka="{{ $errors->any() ? '1' : '0' }}">
        <form method="POST" action="{{ route('admin.peran.store') }}" id="form-tambah">
            @csrf

            <div class="adm-dialog-head">
                <div>
                    <h2 id="dialog-tambah-judul">Tambah akses</h2>
                    <p>Orang ini bisa masuk SAPTO dengan akun SSO-nya begitu disimpan.</p>
                </div>
                <button type="button" class="adm-close" data-tutup aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="adm-dialog-body">
                @if ($errors->any())
                    <div class="adm-toast adm-toast-err" role="alert" style="margin:1rem 0 0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="adm-step"><i>1</i> Peran</div>
                <fieldset class="adm-roles">
                    <legend class="adm-sr">Peran</legend>
                    <label class="adm-role">
                        <input type="radio" name="peran" value="korprodi" @checked($peranLama === 'korprodi')>
                        <strong>Korprodi</strong>
                        <span>Satu program studi</span>
                    </label>
                    <label class="adm-role">
                        <input type="radio" name="peran" value="dekan" @checked($peranLama === 'dekan')>
                        <strong>Dekan</strong>
                        <span>Satu fakultas</span>
                    </label>
                    <label class="adm-role">
                        <input type="radio" name="peran" value="rektor" @checked($peranLama === 'rektor')>
                        <strong>Rektor</strong>
                        <span>Seluruh universitas</span>
                    </label>
                </fieldset>

                <div class="adm-step"><i>2</i> Identitas <small>— isi minimal salah satu</small></div>
                <div class="adm-fields adm-fields-2">
                    <div>
                        <label for="nip" class="adm-label">NIP / NIM</label>
                        <input id="nip" name="nip" type="text" inputmode="numeric" class="adm-input" value="{{ old('nip') }}" placeholder="197801212008121003" autocomplete="off" autofocus>
                    </div>
                    <div>
                        <label for="email" class="adm-label">Email SSO</label>
                        <input id="email" name="email" type="email" class="adm-input" value="{{ old('email') }}" placeholder="nama@unsri.ac.id" autocomplete="off">
                    </div>
                    <div style="grid-column:1/-1">
                        <label for="nama" class="adm-label">Nama <span style="font-weight:500">(opsional)</span></label>
                        <input id="nama" name="nama" type="text" class="adm-input" value="{{ old('nama') }}" placeholder="Dr. Nama Lengkap, M.Kom." autocomplete="off">
                    </div>
                </div>
                <p class="adm-hint">Isi NIP dan email kalau tahu keduanya: cukup salah satu yang cocok, dan Keycloak kadang tidak mengirim NIP.</p>

                <div class="adm-step"><i>3</i> <span data-judul-lingkup>Lingkup</span></div>

                <div data-scope="korprodi">
                    <div class="adm-chosen" id="prodi-terpilih" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Terpilih: <strong></strong></span>
                    </div>
                    <div class="adm-search" style="max-width:none">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input id="cari-prodi" type="search" class="adm-input" placeholder="Cari program studi, jenjang, atau kode…" aria-label="Cari program studi" autocomplete="off">
                    </div>
                    <div class="adm-pick" role="radiogroup" aria-label="Program studi" id="daftar-prodi">
                        @foreach ($prodis->groupBy('fakultas') as $namaFakultas => $isi)
                            <div data-pick-group>
                                <div class="adm-pick-group">{{ $namaFakultas }}</div>
                                @foreach ($isi as $prodi)
                                    <label class="adm-pick-item" data-pick data-search="{{ mb_strtolower($prodi['label'].' '.$prodi['kode'].' '.$namaFakultas) }}">
                                        <input type="radio" name="lingkup_prodi" value="{{ $prodi['kode'] }}" data-nama="{{ $prodi['label'] }} ({{ $prodi['kode'] }})" @checked(old('lingkup_prodi') === $prodi['kode'])>
                                        <span>{{ $prodi['label'] }}</span>
                                        <small>{{ $prodi['kode'] }}</small>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                        <p class="adm-empty" id="prodi-kosong" hidden>Tidak ada program studi yang cocok.</p>
                    </div>
                </div>

                <div data-scope="dekan">
                    <div class="adm-pick" role="radiogroup" aria-label="Fakultas" style="margin-top:0">
                        @foreach ($fakultas as $f)
                            <label class="adm-pick-item">
                                <input type="radio" name="lingkup_fakultas" value="{{ $f['id'] }}" @checked(old('lingkup_fakultas') === $f['id'])>
                                <span>{{ $f['nama'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="adm-note" data-scope="rektor">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                    Rektor melihat seluruh fakultas dan program studi, jadi tidak perlu memilih lingkup.
                </div>
            </div>

            <div class="adm-dialog-foot">
                <button type="button" class="adm-btn adm-btn-ghost" data-tutup>Batal</button>
                <button type="submit" class="adm-btn adm-btn-primary">Tambah akses</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var dialog = document.getElementById('dialog-tambah');
        var form = document.getElementById('form-tambah');

        document.getElementById('buka-tambah').addEventListener('click', function () {
            dialog.showModal();
        });

        // Tutup lewat ✕/Batal atau klik di luar popup. Isian sengaja tidak
        // dihapus, jadi popup yang tertutup tidak sengaja bisa dibuka lagi
        // tanpa mengetik ulang.
        dialog.querySelectorAll('[data-tutup]').forEach(function (btn) {
            btn.addEventListener('click', function () { dialog.close(); });
        });
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) dialog.close();
        });

        // Validasi server gagal -> popup langsung terbuka lagi dengan isian lama.
        if (dialog.dataset.buka === '1') dialog.showModal();

        // Tampilkan pemilih lingkup sesuai peran.
        var judulLingkup = form.querySelector('[data-judul-lingkup]');
        var judul = { korprodi: 'Program studi', dekan: 'Fakultas', rektor: 'Lingkup' };
        function sinkronLingkup() {
            var peran = (form.querySelector('input[name="peran"]:checked') || {}).value;
            form.querySelectorAll('[data-scope]').forEach(function (el) {
                el.hidden = el.dataset.scope !== peran;
            });
            judulLingkup.textContent = judul[peran] || 'Lingkup';
        }
        form.querySelectorAll('input[name="peran"]').forEach(function (r) { r.addEventListener('change', sinkronLingkup); });
        sinkronLingkup();

        // Ringkasan prodi terpilih -- tetap terlihat walau daftarnya disaring.
        var terpilih = document.getElementById('prodi-terpilih');
        function tampilkanTerpilih() {
            var r = form.querySelector('input[name="lingkup_prodi"]:checked');
            terpilih.hidden = !r;
            if (r) terpilih.querySelector('strong').textContent = r.dataset.nama;
        }
        form.querySelectorAll('input[name="lingkup_prodi"]').forEach(function (r) { r.addEventListener('change', tampilkanTerpilih); });
        tampilkanTerpilih();

        // Saring daftar prodi; grup fakultas yang kosong ikut disembunyikan.
        var cari = document.getElementById('cari-prodi');
        var kosong = document.getElementById('prodi-kosong');
        var grup = Array.prototype.slice.call(document.querySelectorAll('[data-pick-group]'));
        cari.addEventListener('input', function () {
            var q = cari.value.trim().toLowerCase();
            var total = 0;
            grup.forEach(function (g) {
                var ada = 0;
                g.querySelectorAll('[data-pick]').forEach(function (item) {
                    var cocok = !q || item.dataset.search.indexOf(q) !== -1;
                    item.hidden = !cocok;
                    if (cocok) ada++;
                });
                g.hidden = ada === 0;
                total += ada;
            });
            kosong.hidden = total > 0;
        });

        // Enter di kotak cari prodi jangan mengirim form yang belum lengkap.
        cari.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
    })();
</script>
@endpush
