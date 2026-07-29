<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" id="theme-color-meta" content="#e7ecf5">
    <title>Login sebagai — SAPTO Admin</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sapto-theme');
                if (t !== 'dark' && t !== 'light') {
                    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
                var m = document.getElementById('theme-color-meta');
                if (m) m.setAttribute('content', t === 'dark' ? '#0a0a0c' : '#e7ecf5');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans text-[14px] antialiased">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[18px] font-extrabold tracking-tight text-ink sm:text-[22px]">Login sebagai</h1>
                <p class="mt-1 text-[13px] text-muted">Uji tampilan SAPTO sebagai koordinator program studi. Kamu bisa kembali ke akun admin kapan saja.</p>
            </div>
            <a href="{{ url('/') }}" class="unit-pill shrink-0" style="text-decoration:none">← Ke aplikasi</a>
        </div>

        <div class="glass mb-4 rounded-2xl p-3.5 sm:p-4">
            <label for="cari" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">Cari nama / NIP / prodi</label>
            <input id="cari" type="text" class="filter-select" placeholder="Ketik untuk memfilter…" autocomplete="off" autofocus>
        </div>

        <div id="daftar" class="space-y-2">
            @foreach ($korprodis as $k)
                <form method="POST" action="{{ route('admin.login-as.store') }}" data-search="{{ $k['search'] }}"
                      class="ma-row glass flex items-center gap-3 rounded-2xl p-3 sm:p-3.5">
                    @csrf
                    <input type="hidden" name="nip" value="{{ $k['nip'] }}">
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="truncate text-[14px] font-semibold text-ink">{{ $k['nama'] }}</div>
                        <div class="truncate text-[12px] text-muted">{{ $k['prodi'] }} — NIP {{ $k['nip'] }}</div>
                    </div>
                    <button type="submit" class="unit-pill shrink-0" style="cursor:pointer;border:0;background:var(--color-accent);color:#fff">
                        Masuk sebagai
                    </button>
                </form>
            @endforeach
            <p id="kosong" class="hidden px-3 py-6 text-center text-[13px] text-muted">Tidak ada korprodi yang cocok.</p>
        </div>

        <div class="glass mt-4 rounded-2xl p-3.5 sm:p-4">
            <div class="text-[12px] font-semibold text-muted">Uji akun tanpa akses</div>
            <p class="mt-0.5 text-[12px] text-muted">Login sebagai NIP yang bukan korprodi untuk melihat halaman "Akses ditolak".</p>
            <form method="POST" action="{{ route('admin.login-as.store') }}" class="mt-2 flex items-center gap-2">
                @csrf
                <input type="hidden" name="any" value="1">
                <input type="text" name="nip" class="filter-select" value="0000000000" placeholder="NIP uji">
                <button type="submit" class="unit-pill shrink-0" style="cursor:pointer;border:0">Uji</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var cari = document.getElementById('cari');
            var rows = Array.prototype.slice.call(document.querySelectorAll('.ma-row'));
            var kosong = document.getElementById('kosong');
            cari.addEventListener('input', function () {
                var q = cari.value.trim().toLowerCase();
                var shown = 0;
                rows.forEach(function (row) {
                    var match = !q || (row.dataset.search || '').indexOf(q) !== -1;
                    row.classList.toggle('hidden', !match);
                    if (match) shown++;
                });
                kosong.classList.toggle('hidden', shown > 0);
            });
        })();
    </script>
</body>
</html>
