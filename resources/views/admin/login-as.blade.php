@extends('layouts.admin')

@section('title', 'Login sebagai')
@section('subtitle', 'Lihat SAPTO persis seperti yang dilihat korprodi, dekan, atau rektor. Kembali ke akun admin kapan saja; setiap penyamaran dicatat di log.')

@push('styles')
<style>
    .adm-hint-enter {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 11.5px;
        color: var(--color-muted);
    }

    kbd {
        padding: 0.05rem 0.4rem;
        border: 1px solid var(--input-border);
        border-bottom-width: 2px;
        border-radius: 0.4rem;
        font: inherit;
        font-size: 11px;
        font-weight: 700;
        color: var(--color-ink);
        background: var(--input-bg);
    }

    /* Satu kartu = satu tombol besar. Tidak ada lagi 98 tombol ungu yang
       sama persis berjejer; aksinya muncul sebagai isyarat saat disorot. */
    .adm-cards {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.6rem;
        padding: 0 1rem 1rem;
    }

    @media (min-width: 720px) {
        .adm-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (min-width: 1100px) {
        .adm-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    .adm-card { margin: 0; }

    .adm-card-btn {
        position: relative;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: start;
        gap: 0.75rem;
        width: 100%;
        height: 100%;
        padding: 0.85rem 0.9rem;
        border: 1px solid var(--input-border);
        border-radius: 1rem;
        font: inherit;
        text-align: left;
        color: inherit;
        background: var(--input-bg);
        cursor: pointer;
        transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease;
    }

    .adm-card-btn:hover,
    .adm-card-btn:focus-visible,
    .adm-card.is-first .adm-card-btn {
        outline: none;
        border-color: var(--color-accent);
        box-shadow: 0 12px 26px -18px rgba(67, 56, 202, 0.9), 0 0 0 3px var(--color-accent-soft);
        transform: translateY(-1px);
    }

    .adm-card-body { min-width: 0; }
    .adm-card-body .adm-scope-line { margin-top: 0.4rem; }
    .adm-card.is-warn .adm-scope-name { color: var(--color-warn); }

    .adm-card-go {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-top: 0.55rem;
        font-size: 12px;
        font-weight: 800;
        color: var(--color-accent);
        opacity: 0.55;
        transition: opacity 120ms ease;
    }

    .adm-card-go svg { width: 14px; height: 14px; transition: transform 120ms ease; }
    .adm-card-btn:hover .adm-card-go,
    .adm-card-btn:focus-visible .adm-card-go,
    .adm-card.is-first .adm-card-go { opacity: 1; }
    .adm-card-btn:hover .adm-card-go svg { transform: translateX(3px); }

    .adm-card-enter { display: none; margin-left: auto; }
    .adm-card.is-first .adm-card-enter { display: inline-flex; }

    .adm-test {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1.25rem;
        margin-top: 1rem;
        padding: 1rem 1.1rem;
    }

    .adm-test strong { display: block; font-size: 13.5px; font-weight: 800; color: var(--color-ink); }
    .adm-test p { margin: 0.15rem 0 0; font-size: 12px; color: var(--color-muted); }
    .adm-test form { display: flex; gap: 0.5rem; margin: 0; flex: 0 1 22rem; }

    .adm-btn-ghost {
        flex-shrink: 0;
        padding: 0 1rem;
        border: 1px solid var(--input-border);
        border-radius: 0.8rem;
        font: inherit;
        font-size: 12.5px;
        font-weight: 800;
        color: var(--color-ink);
        background: var(--input-bg);
        cursor: pointer;
    }

    .adm-btn-ghost:hover { border-color: var(--color-accent-line); color: var(--color-accent); }
</style>
@endpush

@section('content')
    <section class="glass-strong adm-panel" data-filter="orang" id="daftar-orang" aria-label="Pilih akun">
        <div class="adm-toolbar">
            <div class="adm-search" style="max-width:34rem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" class="adm-input" data-filter-search id="cari-orang" placeholder="Cari nama, NIP, email, prodi, fakultas…" aria-label="Cari akun" autocomplete="off" autofocus>
            </div>
            <span class="adm-hint-enter"><kbd>Enter</kbd> masuk sebagai hasil teratas</span>
        </div>

        <div class="adm-toolbar" style="padding-top:0">
            <div class="adm-chips" role="group" aria-label="Saring menurut peran">
                <button type="button" class="adm-chip" data-chip="semua" aria-pressed="true">Semua <b>{{ $orang->count() }}</b></button>
                <button type="button" class="adm-chip" data-chip="rektor" aria-pressed="false">Rektor <b>{{ $jumlah->get('rektor', 0) }}</b></button>
                <button type="button" class="adm-chip" data-chip="dekan" aria-pressed="false">Dekan <b>{{ $jumlah->get('dekan', 0) }}</b></button>
                <button type="button" class="adm-chip" data-chip="korprodi" aria-pressed="false">Korprodi <b>{{ $jumlah->get('korprodi', 0) }}</b></button>
                @if ($jumlah->get('bermasalah', 0) > 0)
                    <button type="button" class="adm-chip adm-chip-warn" data-chip="bermasalah" aria-pressed="false">Bermasalah <b>{{ $jumlah->get('bermasalah') }}</b></button>
                @endif
            </div>
            <div class="adm-count" data-filter-count aria-live="polite" style="padding:0"></div>
        </div>

        <div class="adm-cards">
            @foreach ($orang as $o)
                <form method="POST" action="{{ route('admin.login-as.store') }}" @class(['adm-card', 'is-warn' => $o['bermasalah']])
                      data-item data-tags="{{ $o['tags'] }}" data-search="{{ $o['search'] }}">
                    @csrf
                    <input type="hidden" name="penugasan_id" value="{{ $o['penugasan_id'] }}">
                    <button type="submit" class="adm-card-btn">
                        <span class="adm-avatar adm-av-{{ $o['peran'] }}" aria-hidden="true">{{ $o['inisial'] }}</span>
                        <span class="adm-card-body">
                            <span class="adm-name" style="display:block">{{ $o['nama'] }}</span>
                            <span class="adm-meta" style="display:block">{{ $o['identitas'] }}</span>
                            <span class="adm-scope-line">
                                <span class="adm-badge adm-badge-{{ $o['peran'] }}">{{ \App\Models\Penugasan::LABEL_PERAN[$o['peran']] ?? $o['peran'] }}</span>
                                <span class="adm-scope-name">{{ $o['lingkup'] }}</span>
                            </span>
                            <span class="adm-scope-line">
                                <span class="adm-card-go">
                                    Masuk sebagai
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13m-5-6 6 6-6 6"/></svg>
                                </span>
                                <kbd class="adm-card-enter">Enter</kbd>
                            </span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>

        <div class="adm-empty" data-filter-empty hidden>
            <strong>Tidak ada akun yang cocok</strong>
            Akun yang belum terdaftar bisa ditambahkan di <a href="{{ route('admin.peran') }}" style="color:var(--color-accent);font-weight:700">Kelola peran</a>.
        </div>
    </section>

    <section class="glass-strong adm-panel adm-test" aria-label="Uji akun tanpa akses">
        <div>
            <strong>Uji akun tanpa akses</strong>
            <p>Masuk sebagai NIP yang tidak terdaftar untuk melihat apa yang dialami orang yang belum didaftarkan.</p>
        </div>
        <form method="POST" action="{{ route('admin.login-as.store') }}">
            @csrf
            <input type="hidden" name="any" value="1">
            <input type="text" name="nip" class="adm-input" value="0000000000" aria-label="NIP uji" inputmode="numeric">
            <button type="submit" class="adm-btn-ghost">Uji</button>
        </form>
    </section>
@endsection

@push('scripts')
<script>
    (function () {
        // Enter di kotak cari = masuk sebagai hasil teratas yang terlihat.
        var cari = document.getElementById('cari-orang');
        var daftar = document.getElementById('daftar-orang');
        cari.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || !cari.value.trim() || !daftar.firstVisible) return;
            e.preventDefault();
            daftar.firstVisible.requestSubmit();
        });
    })();
</script>
@endpush
