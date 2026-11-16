@extends('layouts.admin')

@section('title', 'Event Stream (Kafka)')
@section('subtitle', 'Kabar penting dari SAPTO (login, perubahan peran, sinkronisasi data) dicatat lewat Kafka. Di sini Anda bisa melihat apakah alurnya sehat.')

@push('styles')
<style>
    .kf-page {
        --kf-ok: var(--color-accent);
        --kf-warn: var(--m-warn, #9a5b00);
        --kf-down: var(--color-warn);
        display: grid;
        gap: 1rem;
    }

    .kf-h2 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: -0.01em; color: var(--color-ink); }
    .kf-muted { color: var(--color-muted); }
    .kf-num { font-variant-numeric: tabular-nums; }
    .kf-panel { padding: 1.1rem 1.15rem 1.2rem; }
    .kf-panel-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 0.4rem 1rem; margin-bottom: 0.85rem; }
    .kf-panel-head p { margin: 0; font-size: 12.5px; color: var(--color-muted); }

    /* --- Alur ------------------------------------------------------------ */
    .kf-flow { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.6rem; margin: 0; padding: 0; list-style: none; }
    .kf-step { position: relative; display: flex; flex-direction: column; gap: 0.25rem; padding: 0.8rem 0.85rem; border: 1px solid var(--color-line); border-radius: 0.9rem; background: var(--color-surface); }
    .kf-step b { font-size: 13px; font-weight: 800; color: var(--color-ink); }
    .kf-step span { font-size: 12px; line-height: 1.5; color: var(--color-muted); }
    .kf-step.is-kafka { border-color: var(--color-accent-line); background: var(--color-accent-soft); }
    .kf-step:not(:last-child)::after { content: '→'; position: absolute; top: 50%; right: -0.62rem; z-index: 1; transform: translateY(-50%); font-size: 14px; font-weight: 800; color: var(--color-muted); }
    .kf-explain { margin-top: 0.8rem; }
    .kf-explain summary { min-height: 44px; display: flex; align-items: center; cursor: pointer; font-size: 13px; font-weight: 700; color: var(--color-accent-dark); }
    .kf-explain p { margin: 0.2rem 0 0.6rem; max-width: 70ch; font-size: 13px; line-height: 1.65; color: var(--color-muted); }
    .kf-explain p b { color: var(--color-ink); }

    /* --- Kartu status ---------------------------------------------------- */
    .kf-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(13.5rem, 1fr)); gap: 0.75rem; }
    .kf-card { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; padding: 1rem 1.1rem; border-radius: 1.1rem; }
    .kf-card-label { font-size: 11px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; color: var(--color-muted); }
    .kf-card-value { display: flex; align-items: center; gap: 0.5rem; font-size: 17px; font-weight: 800; line-height: 1.25; color: var(--color-ink); }
    .kf-card-sub { font-size: 12.5px; line-height: 1.5; color: var(--color-muted); overflow-wrap: anywhere; }

    .kf-dot { flex-shrink: 0; width: 10px; height: 10px; border-radius: 999px; background: var(--color-muted); box-shadow: 0 0 0 4px var(--color-slate-soft); }
    .kf-dot.is-ok { background: var(--kf-ok); box-shadow: 0 0 0 4px var(--color-accent-soft); }
    .kf-dot.is-warn { background: var(--kf-warn); box-shadow: 0 0 0 4px var(--m-warn-soft, var(--color-slate-soft)); }
    .kf-dot.is-down { background: var(--kf-down); box-shadow: 0 0 0 4px var(--color-warn-soft); }

    /* --- Event uji ------------------------------------------------------- */
    .kf-test { display: flex; flex-wrap: wrap; align-items: center; gap: 0.9rem 1.2rem; }
    .kf-test-text { flex: 1 1 20rem; min-width: 0; }
    .kf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; min-height: 44px; padding: 0 1.15rem; border: 0; border-radius: 0.8rem; background: var(--color-accent); color: var(--color-surface); font: inherit; font-size: 13.5px; font-weight: 800; cursor: pointer; }
    .kf-btn:disabled { opacity: 0.55; cursor: progress; }
    .kf-btn:focus-visible, .kf-chip:focus-visible, .kf-explain summary:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
    .kf-track { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; width: 100%; margin: 0; padding: 0; list-style: none; }
    .kf-track[hidden] { display: none; }
    .kf-track li { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.7rem 0.8rem; border: 1px dashed var(--color-line); border-radius: 0.8rem; font-size: 12px; color: var(--color-muted); }
    .kf-track li b { font-size: 12.5px; color: var(--color-ink); }
    .kf-track li.is-done { border-style: solid; border-color: var(--color-accent-line); background: var(--color-accent-soft); }
    .kf-track li.is-fail { border-style: solid; border-color: var(--color-warn-line); background: var(--color-warn-soft); }

    /* --- Topic & event --------------------------------------------------- */
    .kf-topics { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 0.7rem; }
    .kf-topic { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.9rem 1rem; border: 1px solid var(--color-line); border-radius: 0.9rem; background: var(--color-surface); }
    .kf-topic code { font-size: 12.5px; font-weight: 700; color: var(--color-ink); overflow-wrap: anywhere; }
    .kf-topic p { margin: 0; font-size: 12.5px; line-height: 1.5; color: var(--color-muted); }
    .kf-topic dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem 0.8rem; margin: 0; }
    .kf-topic dt { font-size: 11px; color: var(--color-muted); }
    .kf-topic dd { margin: 0; font-size: 15px; font-weight: 800; color: var(--color-ink); }

    .kf-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.8rem; }
    .kf-chip { min-height: 36px; padding: 0 0.85rem; border: 1px solid var(--color-line); border-radius: 999px; background: var(--color-surface); color: var(--color-ink); font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; }
    .kf-chip[aria-pressed="true"] { border-color: transparent; background: var(--color-accent); color: var(--color-surface); font-weight: 800; }

    .kf-events { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
    .kf-event { display: grid; grid-template-columns: 9.5rem minmax(0, 1fr) auto; gap: 0.2rem 1rem; align-items: baseline; padding: 0.7rem 0; border-top: 1px solid var(--color-line); }
    .kf-event:first-child { border-top: 0; }
    .kf-event time { font-size: 12px; color: var(--color-muted); font-variant-numeric: tabular-nums; }
    .kf-event-main { min-width: 0; }
    .kf-event-main b { font-size: 13.5px; font-weight: 700; color: var(--color-ink); }
    .kf-event-main span { display: block; font-size: 12.5px; line-height: 1.5; color: var(--color-muted); overflow-wrap: anywhere; }
    .kf-event-meta { font-size: 11.5px; color: var(--color-muted); text-align: right; font-variant-numeric: tabular-nums; }
    .kf-empty { padding: 1.4rem 0.5rem; text-align: center; font-size: 13px; color: var(--color-muted); }

    .kf-error { margin: 0.6rem 0 0; padding: 0.7rem 0.85rem; border: 1px solid var(--color-warn-line); border-radius: 0.8rem; background: var(--color-warn-soft); font-size: 12.5px; line-height: 1.55; color: var(--color-ink); overflow-wrap: anywhere; }
    .kf-error[hidden] { display: none; }
    .kf-foot { font-size: 12.5px; line-height: 1.65; color: var(--color-muted); }
    .kf-foot code { font-size: 12px; }

    @media (max-width: 900px) {
        .kf-flow { grid-template-columns: 1fr; }
        .kf-step:not(:last-child)::after { content: '↓'; top: auto; right: auto; bottom: -0.78rem; left: 50%; transform: translateX(-50%); }
        .kf-track { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .kf-event { grid-template-columns: 1fr; }
        .kf-event-meta { text-align: left; }
    }
</style>
@endpush

@section('content')
    <noscript>
        <div class="adm-toast adm-toast-err">Halaman ini butuh JavaScript untuk menampilkan status Kafka.</div>
    </noscript>

    <div class="kf-page" id="kf-app"
         data-status-url="{{ route('admin.kafka.status') }}"
         data-test-url="{{ route('admin.kafka.test') }}"
         data-csrf="{{ csrf_token() }}">

        <section class="glass-strong adm-panel kf-panel" aria-labelledby="kf-flow-title">
            <div class="kf-panel-head">
                <h2 class="kf-h2" id="kf-flow-title">Bagaimana alurnya?</h2>
                <p>Kafka bekerja seperti kotak surat bernomor urut.</p>
            </div>
            <ol class="kf-flow">
                <li class="kf-step"><b>1. Kejadian di SAPTO</b><span>Ada yang login, peran diubah, atau sinkronisasi data selesai.</span></li>
                <li class="kf-step"><b>2. Producer</b><span>SAPTO menulis satu "kabar" (event) lalu mengirimnya ke Kafka.</span></li>
                <li class="kf-step is-kafka"><b>3. Kafka</b><span>Menyimpan kabar berurutan dalam topic (antrean bernomor). Kabar tidak hilang walau belum dibaca.</span></li>
                <li class="kf-step"><b>4. Consumer</b><span>Proses di SAPTO yang membaca kabar dari Kafka, satu per satu sesuai urutan.</span></li>
                <li class="kf-step"><b>5. Arsip &amp; notifikasi</b><span>Kabar disimpan permanen di database. Hasil sinkronisasi juga muncul sebagai pemberitahuan untuk admin.</span></li>
            </ol>
            <details class="kf-explain">
                <summary>Apa gunanya Kafka untuk SAPTO?</summary>
                <p><b>Jejak audit.</b> Setiap login, perubahan hak akses, dan penyamaran admin tercatat rapi dan berurutan. Ini yang ditanyakan auditor akreditasi pertama kali.</p>
                <p><b>Pemisahan tugas.</b> SAPTO cukup "mengumumkan" sebuah kejadian. Sistem lain (arsip, notifikasi, dan nanti dasbor atau laporan) bisa ikut membaca tanpa mengubah kode SAPTO yang sudah berjalan.</p>
                <p><b>Tahan gangguan.</b> Kalau Kafka sedang mati, kabar ditampung di antrean kirim ulang dan dikirim lagi otomatis tiap menit. Login dan sinkronisasi tidak pernah ikut tertahan.</p>
            </details>
        </section>

        <section class="kf-cards" aria-label="Ringkasan status">
            <article class="kf-card glass-strong">
                <span class="kf-card-label">Kafka (REST Proxy)</span>
                <span class="kf-card-value"><span class="kf-dot" id="kf-proxy-dot"></span><span id="kf-proxy-label">Memeriksa…</span></span>
                <span class="kf-card-sub" id="kf-proxy-sub"></span>
            </article>
            <article class="kf-card glass-strong">
                <span class="kf-card-label">Consumer</span>
                <span class="kf-card-value"><span class="kf-dot" id="kf-consumer-dot"></span><span id="kf-consumer-label">Memeriksa…</span></span>
                <span class="kf-card-sub" id="kf-consumer-sub"></span>
            </article>
            <article class="kf-card glass-strong">
                <span class="kf-card-label">Antrean kirim ulang</span>
                <span class="kf-card-value"><span class="kf-dot" id="kf-outbox-dot"></span><span id="kf-outbox-label">Memeriksa…</span></span>
                <span class="kf-card-sub" id="kf-outbox-sub">Event yang gagal terkirim menunggu di sini.</span>
            </article>
            <article class="kf-card glass-strong">
                <span class="kf-card-label">Event 24 jam terakhir</span>
                <span class="kf-card-value kf-num" id="kf-24h">—</span>
                <span class="kf-card-sub" id="kf-24h-sub">Tersimpan di arsip database.</span>
            </article>
        </section>
        <p class="kf-error" id="kf-error" role="alert" hidden></p>

        <section class="glass-strong adm-panel kf-panel" aria-labelledby="kf-test-title">
            <div class="kf-test">
                <div class="kf-test-text">
                    <h2 class="kf-h2" id="kf-test-title">Coba alurnya</h2>
                    <p class="kf-muted" style="margin:0.3rem 0 0;font-size:12.5px;line-height:1.55">Kirim satu event uji, lalu lihat sendiri kabar itu masuk ke Kafka dan dibaca consumer. Aman: event uji hanya tercatat di arsip.</p>
                </div>
                <button type="button" class="kf-btn" id="kf-test-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    Kirim event uji
                </button>
                <ol class="kf-track" id="kf-track" hidden aria-live="polite">
                    <li id="kf-t1"><b>1. Dikirim ke Kafka</b><span>Menunggu…</span></li>
                    <li id="kf-t2"><b>2. Diterima broker</b><span>Menunggu…</span></li>
                    <li id="kf-t3"><b>3. Dibaca consumer &amp; diarsipkan</b><span>Menunggu…</span></li>
                </ol>
            </div>
        </section>

        <section class="glass-strong adm-panel kf-panel" aria-labelledby="kf-topics-title">
            <div class="kf-panel-head">
                <h2 class="kf-h2" id="kf-topics-title">Topic</h2>
                <p>Topic = satu antrean untuk satu jenis kabar.</p>
            </div>
            <div class="kf-topics" id="kf-topics">
                @foreach ($topics as $name => $topic)
                    <article class="kf-topic">
                        <code>{{ $name }}</code>
                        <p>{{ $topic['description'] }}</p>
                        <dl><div><dt>Pesan di Kafka</dt><dd class="kf-num">—</dd></div><div><dt>Di arsip database</dt><dd class="kf-num">—</dd></div></dl>
                    </article>
                @endforeach
            </div>
            <p class="kf-foot" style="margin:0.8rem 0 0">
                Angka "Pesan di Kafka" boleh sedikit lebih besar dari "Di arsip database". Kafka menjamin pesan <b>tidak hilang</b>,
                dan konsekuensinya satu pesan kadang terkirim dua kali (mis. jawaban broker terlambat lalu SAPTO mengirim ulang).
                Consumer mengenali pesan kembar lewat <code>event_id</code> dan hanya mengarsipkannya sekali.
            </p>
        </section>

        <section class="glass-strong adm-panel kf-panel" aria-labelledby="kf-events-title">
            <div class="kf-panel-head">
                <h2 class="kf-h2" id="kf-events-title">Event terbaru</h2>
                <p id="kf-events-count"></p>
            </div>
            <div class="kf-chips" role="group" aria-label="Saring per topic">
                <button type="button" class="kf-chip" data-topic="" aria-pressed="true">Semua</button>
                @foreach ($topics as $name => $topic)
                    <button type="button" class="kf-chip" data-topic="{{ $name }}" aria-pressed="false">{{ \Illuminate\Support\Str::after($name, 'sapto.') }}</button>
                @endforeach
            </div>
            <ul class="kf-events" id="kf-events"></ul>
        </section>

        <p class="kf-foot">
            Melihat isi mentah Kafka: kafka-ui hanya bisa dibuka lewat SSH tunnel
            (<code>ssh -L 8088:127.0.0.1:8088 &lt;user&gt;@&lt;server&gt;</code>, lalu <code>http://localhost:8088</code>), sandinya ada di <code>.env</code> (<code>KAFKA_UI_PASSWORD</code>).
            Penjelasan lengkap, perintah operasional, dan panduan masalah ada di <code>docs/kafka.md</code>.
        </p>
    </div>

    <script>
    (function () {
        var app = document.getElementById('kf-app');
        var statusUrl = app.dataset.statusUrl;
        var topic = '';
        var timer = null;
        var watching = null; // { id, since }

        function $(id) { return document.getElementById(id); }
        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function nf(n) { return Number(n || 0).toLocaleString('id-ID'); }
        function ago(iso) {
            if (!iso) return '—';
            var s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
            if (s < 5) return 'baru saja';
            if (s < 60) return s + ' detik lalu';
            if (s < 3600) return Math.round(s / 60) + ' menit lalu';
            if (s < 86400) return Math.round(s / 3600) + ' jam lalu';
            return Math.round(s / 86400) + ' hari lalu';
        }
        function clock(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        function dot(id, state) { $(id).className = 'kf-dot' + (state ? ' is-' + state : ''); }

        function render(d) {
            var px = d.proxy, c = d.consumer, ob = d.outbox;

            if (!d.enabled) {
                dot('kf-proxy-dot', 'warn'); $('kf-proxy-label').textContent = 'Dinonaktifkan';
            } else if (px.ok) {
                dot('kf-proxy-dot', 'ok'); $('kf-proxy-label').textContent = 'Terhubung';
            } else {
                dot('kf-proxy-dot', 'down'); $('kf-proxy-label').textContent = 'Tidak terhubung';
            }
            $('kf-proxy-sub').textContent = px.ok
                ? px.brokers + ' broker · jawaban ' + px.latency_ms + ' ms'
                : 'Alamat ' + px.url;

            if (!d.enabled || !px.ok) {
                dot('kf-consumer-dot', d.enabled ? 'down' : 'warn'); $('kf-consumer-label').textContent = c.alive ? 'Berjalan' : 'Tidak aktif';
                $('kf-consumer-sub').textContent = 'Grup ' + c.group;
            } else if (!c.alive) {
                dot('kf-consumer-dot', 'warn'); $('kf-consumer-label').textContent = 'Tidak aktif';
                $('kf-consumer-sub').textContent = 'Tidak ada detak terakhir. Proses consumer dinyalakan otomatis saat container app naik.';
            } else {
                var lag = c.lag == null ? 0 : c.lag;
                dot('kf-consumer-dot', lag > 50 ? 'warn' : 'ok');
                $('kf-consumer-label').textContent = lag === 0 ? 'Berjalan, semua terbaca' : 'Berjalan, ' + nf(lag) + ' menunggu dibaca';
                $('kf-consumer-sub').textContent = 'Grup ' + c.group + ' · ' + nf(c.processed) + ' event sejak dinyalakan · detak ' + ago(c.heartbeat_at);
            }

            var pend = ob.pending + ob.dead;
            dot('kf-outbox-dot', ob.dead > 0 ? 'down' : (ob.pending > 0 ? 'warn' : 'ok'));
            $('kf-outbox-label').textContent = pend === 0 ? 'Kosong' : nf(ob.pending) + ' menunggu' + (ob.dead ? ', ' + nf(ob.dead) + ' gagal permanen' : '');
            $('kf-outbox-sub').textContent = pend === 0
                ? 'Semua event terkirim. Antrean ini hanya terisi saat Kafka bermasalah.'
                : 'Dikirim ulang otomatis tiap menit. Dicoba sampai berhasil.';

            $('kf-24h').textContent = nf(d.events_24h);

            var err = $('kf-error');
            var msg = !d.enabled ? '' : (!px.ok ? 'REST Proxy tidak dapat dihubungi: ' + px.error : (c.error ? 'Consumer melaporkan: ' + c.error : ''));
            err.hidden = !msg; err.textContent = msg;

            $('kf-topics').innerHTML = d.topics.map(function (t) {
                return '<article class="kf-topic"><code>' + esc(t.name) + '</code><p>' + esc(t.description) + '</p>' +
                    '<dl><div><dt>Pesan di Kafka</dt><dd class="kf-num">' + (t.messages == null ? '—' : nf(t.messages)) + '</dd></div>' +
                    '<div><dt>Di arsip database</dt><dd class="kf-num">' + nf(t.archived) + '</dd></div>' +
                    '<div><dt>Partisi</dt><dd class="kf-num">' + t.partitions + '</dd></div>' +
                    '<div><dt>Disimpan</dt><dd class="kf-num">' + t.retention_days + ' hari</dd></div></dl></article>';
            }).join('');

            var list = $('kf-events');
            $('kf-events-count').textContent = d.events.length ? 'Menampilkan ' + d.events.length + ' terbaru' : '';
            list.innerHTML = d.events.length ? d.events.map(function (e) {
                return '<li class="kf-event"><time datetime="' + esc(e.consumed_at) + '" title="' + esc(clock(e.occurred_at)) + '">' + esc(ago(e.occurred_at)) + '</time>' +
                    '<div class="kf-event-main"><b>' + esc(e.label) + '</b>' +
                    (e.detail ? '<span>' + esc(e.detail) + '</span>' : '') +
                    (e.actor ? '<span>oleh ' + esc(e.actor) + '</span>' : '') + '</div>' +
                    '<div class="kf-event-meta">' + esc(e.topic.replace('sapto.', '')) + '<br>partisi ' + e.partition + ' · nomor ' + e.offset + '</div></li>';
            }).join('') : '<li class="kf-empty">Belum ada event' + (topic ? ' di topic ini' : '') + '. Coba tombol "Kirim event uji" di atas.</li>';

            track(d);
        }

        function step(id, state, text) {
            var el = $(id);
            el.className = state ? 'is-' + state : '';
            el.querySelector('span').textContent = text;
        }

        // Memantau event uji: selesai saat event_id muncul di arsip.
        function track(d) {
            if (!watching) return;
            var hit = d.events.filter(function (e) { return e.event_id === watching.id; })[0];
            if (hit) {
                step('kf-t3', 'done', 'Tersimpan di arsip ' + Math.max(0, Math.round((new Date(hit.consumed_at) - new Date(hit.occurred_at)) / 1000)) + ' detik setelah dikirim.');
                watching = null; schedule(10000); return;
            }
            if (Date.now() - watching.since > 25000) {
                step('kf-t3', 'fail', 'Belum terbaca consumer setelah 25 detik. Lihat kartu "Consumer" di atas.');
                watching = null; schedule(10000);
            }
        }

        function load() {
            var url = statusUrl + (topic ? '?topic=' + encodeURIComponent(topic) : '');
            return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(render)
                .catch(function (e) {
                    var err = $('kf-error'); err.hidden = false; err.textContent = 'Status tidak bisa dimuat: ' + e.message;
                });
        }

        function schedule(ms) {
            clearTimeout(timer);
            timer = setTimeout(function () { load().then(function () { schedule(watching ? 1000 : 10000); }); }, ms);
        }

        document.querySelectorAll('.kf-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                topic = chip.dataset.topic;
                document.querySelectorAll('.kf-chip').forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
                load();
            });
        });

        $('kf-test-btn').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            $('kf-track').hidden = false;
            step('kf-t1', '', 'Mengirim…'); step('kf-t2', '', 'Menunggu…'); step('kf-t3', '', 'Menunggu…');

            fetch(app.dataset.testUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': app.dataset.csrf } })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                .then(function (res) {
                    var j = res.body;
                    if (!j.event_id) { step('kf-t1', 'fail', j.message || 'Gagal mengirim.'); return; }
                    step('kf-t1', 'done', 'Event ' + j.event_id.slice(0, 8) + ' dikirim.');
                    if (j.ok) {
                        step('kf-t2', 'done', 'Diterima broker: partisi ' + j.partition + ', nomor ' + j.offset + '.');
                        step('kf-t3', '', 'Menunggu consumer membaca…');
                        watching = { id: j.event_id, since: Date.now() };
                        schedule(800);
                    } else {
                        step('kf-t2', 'fail', j.message);
                        step('kf-t3', '', 'Akan terbaca setelah antrean kirim ulang berhasil.');
                        schedule(2000);
                    }
                })
                .catch(function (e) { step('kf-t1', 'fail', 'Gagal: ' + e.message); })
                .then(function () { setTimeout(function () { btn.disabled = false; }, 3000); });
        });

        load().then(function () { schedule(10000); });
    })();
    </script>
@endsection
