# Gravitee API Gateway di SAPTO

Dokumen ini menjelaskan jalur data SAPTO → Gravitee → PD UNSRI, kondisi
terakhir hasil pemeriksaan (2026-09-30), apa yang sudah diperbaiki, dan apa
yang masih harus dilakukan.

## 1. Gravitee itu apa, dan kenapa dipakai

Gravitee adalah **gerbang** di depan API PD UNSRI. Aplikasi tidak memanggil
server kampus langsung, tetapi lewat gerbang ini. Nilainya ada di apa yang
bisa dilakukan gerbang itu **tanpa mengubah kode SAPTO**:

| Kemampuan | Untuk apa di SAPTO |
| --- | --- |
| **API Key** | Hanya aplikasi yang punya kunci yang boleh lewat. Tidak ada lagi jalur terbuka. |
| **Rate limit** | Membatasi request per detik supaya server PD UNSRI tidak kebanjiran (mis. sinkronisasi yang salah jalan). |
| **Health check** | Gerbang memeriksa `/up` server kampus tiap 30 detik dan menandai backend sehat/mati. |
| **Analitik & log** | Berapa request, berapa yang gagal, berapa lama. Dilihat di Management UI. |
| **Satu pintu** | Bila alamat server kampus berubah, cukup ubah di Gravitee. |

## 2. Alur data

```
                 (1) token: langsung, TIDAK lewat Gravitee
SAPTO app ─────────────────────────────────────────────► api-pangkalan-data.unsri.ac.id
   │  (2) data: http://gateway:8082/sapto-api/...           ▲
   │      header X-Gravitee-Api-Key                         │ diteruskan bila kunci sah
   ▼                                                        │ dan belum melewati batas laju
Gravitee gateway ──── plan "SAPTO Web App" (API Key + rate limit)
   │
   └─ log & analitik ──► Elasticsearch ◄── dibaca Management API/UI
```

Kenapa token tidak lewat gateway: `api/get-token` menerima email dan password
sebagai **query string**. Lewat gateway, keduanya ikut tercatat di log
analitik. Karena itu permintaan token langsung ke server kampus
(`authBaseUrl()` di `PangkalanDataApiClient`), dan hanya permintaan **data**
yang lewat gerbang (`baseUrl()`).

Di kode: alamat gerbang dan kunci dibaca dari `config/services.php`
(`pangkalan_data.gateway_url`, `pangkalan_data.gateway_api_key`), yang
mengambil dari `.env`: `SAPTO_GATEWAY_URL` dan `SAPTO_GATEWAY_API_KEY`. Header
`X-Gravitee-Api-Key` dikirim otomatis bila kunci terisi.

## 3. Layanan dan port

Gravitee berjalan sebagai project Docker Compose **terpisah**
(`/home/sapto/gravitee`), bukan bagian project SAPTO. SAPTO menjangkaunya lewat
network Docker `frontend` dengan nama host `gateway`.

| Container | Port host | Fungsi |
| --- | --- | --- |
| `gio_apim_gateway` | 8077 | Gerbang yang dilewati data. SAPTO memakai `gateway:8082` lewat network Docker, bukan port host. |
| `gio_apim_management_api` | 8083 | API pengelolaan (dipakai `scripts/gravitee-setup.sh`) |
| `gio_apim_management_ui` | 8084 | Tampilan pengelolaan (untuk manusia) |
| `gio_apim_portal_ui` | 8066 | Portal pengembang |
| `gio_apim_mongodb` | – | Menyimpan definisi API, plan, aplikasi |
| `gio_apim_elasticsearch` | – | Menyimpan log & analitik |

## 4. Kondisi setelah perbaikan (2026-09-30)

| Temuan awal | Sekarang |
| --- | --- |
| Log & analitik hilang: Elasticsearch hanya heap 256 MB dan penuh, 65.387 kegagalan tulis dalam 7 hari | **Selesai.** Heap 768 MB, batas circuit breaker 85%, replika 0 (satu node), retensi otomatis 14 hari (ILM `gravitee-retensi-14-hari`). Cluster hijau, tidak ada kegagalan tulis baru. |
| Plan hanya `KEY_LESS`, 0 policy | **Selesai.** Plan `SAPTO Web App` (API Key + rate limit 30 request/detik) dipakai SAPTO. Plan tanpa kunci sudah **ditutup**: request tanpa kunci mendapat HTTP 401. |
| Alamat gerbang dibaca lewat `env()` langsung di kode | **Selesai.** Dipindah ke `config/services.php`, ada tesnya. |
| Timeout baca backend 10 detik (4 dari 9.709 request gagal HTTP 504) | **Selesai.** Dinaikkan ke 60 detik. |
| Akun admin Gravitee bawaan (`admin`/`admin`) | **Selesai.** Empat akun bawaan (`admin`, `user`, `api1`, `application1`) diganti password acak. Password `admin` ada di `/home/sapto/gravitee/.admin-password` (izin 600). |
| Port 8077/8083/8084/8066 terbuka ke seluruh jaringan | **Sengaja dibiarkan.** Management UI (8084) memanggil Management API (8083) dari **browser** pengguna, jadi menutup 8083 ke localhost memutus akses tim. Gerbang (8077) kini menuntut API Key. Bila ingin menutup, pasang reverse proxy dengan TLS lebih dulu. |

## 5. Cara kerja skrip pengelola

`scripts/gravitee-setup.sh` idempoten dan sudah dijalankan penuh. Dipertahankan
untuk pemulihan (mis. plan atau kunci hilang) atau lingkungan baru:

```bash
bash scripts/gravitee-setup.sh --check           # hanya membaca
bash scripts/gravitee-setup.sh                   # plan + aplikasi + langganan, deploy, simpan kunci ke .env
bash scripts/gravitee-setup.sh --close-keyless   # tutup plan tanpa kunci (setelah SAPTO terbukti sehat)
```

Password admin dibaca dari env `GRAVITEE_ADMIN_PASSWORD`, atau file
`/home/sapto/gravitee/.admin-password`, atau ditanya. Batas laju bisa diubah:
`SAPTO_GATEWAY_RATE_LIMIT=60 bash scripts/gravitee-setup.sh`.

Catatan teknis yang penting bila mengubah gerbang lewat API atau UI:

- Gateway butuh **beberapa detik sampai ±1 menit** menerapkan sebuah deploy.
  Setelah menutup plan, tunggu sampai request tanpa kunci benar-benar 401.
- **Label deploy maksimal 32 karakter**, kalau lebih ditolak (HTTP 400).
- Proses SAPTO yang berjalan lama (`queue:work`) membaca `.env` hanya saat naik.
  Setelah kunci berubah jalankan `docker compose exec app php artisan queue:restart`.

**Pemulihan bila SAPTO tidak bisa lewat gerbang:** di Management UI
(`http://<server>:8084`, akun `admin`) → APIs → *API Pangkalan Data* → Plans →
tab *Closed* → buka kembali plan "Default Plan" (tanpa kunci) → **Deploy API**.
Ini membuka jalur sementara sambil mencari sebab.

## 6. Yang masih perlu keputusan

- **Password admin ada di file di server.** Bagikan ke tim lewat kanal yang
  aman, jangan lewat chat biasa. Mengganti: buat hash bcrypt baru
  (`htpasswd -bnBC 10 "" 'SANDI_BARU' | tr -d ':\n' | sed 's/$2y/$2a/'`), ganti
  nilai `gravitee_security_providers_0_users_1_password` di
  `/home/sapto/gravitee/docker-compose.yml` (tiap `$` ditulis `$$`), lalu
  `docker compose up -d management_api` di folder itu.
- **Akses lewat HTTPS.** Management UI dan gerbang dilayani lewat HTTP biasa di
  jaringan kampus; password admin berjalan tanpa enkripsi di jaringan itu.

## 7. Operasional

```bash
# Kesehatan Elasticsearch (harus green)
docker exec gio_apim_elasticsearch curl -s localhost:9200/_cluster/health?pretty

# Ada kegagalan tulis log lagi? (harus 0)
docker logs gio_apim_gateway --since 10m 2>&1 | grep -c 'Unable to send bulk'

# Ukuran index Gravitee
docker exec gio_apim_elasticsearch curl -s 'localhost:9200/_cat/indices/gravitee-*?h=index,docs.count,store.size&s=index'

# Uji jalur data dari container SAPTO (harus bukan 000; 401 berarti kunci salah/tidak ada)
docker compose exec app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" -H "X-Gravitee-Api-Key: $(grep ^SAPTO_GATEWAY_API_KEY .env | cut -d= -f2)" http://gateway:8082/sapto-api/up'
```

Badge **Status API PD UNSRI** di SAPTO (`PdUnsriHealthCheck`) memeriksa jalur
ini tiap menit lewat gerbang, dan mencatat warning ke `storage/logs/laravel.log`
bila gagal. Timeout 5 detik yang sesekali muncul di sana biasanya berasal dari
server kampus yang lambat, bukan dari gerbang.
