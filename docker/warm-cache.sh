#!/bin/bash
# Panaskan ulang cache ringkasan tabel SAPTO (cron tiap 6 jam).
#
# TTL cache 24 jam, jadi tanpa ini pengunjung pertama setelah cache habis
# menanggung hitung dingin ~20 detik (terberat: dosen-pembimbing-ta, full
# scan ~400rb baris dengan `like '%...%'` yang tidak bisa ditolong index).
# Enam jam sekali juga membuat hasil sinkronisasi data ikut cepat terlihat.
#
# Dipasang bersama docker/watchdog.sh — lihat CLAUDE.md.
set -euo pipefail

cd "$(dirname "$0")/.."

LOG=storage/logs/warm-cache.log

# Kalau stack sedang mati, watchdog yang mengurus; jangan bikin cron gagal.
if ! docker compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "[$(date '+%F %T')] app tidak berjalan, lewati pemanasan." >> "$LOG"
    exit 0
fi

echo "[$(date '+%F %T')] mulai" >> "$LOG"
# --prodi ikut memanaskan ~97 scope korprodi (~7 menit). Tanpa itu akun
# korprodi tetap menanggung ~5 detik saat pertama buka, karena tabel seperti
# dosen-pembimbing-ta memfilter prodi di dalam SQL sehingga cache-nya terpisah.
docker compose exec -T app php artisan sapto:warm --prodi >> "$LOG" 2>&1
echo "[$(date '+%F %T')] selesai" >> "$LOG"
