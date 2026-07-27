#!/bin/sh
# Watchdog stack SAPTO. Dijalankan cron tiap 5 menit.
#
# Ada karena 2026-07-26 seluruh stack mati 25 jam tanpa ada yang tahu: server
# reboot, db & phpmyadmin kalah rebutan port host dengan container project lain
# (exit 255), lalu `app` yang waktu itu `restart: no` ikut mati permanen.
# `restart: always` TIDAK menyelamatkan kasus itu — Docker menyerah setelah
# beberapa kali gagal bind port, dan tidak mencoba lagi.
#
# Idempoten: kalau /up sehat, script keluar tanpa melakukan apa pun.
set -eu

cd "$(dirname "$0")/.."

LOG=storage/logs/watchdog.log
PATH=/usr/bin:/usr/local/bin:/bin

# Dua-duanya perlu dicek. `/up` Laravel tidak menyentuh database sama sekali —
# dengan db mati pun dia tetap 200, padahal insiden 2026-07-26 justru mulai dari
# db. Jadi cek juga semua service compose benar-benar running.
sehat() {
    [ "$(docker compose ps --services --status running | wc -l)" \
        -eq "$(docker compose config --services | wc -l)" ] &&
        curl -fsS -m 10 http://localhost:8020/up >/dev/null 2>&1
}

if sehat; then
    exit 0
fi

echo "$(date -Is) /up tidak sehat — menjalankan 'docker compose up -d'" >>"$LOG"
docker compose up -d >>"$LOG" 2>&1 || true

# Container "Started" != siap melayani: entrypoint masih cek composer lalu
# `artisan serve`. Tunggu sampai 60 detik sebelum menyerah. (Jangan pakai
# `curl --retry` di sini: bawaannya tidak mengulang saat connection refused.)
i=1
while [ "$i" -le 12 ]; do
    if sehat; then
        echo "$(date -Is) pulih" >>"$LOG"
        exit 0
    fi
    sleep 5
    i=$((i + 1))
done

# Paling sering: port 8020/8086 direbut container lain, atau image gagal build.
# Dua-duanya butuh mata manusia, watchdog tidak bisa menebak.
echo "$(date -Is) GAGAL PULIH — perlu dicek manual, lihat error di atas" >>"$LOG"

# ponytail: tanpa rotasi log — script hanya menulis saat stack sakit, jadi
# filenya tetap kecil. Pasang logrotate kalau sampai ramai.
