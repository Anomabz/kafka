#!/bin/sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install
else
    echo "Composer dependencies already installed."
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    test -f database/database.sqlite || touch database/database.sqlite
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --seed --force
else
    echo "Skipping migrations and seeders because RUN_MIGRATIONS is not true."
fi

# View di-cache supaya blade tidak di-compile ulang tiap request.
#
# config:cache sengaja TIDAK dipakai: `php artisan test` dijalankan di
# container yang sama, dan config.php yang ter-cache menang atas env
# testing di phpunit.xml -> sesi/CSRF ikut config produksi dan test login
# gagal dengan 419. Untungnya ongkos parse config kecil dibanding opcache
# + pemanasan cache di bawah. route:cache juga tidak: routes/web.php masih
# memakai closure route yang tidak bisa diserialisasi.
php artisan config:clear || true
php artisan view:cache || true

# Panaskan cache tabel di latar belakang. Query terberat (dosen-pembimbing-ta:
# full scan ~400rb baris dengan `like '%...%'`) butuh belasan detik saat dingin,
# dan tanpa ini ongkos itu ditanggung pengunjung pertama setelah cache habis.
#
# Dua tahap: scope universitas dulu (~10 detik, bikin akun admin langsung
# enak), baru --prodi untuk ~97 scope korprodi (~7 menit). Keduanya di latar
# belakang supaya /up tidak menunggu, dan urutannya bikin bagian yang paling
# cepat berguna selesai lebih dulu.
(php artisan sapto:warm && php artisan sapto:warm --prodi) >/dev/null 2>&1 &

php artisan serve --host=0.0.0.0 --port=8000
