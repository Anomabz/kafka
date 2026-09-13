{{--
    Badge status jalur data PD UNSRI (lewat Gravitee), terlihat semua peran.
    Data: GET /api/sapto/pd-unsri/status (PdUnsriHealthCheck). $variant:
    'topbar' (topbar aplikasi, ikut tema) atau 'hero' (header gelap halaman
    admin). CSS & JS di sini sengaja mandiri, bukan kelas Tailwind baru --
    CSS aplikasi sudah ter-build di public/build.
--}}
@php($variant = $variant ?? 'topbar')
<div class="apib apib--{{ $variant }}" data-api-badge data-status="memeriksa"
     data-endpoint="{{ url('/api/sapto/pd-unsri/status') }}"
     data-refresh-endpoint="{{ url('/api/sapto/pd-unsri/refresh') }}">
    {{-- Cukup ikon awan: warnanya = status, garis miring = offline, titik
         berdenyut = online. Teks status ada di aria-label/title dan popover. --}}
    <button type="button" class="apib-btn{{ $variant === 'topbar' ? ' theme-toggle' : '' }}" aria-expanded="false"
            aria-controls="apib-pop-{{ $variant }}" aria-label="Status API PD UNSRI: memeriksa" title="API PD UNSRI: Memeriksa…"
            data-api-badge-toggle>
        <svg class="apib-cloud" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
            <path class="apib-slash" d="M3 3l18 18"/>
        </svg>
        <span class="apib-dot" aria-hidden="true"></span>
    </button>

    <div class="apib-pop" id="apib-pop-{{ $variant }}" aria-labelledby="apib-pop-{{ $variant }}-title" hidden data-api-badge-pop>
        <div class="apib-pop-head">
            <span class="apib-dot" aria-hidden="true"></span>
            <strong id="apib-pop-{{ $variant }}-title"><span data-f="label">Memeriksa…</span> · API PD UNSRI</strong>
        </div>
        <p class="apib-pop-detail" data-f="detail">Sedang memeriksa koneksi ke API Pangkalan Data UNSRI.</p>
        <dl class="apib-meta">
            <div><dt>Jalur</dt><dd data-f="via">Lewat Gravitee</dd></div>
            <div><dt>Waktu respons</dt><dd data-f="latency">—</dd></div>
            <div><dt>Dicek</dt><dd data-f="checked">—</dd></div>
            <div><dt>Data SAPTO</dt><dd data-f="sync">—</dd></div>
        </dl>

        {{-- Hanya muncul untuk peran yang punya lingkup laporan (korprodi,
             dekan, rektor, admin). Menghitung ulang angka laporan lingkup
             sendiri dari data lokal -- tidak menarik dari PD UNSRI. --}}
        <div class="apib-refresh" hidden data-apib-refresh>
            <div class="apib-refresh-scope">Laporan <b data-f="scope">—</b></div>
            <button type="button" class="apib-refresh-btn" data-apib-refresh-btn>
                <svg class="apib-refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12a9 9 0 0 1 15.5-6.2M21 12a9 9 0 0 1-15.5 6.2"/><path d="M18 3v4h-4M6 21v-4h4"/>
                </svg>
                <span data-f="refresh-label">Segarkan laporan</span>
            </button>
            <p class="apib-refresh-note" data-f="refresh-note">Hitung ulang angka laporan dari data SAPTO terbaru — tidak menarik data dari PD UNSRI.</p>
        </div>
    </div>

    <span class="apib-sr" aria-live="polite" data-api-badge-live></span>
</div>

@once
<style>
    :root {
        --apib-ok: #15803d;
        --apib-ok-dot: #16a34a;
        --apib-ok-soft: rgba(22, 163, 74, 0.14);
        --apib-bad: #b91c1c;
        --apib-bad-dot: #dc2626;
        --apib-bad-soft: rgba(220, 38, 38, 0.12);
    }

    :root[data-theme='dark'] {
        --apib-ok: #4ade80;
        --apib-ok-dot: #4ade80;
        --apib-ok-soft: rgba(74, 222, 128, 0.14);
        --apib-bad: #f87171;
        --apib-bad-dot: #f87171;
        --apib-bad-soft: rgba(248, 113, 113, 0.14);
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme]) {
            --apib-ok: #4ade80;
            --apib-ok-dot: #4ade80;
            --apib-ok-soft: rgba(74, 222, 128, 0.14);
            --apib-bad: #f87171;
            --apib-bad-dot: #f87171;
            --apib-bad-soft: rgba(248, 113, 113, 0.14);
        }
    }

    .apib {
        position: relative;
        flex-shrink: 0;
        --apib-tone: var(--color-muted);
        --apib-dot-c: var(--color-muted);
        --apib-soft: var(--color-slate-soft);
    }

    .apib[data-status='online'] { --apib-tone: var(--apib-ok); --apib-dot-c: var(--apib-ok-dot); --apib-soft: var(--apib-ok-soft); }
    .apib[data-status='lambat'],
    .apib[data-status='gangguan'] { --apib-tone: var(--color-warn); --apib-dot-c: var(--color-warn); --apib-soft: var(--color-warn-soft); }
    .apib[data-status='offline'] { --apib-tone: var(--apib-bad); --apib-dot-c: var(--apib-bad-dot); --apib-soft: var(--apib-bad-soft); }

    /* Di topbar bentuk tombolnya dari .theme-toggle (sama dengan tombol
       layar penuh & tema di sebelahnya); di sini cuma yang khusus badge. */
    .apib .apib-btn { position: relative; }
    .apib .apib-btn[aria-expanded='true'] { border-color: var(--apib-tone); }
    .apib .apib-btn:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }

    .apib .apib-cloud {
        width: 18px;
        height: 18px;
        color: var(--apib-tone);
        transition: color 160ms ease;
    }

    .apib .apib-slash { display: none; }
    .apib[data-status='offline'] .apib-slash { display: inline; }

    .apib-dot {
        position: relative;
        display: inline-block;
        flex-shrink: 0;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--apib-dot-c);
        box-shadow: 0 0 0 3px var(--apib-soft);
    }

    /* Titik status menempel di pojok ikon, bukan di samping teks. */
    .apib .apib-btn .apib-dot {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 7px;
        height: 7px;
        box-shadow: 0 0 0 2px var(--color-surface);
    }

    .apib[data-status='online'] .apib-btn .apib-dot::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: var(--apib-dot-c);
        animation: apib-pulse 2.2s ease-out infinite;
    }

    .apib[data-status='memeriksa'] .apib-btn .apib-dot { animation: apib-blink 1.2s ease-in-out infinite; }

    @keyframes apib-pulse {
        0% { transform: scale(1); opacity: 0.55; }
        70%, 100% { transform: scale(2.6); opacity: 0; }
    }

    @keyframes apib-blink { 50% { opacity: 0.35; } }

    @media (prefers-reduced-motion: reduce) {
        .apib .apib-dot,
        .apib .apib-dot::after { animation: none !important; }
    }

    /* Header admin selalu gelap (gradien), jadi warna pill dikunci terang
       di sana apa pun temanya -- disamakan dengan tombol .adm-back. */
    .apib--hero .apib-btn {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
        cursor: pointer;
        transition: background 120ms ease;
    }

    .apib--hero .apib-btn:hover,
    .apib--hero .apib-btn[aria-expanded='true'] { background: rgba(255, 255, 255, 0.18); }
    .apib--hero .apib-btn .apib-dot { top: 4px; right: 4px; box-shadow: 0 0 0 2px #182015; }
    .apib--hero { --apib-tone: rgba(248, 250, 252, 0.7); --apib-dot-c: rgba(248, 250, 252, 0.6); --apib-soft: rgba(255, 255, 255, 0.12); }
    .apib--hero[data-status='online'] { --apib-tone: #86efac; --apib-dot-c: #4ade80; --apib-soft: rgba(74, 222, 128, 0.22); }
    .apib--hero[data-status='lambat'],
    .apib--hero[data-status='gangguan'] { --apib-tone: #fcd34d; --apib-dot-c: #fbbf24; --apib-soft: rgba(251, 191, 36, 0.22); }
    .apib--hero[data-status='offline'] { --apib-tone: #fca5a5; --apib-dot-c: #f87171; --apib-soft: rgba(248, 113, 113, 0.22); }

    /* Popover dipindah ke <body> oleh script (posisi fixed): topbar punya
       backdrop-filter dan header admin overflow:hidden, dua-duanya akan
       memotong popover kalau tetap di dalamnya. */
    .apib-pop {
        position: fixed;
        z-index: 70;
        width: min(19rem, calc(100vw - 24px));
        padding: 0.9rem 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 14px;
        font-size: 12.5px;
        line-height: 1.45;
        color: var(--color-ink);
        background: var(--glass-bg-strong);
        --apib-tone: var(--color-muted);
        --apib-dot-c: var(--color-muted);
        --apib-soft: var(--color-slate-soft);
    }

    .apib-pop[hidden] { display: none; }
    .apib-pop[data-status='online'] { --apib-tone: var(--apib-ok); --apib-dot-c: var(--apib-ok-dot); --apib-soft: var(--apib-ok-soft); }
    .apib-pop[data-status='lambat'],
    .apib-pop[data-status='gangguan'] { --apib-tone: var(--color-warn); --apib-dot-c: var(--color-warn); --apib-soft: var(--color-warn-soft); }
    .apib-pop[data-status='offline'] { --apib-tone: var(--apib-bad); --apib-dot-c: var(--apib-bad-dot); --apib-soft: var(--apib-bad-soft); }

    .apib-pop-head { display: flex; align-items: center; gap: 0.55rem; font-size: 13px; }
    .apib-pop-head strong { font-weight: 700; }
    .apib-pop-head [data-f='label'] { color: var(--apib-tone); }
    .apib-pop-detail { margin: 0.35rem 0 0.7rem; color: var(--color-muted); }

    .apib-meta {
        display: grid;
        gap: 0.4rem;
        margin: 0;
        padding-top: 0.7rem;
        border-top: 1px solid var(--color-line);
    }

    .apib-meta div { display: flex; justify-content: space-between; gap: 1rem; }
    .apib-meta dt { color: var(--color-muted); }
    .apib-meta dd { margin: 0; font-weight: 600; text-align: right; }

    /* --- Segarkan laporan (lingkup pengguna) ---------------------------- */
    .apib-refresh {
        margin-top: 0.75rem;
        padding-top: 0.7rem;
        border-top: 1px solid var(--color-line);
    }

    .apib-refresh[hidden] { display: none; }
    .apib-refresh-scope { margin-bottom: 0.45rem; font-size: 12px; color: var(--color-muted); }
    .apib-refresh-scope b { color: var(--color-ink); font-weight: 700; }

    .apib-refresh-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--color-accent-line);
        border-radius: 0.65rem;
        font: inherit;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--color-accent);
        background: var(--color-accent-soft);
        cursor: pointer;
        transition: filter 120ms ease, opacity 120ms ease;
    }

    .apib-refresh-btn:hover:not(:disabled) { filter: brightness(1.06); }
    .apib-refresh-btn:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
    .apib-refresh-btn:disabled { cursor: not-allowed; opacity: 0.6; }
    .apib-refresh-icon { width: 14px; height: 14px; }

    /* Ikon berputar selama proses jalan -- satu-satunya tanda gerak, karena
       hitung ulang bisa 5-20 detik dan tanpa ini tombolnya terlihat macet. */
    .apib-refresh.is-running .apib-refresh-icon { animation: apib-spin 1s linear infinite; }
    .apib-refresh-note { margin: 0.45rem 0 0; font-size: 11.5px; line-height: 1.45; color: var(--color-muted); }
    .apib-refresh.is-failed .apib-refresh-note { color: var(--apib-bad); }

    @keyframes apib-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .apib-refresh .apib-refresh-icon { animation: none !important; }
    }

    .apib-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }
</style>

<script>
    (function () {
        var POLL_MS = 60000;
        var WORD = { online: 'Online', lambat: 'Lambat', gangguan: 'Gangguan', offline: 'Offline', memeriksa: 'Memeriksa…', unknown: 'Tidak diketahui' };

        function relative(iso) {
            if (!iso) return '—';
            var s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
            if (s < 60) return s + ' detik lalu';
            if (s < 3600) return Math.round(s / 60) + ' menit lalu';
            if (s < 86400) return Math.round(s / 3600) + ' jam lalu';
            return Math.round(s / 86400) + ' hari lalu';
        }

        function dateTime(iso) {
            try {
                return new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return iso; }
        }

        function latency(ms) {
            if (ms === null || ms === undefined) return '—';
            return ms >= 1000 ? (ms / 1000).toFixed(1).replace('.', ',') + ' detik' : ms + ' ms';
        }

        function syncText(s) {
            if (!s) return '—';
            if (s.running) return 'Sinkronisasi berjalan: ' + s.processed + '/' + s.total + ' tabel';
            if (s.finished_at) return 'Disinkronkan ' + relative(s.finished_at) + ' (' + dateTime(s.finished_at) + ')';
            return 'Belum pernah disinkronkan';
        }

        function init(root) {
            var btn = root.querySelector('[data-api-badge-toggle]');
            var pop = root.querySelector('[data-api-badge-pop]');
            var live = root.querySelector('[data-api-badge-live]');
            var endpoint = root.getAttribute('data-endpoint');
            var refreshEndpoint = root.getAttribute('data-refresh-endpoint');
            var refreshBox = pop.querySelector('[data-apib-refresh]');
            var refreshBtn = pop.querySelector('[data-apib-refresh-btn]');
            var last = null;
            var lastStatus = null;
            var lastRefreshState = null;
            var fetchedAt = 0;
            var fastTimer = null;

            // Token CSRF dari cookie yang dipasang Laravel; tanpa ini POST
            // ditolak 419 oleh middleware web.
            function csrf() {
                var m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
                return m ? decodeURIComponent(m[1]) : '';
            }

            function detik(iso) {
                return Math.max(0, Math.round((new Date(iso).getTime() - Date.now()) / 1000));
            }

            function renderRefresh(r) {
                if (!r || !r.allowed) {
                    refreshBox.hidden = true;

                    return;
                }

                refreshBox.hidden = false;
                field('scope', r.scope_label || '—');

                var jeda = r.cooldown_until ? detik(r.cooldown_until) : 0;
                var running = r.state === 'running';

                refreshBox.classList.toggle('is-running', running);
                refreshBox.classList.toggle('is-failed', r.state === 'failed');
                refreshBtn.disabled = running || jeda > 0;
                field('refresh-label', running ? 'Menyegarkan…' : 'Segarkan laporan');

                if (running) {
                    field('refresh-note', 'Sedang menghitung ulang. Biasanya belasan detik, bisa sampai semenit saat server sibuk; halaman akan menyegarkan angkanya sendiri.');
                    clearTimeout(fastTimer);
                    fastTimer = setTimeout(load, 3000);
                } else if (r.state === 'failed') {
                    field('refresh-note', r.message || 'Penyegaran gagal. Coba lagi beberapa saat lagi.');
                } else if (jeda > 0) {
                    field('refresh-note', 'Baru saja diperbarui' + (r.duration_ms ? ' (' + Math.round(r.duration_ms / 1000) + ' detik)' : '') + '. Bisa disegarkan lagi dalam ' + jeda + ' detik.');
                } else if (r.finished_at) {
                    field('refresh-note', 'Terakhir disegarkan ' + relative(r.finished_at) + '. Tidak menarik data dari PD UNSRI.');
                } else {
                    field('refresh-note', 'Hitung ulang angka laporan dari data SAPTO terbaru — tidak menarik data dari PD UNSRI.');
                }

                // Selesai: beri tahu halaman supaya tabel yang sedang dibuka
                // memuat angka yang baru dihitung.
                if (lastRefreshState === 'running' && r.state === 'done') {
                    live.textContent = 'Laporan selesai disegarkan.';
                    window.dispatchEvent(new CustomEvent('sapto:reports-refreshed'));
                }

                lastRefreshState = r.state;
            }

            function mulaiRefresh() {
                refreshBtn.disabled = true;
                field('refresh-label', 'Menyegarkan…');
                field('refresh-note', 'Memulai…');

                fetch(refreshEndpoint, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (body) {
                        if (!r.ok) throw new Error(body.message || 'Tidak bisa memulai penyegaran.');
                        return body;
                    });
                }).then(function () {
                    lastRefreshState = 'running';
                    load();
                }).catch(function (e) {
                    refreshBox.classList.add('is-failed');
                    field('refresh-note', e.message);
                    refreshBtn.disabled = false;
                });
            }

            refreshBtn.addEventListener('click', mulaiRefresh);

            document.body.appendChild(pop);

            function field(name, text) {
                var el = pop.querySelector('[data-f="' + name + '"]');
                if (el) el.textContent = text;
            }

            function render(data, fallbackDetail) {
                var status = data && WORD[data.status] ? data.status : 'unknown';
                root.setAttribute('data-status', status);
                pop.setAttribute('data-status', status);
                field('label', data && data.label ? data.label : WORD[status]);
                field('detail', data && data.detail ? data.detail : fallbackDetail);
                field('via', data && data.via ? 'Lewat ' + data.via : 'Lewat Gravitee');
                field('latency', latency(data ? data.latency_ms : null));
                field('checked', data ? relative(data.checked_at) : '—');
                field('sync', data ? syncText(data.last_sync) : '—');
                renderRefresh(data ? data.refresh : null);
                btn.setAttribute('title', 'API PD UNSRI: ' + WORD[status]);
                btn.setAttribute('aria-label', 'Status API PD UNSRI: ' + WORD[status] + '. Tekan untuk detail.');

                // Umumkan ke screen reader hanya saat status berubah, bukan tiap polling.
                if (lastStatus !== null && lastStatus !== status) {
                    live.textContent = 'Status API PD UNSRI berubah: ' + WORD[status];
                }
                lastStatus = status;
            }

            function load() {
                fetchedAt = Date.now();
                fetch(endpoint, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function (r) {
                    if (r.status === 401) throw new Error('sesi');
                    if (!r.ok) throw new Error('http');
                    return r.json();
                }).then(function (data) {
                    last = data;
                    render(data);
                }).catch(function (e) {
                    last = null;
                    render(null, e.message === 'sesi'
                        ? 'Sesi berakhir — muat ulang halaman untuk melihat status.'
                        : 'Status API tidak dapat diambil saat ini.');
                });
            }

            function place() {
                var r = btn.getBoundingClientRect();
                var w = pop.offsetWidth;
                var left = Math.max(12, Math.min(r.right - w, window.innerWidth - w - 12));
                pop.style.top = Math.round(r.bottom + 8) + 'px';
                pop.style.left = Math.round(left) + 'px';
            }

            function open() {
                if (last) render(last); // segarkan teks "… lalu"
                pop.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                place();
            }

            function close(refocus) {
                if (pop.hidden) return;
                pop.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                if (refocus) btn.focus();
            }

            btn.addEventListener('click', function () {
                pop.hidden ? open() : close(false);
            });

            document.addEventListener('pointerdown', function (e) {
                if (!pop.hidden && !pop.contains(e.target) && !btn.contains(e.target)) close(false);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !pop.hidden) close(true);
            });

            window.addEventListener('resize', function () { if (!pop.hidden) place(); });
            window.addEventListener('scroll', function () { if (!pop.hidden) place(); }, { passive: true, capture: true });

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible' && Date.now() - fetchedAt > 30000) load();
            });

            setInterval(function () {
                if (document.visibilityState === 'visible') load();
            }, POLL_MS);

            load();
        }

        function boot() {
            document.querySelectorAll('[data-api-badge]').forEach(function (root) {
                if (root.getAttribute('data-api-badge-ready')) return;
                root.setAttribute('data-api-badge-ready', '1');
                init(root);
            });
        }

        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
    })();
</script>
@endonce
