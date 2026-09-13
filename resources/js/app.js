// Deteksi otomatis kalau server masuk maintenance mode ("php artisan down")
// selagi halaman sedang terbuka -- begitu terdeteksi, halaman di-reload
// supaya Laravel menyajikan resources/views/errors/503.blade.php (yang
// punya polling sendiri buat balik otomatis begitu "php artisan up"
// dijalankan). Threshold 2 kegagalan beruntun (bukan 1) supaya blip
// jaringan sesaat di sisi klien tidak memicu reload yang mengganggu kerja
// pengguna (mis. lagi mengisi form/filter tabel).
(function () {
    if (window.__saptoHealthWatch) return;
    window.__saptoHealthWatch = true;

    var failures = 0;
    var THRESHOLD = 2;
    var INTERVAL_MS = 20000;

    function check() {
        fetch('/ping', { cache: 'no-store' })
            .then(function (res) {
                failures = res.ok ? 0 : failures + 1;
                if (failures >= THRESHOLD) window.location.reload();
            })
            .catch(function () {
                failures++;
                if (failures >= THRESHOLD) window.location.reload();
            });
    }

    setInterval(check, INTERVAL_MS);
})();
