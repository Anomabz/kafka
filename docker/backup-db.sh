#!/bin/sh
# Backup harian db SAPTO. Dijalankan cron tiap hari jam 03:00.
#
# Data mirror Feeder (list_mahasiswa, program_studi, dst.) bisa di-sync ulang
# kalau hilang -- tapi `users` (akun admin) dan `korprodi` (pemetaan NIP ke
# prodi) itu data asli SAPTO sendiri, tidak ada sumber lain untuk mengembalikannya.
# Sebelum script ini ada, tidak ada backup db sama sekali.
#
# Idempoten per hari: nama file pakai timestamp, dump lama otomatis dibuang.
set -eu

cd "$(dirname "$0")/.."

DEST=BACKUPP_SAPTO_PROD
LOG=storage/logs/backup.log
RETAIN_DAYS=14
PATH=/usr/bin:/usr/local/bin:/bin

mkdir -p "$DEST"

FILE="$DEST/sapto_db_$(date +%Y%m%d_%H%M%S).sql.gz"

if docker compose exec -T db mysqldump -uroot -prootpassword sapto_db | gzip > "$FILE"; then
    echo "$(date -Is) backup ok: $FILE ($(du -h "$FILE" | cut -f1))" >>"$LOG"
else
    rm -f "$FILE"
    echo "$(date -Is) backup GAGAL" >>"$LOG"
    exit 1
fi

find "$DEST" -name 'sapto_db_*.sql.gz' -mtime "+$RETAIN_DAYS" -delete
