# Kafka di SAPTO

Dokumen ini untuk tim yang belum pernah memakai Kafka. Bagian 1–3 cukup untuk
memahami apa yang terjadi. Bagian 4 dan seterusnya untuk yang mengoperasikan.

## 1. Apa itu Kafka, dalam satu paragraf

Bayangkan **kotak surat bernomor urut**. Siapa pun boleh memasukkan surat
(*producer*), surat diberi nomor 0, 1, 2, … dan **tidak pernah hilang** selama
masa simpannya, walau belum dibaca. Pembaca (*consumer*) mencatat "saya sudah
sampai nomor berapa", jadi kalau pembaca mati lalu hidup lagi, ia melanjutkan
dari nomor terakhir, tidak mengulang dan tidak melewatkan. Satu kotak surat
untuk satu jenis kabar disebut **topic**. Kafka adalah program yang menjaga
kotak-kotak surat itu tetap rapi dan tahan gangguan.

## 2. Apa yang dilakukan Kafka di SAPTO

SAPTO "mengumumkan" kejadian penting sebagai **event** ke Kafka:

| Topic | Isinya | Kapan terkirim |
| --- | --- | --- |
| `sapto.audit` | Jejak audit | Login berhasil/ditolak, logout, akses diberikan/dicabut, admin mulai/berhenti menyamar, event uji |
| `sapto.sync.table_updated` | Satu tabel PD UNSRI selesai diperbarui | Tiap tabel selesai disinkronkan |
| `sapto.sync.batch_finished` | Satu putaran sinkronisasi selesai | Sesudah semua tabel diproses |

Sebuah consumer di dalam SAPTO membaca semuanya lalu:

1. **menyimpan** tiap event ke tabel `kafka_event_log` (arsip permanen, Kafka
   sendiri hanya menyimpan 30–90 hari);
2. membuat **notifikasi untuk admin** saat sinkronisasi selesai.

Gunanya: jejak audit yang rapi (pertanyaan pertama auditor akreditasi), dan
SAPTO cukup mengumumkan kejadian sehingga sistem lain bisa ikut membaca tanpa
mengubah kode SAPTO yang sudah berjalan.

## 3. Alur lengkap

```
 kejadian di SAPTO ──► KafkaProducerService ──HTTP──► kafka-rest ──► kafka (broker)
 (login, peran,         (amplop event +                (REST Proxy)     topic sapto.*
  sinkronisasi)          circuit breaker)                                   │
        │ gagal kirim                                                       │ dibaca
        ▼                                                                   ▼
   tabel kafka_outbox ──dikirim ulang tiap menit──► (kembali ke atas)   sapto:kafka-consume
                                                                            │
                                                          ┌─────────────────┴──────────┐
                                                          ▼                            ▼
                                                 tabel kafka_event_log        web_notifications
                                                 (arsip, sekali per event)    (khusus admin)
```

Bentuk semua event (amplop yang sama untuk semua topic):

```json
{
  "event_id": "2d2cd7af-5c31-4e0f-8c8c-93d5f4649f82",
  "type": "auth.login",
  "occurred_at": "2026-09-30T00:35:06+00:00",
  "source": "sapto",
  "actor": {"email": "…", "nip": "…", "name": "…", "impersonating": false},
  "data": {"ip": "114.10.99.2", "admin": true}
}
```

## 4. Melihatnya langsung

**Halaman admin (paling mudah):** SAPTO → Akun/Admin → **Event Stream (Kafka)**,
alamat `/admin/kafka`. Isinya: status Kafka/consumer/antrean kirim ulang,
daftar topic dengan jumlah pesan, event terbaru, dan tombol **Kirim event uji**
yang memperlihatkan langkah demi langkah event masuk ke Kafka lalu dibaca
consumer.

**kafka-ui (isi mentah):** hanya bisa dibuka lewat SSH tunnel. Port 8088 sengaja
hanya mendengarkan di `127.0.0.1` server dan memakai login.

```bash
ssh -L 8088:127.0.0.1:8088 <user>@<server>     # biarkan terbuka
# lalu di browser laptop: http://localhost:8088
# user: KAFKA_UI_USER (bawaan: admin), sandi: KAFKA_UI_PASSWORD di .env server
```

## 5. Komponen dan file

| Bagian | Di mana |
| --- | --- |
| Konfigurasi & daftar topic | `config/kafka.php` |
| Kirim event | `app/Services/KafkaProducerService.php` |
| Bicara ke REST Proxy | `app/Services/Kafka/KafkaRestProxy.php` |
| Apa yang dilakukan tiap event yang dibaca | `app/Services/Kafka/KafkaEventHandler.php` |
| Bahan halaman monitor | `app/Services/Kafka/KafkaMonitor.php`, `Admin/KafkaController.php`, `views/admin/kafka.blade.php` |
| Perintah | `sapto:kafka-setup`, `sapto:kafka-consume`, `sapto:kafka-flush-outbox` (`app/Console/Commands`) |
| Tabel | `kafka_event_log`, `kafka_outbox` (migrasi `2026_09_29_000000_create_kafka_tables`) |
| Service Docker | `zookeeper`, `kafka`, `kafka-rest`, `kafka-ui` di `docker-compose.yml` |
| Proses latar | `docker/entrypoint.sh` (setup topic lalu consumer, pola sama dengan queue worker) |

### Kenapa lewat REST Proxy, bukan langsung ke Kafka

Kafka memakai protokol biner sendiri. PHP butuh ekstensi `rdkafka` untuk
memakainya, dan image `composer:2` (Alpine, PHP 8.5) tidak punya ekstensi itu.
Membangun ulang image produksi hanya untuk ini berisiko. **Kafka REST Proxy**
(produk resmi Confluent, versi sama dengan broker) mengubah Kafka jadi HTTP
biasa, jadi Laravel cukup memakai `Http::`.

### Keputusan desain yang perlu diketahui

- **Kafka pelengkap, bukan syarat.** Login dan sinkronisasi tidak pernah
  menunggu atau gagal karena Kafka. Batas waktu kirim hanya 2 detik, dan
  `app` sengaja tidak `depends_on` ke Kafka di compose.
- **Outbox.** Event yang gagal terkirim disimpan di `kafka_outbox` dan dikirim
  ulang tiap menit (jeda 2, 4, 8, … maksimal 30 menit; menyerah setelah 60
  percobaan dan ditandai `dead`, tetap terlihat di halaman monitor).
- **At-least-once.** Kafka menjamin pesan tidak hilang, dengan konsekuensi satu
  pesan kadang terkirim dua kali. Consumer menyaring duplikat lewat `event_id`
  (unik di `kafka_event_log`), jadi arsip dan notifikasi tidak dobel. Karena itu
  angka "Pesan di Kafka" boleh sedikit lebih besar dari "Di arsip database".
- **1 partisi per topic.** Volumenya kecil dan satu partisi menjamin urutan
  global. Retensi 30 hari (sinkronisasi) dan 90 hari (audit).
- **Tes tidak pernah menyentuh Kafka sungguhan.** `phpunit.xml` memaksa
  `KAFKA_ENABLED=false` (tes berjalan di container yang sama dengan produksi).

## 6. Perintah operasional

```bash
# Semua dijalankan dari folder project di server

# Buat topic yang belum ada, samakan retensi (idempoten; otomatis saat container app naik)
docker compose exec app php artisan sapto:kafka-setup

# Kirim ulang event yang tertahan di outbox (dijadwalkan tiap menit)
docker compose exec app php artisan sapto:kafka-flush-outbox
docker compose exec app php artisan sapto:kafka-flush-outbox --dead     # termasuk yang sudah menyerah

# Jumlah pesan per topic (angka terakhir = total)
docker compose exec kafka kafka-run-class kafka.tools.GetOffsetShell \
    --bootstrap-server kafka:9092 --topic sapto.audit

# Lag consumer (LAG harus 0 atau kecil)
docker compose exec kafka kafka-consumer-groups --bootstrap-server kafka:9092 \
    --describe --group sapto-app

# Baca isi topic langsung
docker compose exec kafka kafka-console-consumer --bootstrap-server kafka:9092 \
    --topic sapto.audit --from-beginning --timeout-ms 5000

# Log
tail -f storage/logs/kafka-consumer.log
```

**Membaca ulang semua event dari awal** (mis. arsip database hilang). Reset offset
hanya bisa untuk group yang tidak aktif, jadi consumer harus mati dulu. Cara
paling sederhana: hentikan container `app` sebentar (aplikasi tidak bisa
diakses beberapa detik, lakukan di luar jam sibuk). Arsip aman diulang karena
consumer menyaring `event_id`.

```bash
docker compose stop app
docker compose exec kafka kafka-consumer-groups --bootstrap-server kafka:9092 \
    --group sapto-app --reset-offsets --to-earliest --all-topics --execute
docker compose up -d app
```

## 7. Kalau ada masalah

| Gejala | Kemungkinan penyebab | Tindakan |
| --- | --- | --- |
| Halaman monitor: "Tidak terhubung" | Container `kafka-rest` atau `kafka` mati | `docker compose ps`; `docker compose up -d`. Watchdog (cron 5 menit) juga menyalakan ulang otomatis. Event selama mati tidak hilang, ada di outbox. |
| Consumer "Tidak aktif" | Proses consumer mati, atau container app baru naik | Tunggu ±1 menit (setup topic dulu). Lihat `storage/logs/kafka-consumer.log`. |
| "N menunggu" di antrean kirim ulang | Kafka baru saja bermasalah | Dikirim ulang otomatis tiap menit. Bila `dead` > 0: pulihkan Kafka lalu `sapto:kafka-flush-outbox --dead`. |
| Login pertama setelah restart tampil dobel di Kafka | Broker menerima tetapi jawabannya terlambat (>2 detik), SAPTO mengirim ulang | Normal. Arsip hanya mencatat sekali. |
| `HTTP 406` dari REST Proxy | Header `Accept` salah untuk endpoint v2 | Ada di `KafkaRestProxy`; endpoint v2 butuh `application/vnd.kafka.v2+json`. |
| Server lambat saat Kafka naik | RAM sempit (banyak project compose) | REST Proxy dibatasi `-Xmx256m`. Cek `docker stats`. |

## 8. Pelajaran dari bug lama

Sebelum 2026-09-30, `KafkaProducerService` mengirim JSON lewat `fsockopen`
langsung ke port 9092. Itu **bukan** protokol Kafka: broker menganggap empat
byte pertama JSON (`{"to`) sebagai panjang pesan (2 miliar byte), menolak
koneksinya, dan tidak pernah menyimpan apa pun. Karena koneksi TCP-nya sempat
terbuka, kode itu tetap mencatat "Published" sebanyak 111 kali di log, padahal
offset topic tetap 0 dan tidak ada consumer.

Pelajaran yang sekarang diterapkan:

- Keberhasilan diukur dari **offset yang dikembalikan broker**, bukan dari
  koneksi terbuka. Producer baru menganggap sukses bila REST Proxy menjawab
  dengan partisi dan offset.
- Kegagalan dicatat jujur sebagai warning, tidak ada lagi log keberhasilan palsu.
- Halaman monitor menampilkan bukti nyata (jumlah pesan, lag consumer, event
  yang benar-benar terbaca), bukan kartu yang selalu bertuliskan "Aktif".
