# SAPTO

Aplikasi pelaporan Tridharma Universitas Sriwijaya. Menyajikan tabel-tabel
akreditasi BAN-PT (Tabel 1 s.d. 8.f), dibaca dari data Feeder/PDDikti yang sudah
disinkronkan ke database. Tab default saat aplikasi dibuka adalah Tabel 1
Kerjasama Tridharma - Pendidikan.

Aplikasi bersifat **read-only**: tidak ada modul input data. Seluruh angka
berasal dari mirror Feeder dan API Pangkalan Data UNSRI.

## Daftar isi

- [Arsitektur dan sumber data](#arsitektur-dan-sumber-data)
- [Menjalankan aplikasi](#menjalankan-aplikasi)
- [Konfigurasi](#konfigurasi)
- [Autentikasi dan hak akses](#autentikasi-dan-hak-akses)
- [API](#api)
- [Testing](#testing)
- [Catatan operasional](#catatan-operasional)

## Arsitektur dan sumber data

| Komponen | Keterangan |
| --- | --- |
| Laravel 13 (PHP 8.5 di image Docker) | Backend dan render Blade |
| Vite + Tailwind 4 | Aset frontend |
| MySQL 8 | Mirror data Feeder, dalam compose project ini sendiri |
| Keycloak | SSO UNSRI (`portal-sso.unsri.ac.id`) |

Data referensi (program studi, fakultas, dosen, mahasiswa) dibaca dari
**database mirror Feeder**, bukan dari API secara langsung — lihat
`SaptoProgramStudiRepository::programStudis()` yang memakai `DB::select` dengan
cache. API Pangkalan Data (`PangkalanDataApiClient`) dipakai untuk data yang
belum tersedia di mirror.

Konsekuensinya: **tanpa data hasil sync Feeder di tabel-tabel mirror (mis.
`list_mahasiswa_lulus_do`, `program_studi`, `list_dosen`, dst.), tabel-tabel
BAN-PT akan tampil kosong atau 0**, meski migrasi sudah jalan dan aplikasinya
sendiri berjalan normal. Migrasi cuma membuat skema; isinya baru terisi
lewat proses sync terpisah (`sync.py` untuk memindahkan `database/database.sqlite`
ke MySQL, atau `php artisan sync:mahasiswa` untuk tabel `list_mahasiswa` saja).

## Menjalankan aplikasi

```bash
docker compose up -d --build
```

Aplikasi tersedia di `http://localhost:8020`, phpMyAdmin di
`http://localhost:8086` (server `db`, user `root`, password `rootpassword`).
Db MySQL sendiri **tidak** publish port ke host (lihat komentar di
`docker-compose.yml`) — kalau butuh akses langsung, lewat phpMyAdmin atau
`docker compose exec db mysql -uroot -prootpassword sapto_db`.

Menjalankan di luar Docker praktis tidak disarankan: `composer.json` menyatakan
`"php": "^8.3"`, sedangkan image Docker memakai **PHP 8.5** — jalankan di PHP
versi lain berisiko `artisan` gagal di pemeriksaan platform atau perilaku beda
dari yang sebenarnya dipakai di server.

Migrasi **otomatis berjalan saat container start** karena `docker-compose.yml`
menyetel `RUN_MIGRATIONS: "${RUN_MIGRATIONS:-true}"` (lihat
`docker/entrypoint.sh`) — beda dari `.env.example` yang defaultnya `false`
untuk kasus menjalankan `artisan` di luar compose. Set `RUN_MIGRATIONS=false`
lewat `.env` di root project ini kalau tidak ingin migrasi otomatis (mis. saat
database sudah dikelola manual).

```bash
docker compose exec app php artisan migrate --seed --force   # kalau mau jalan manual
```

Seeder mengisi tabel `korprodi` dari `database/data/korprodi.csv` (98 baris).
`ProgramStudiSeeder` sengaja dinonaktifkan — lihat penjelasannya di
`database/seeders/DatabaseSeeder.php`.

Entrypoint juga memanaskan cache tabel di latar belakang tiap kali container
start (`php artisan sapto:warm` lalu `sapto:warm --prodi`, dua tahap: scope
universitas dulu ~10 detik, baru ~97 scope korprodi ~7 menit) — supaya
pengunjung pertama setelah cache habis tidak menanggung query berat (mis.
`dosen-pembimbing-ta` full scan ~400rb baris).

Aset frontend perlu dibangun sekali, jika tidak `/login` akan error 500
(`Vite manifest not found`):

```bash
npm install
npm run build     # atau: npm run dev (HMR)
```

## Konfigurasi

Salin `.env.example` menjadi `.env`, lalu isi bagian berikut.

### Database

`docker-compose.yml` sudah menyuntikkan `DB_*` sebagai environment variable
container, sehingga menimpa nilai di `.env`. Perlu diketahui: `php artisan
serve` membaca ulang `.env` untuk proses anaknya, jadi **pastikan `.env` tidak
menyatakan koneksi yang berbeda dari `docker-compose.yml`** — perbedaan itu
membuat perintah `artisan` dan permintaan web memakai database yang berlainan.

### SSO (Keycloak)

```dotenv
SSO_BASE_URL=https://portal-sso.unsri.ac.id
SSO_REALM=unsri
SSO_CLIENT_ID=app-sapto
SSO_CLIENT_SECRET=rahasia_client
SSO_REDIRECT_URI=http://localhost:8020/auth/callback
```

`SSO_REDIRECT_URI` harus sama persis dengan redirect URI yang terdaftar di
Keycloak untuk client tersebut. Login form sendiri memakai grant
`password` (Direct Access Grants harus aktif di client).

### API Pangkalan Data

```dotenv
PANGKALAN_DATA_BASE_URL=https://pangkalan-data.unsri.ac.id
PANGKALAN_DATA_EMAIL=email_api
PANGKALAN_DATA_PASSWORD="password_api"
PANGKALAN_DATA_TIMEOUT=30
PANGKALAN_DATA_CACHE_TTL=3600
```

Endpoint `all-prodi` mengembalikan data nasional, sehingga aplikasi mengambil
profil PT UNSRI lalu memfilter daftar prodi dari halaman-halaman `all-prodi`.
Batas penelusuran diatur lewat:

```dotenv
SAPTO_PERGURUAN_TINGGI_KEYWORD="Universitas Sriwijaya"
SAPTO_API_PRODI_MAX_PAGES=100
SAPTO_API_DATA_MAX_PAGES=10
SAPTO_API_ACTIVITY_LOOKUP_LIMIT=600
SAPTO_API_ACTIVITY_POOL_SIZE=20
```

Naikkan `SAPTO_API_DATA_MAX_PAGES` dan `SAPTO_API_ACTIVITY_LOOKUP_LIMIT` bila
ingin cakupan lebih luas. Respons menyertakan `source.partial` untuk menandai
data yang masih terkena batas lookup atau butuh sumber tracer study alumni
yang belum ada di sistem (lihat tabel 8.d/8.e di bawah).

## Autentikasi dan hak akses

Seluruh halaman dan endpoint API berada di balik middleware `sso.auth`. Tanpa
sesi SSO yang sah: permintaan web dialihkan ke `/login`, permintaan API dibalas
**401**.

Alurnya:

1. `/login` — form milik SAPTO sendiri. Kredensial divalidasi ke Keycloak
   server-side (`grant_type=password`).
2. Bila Keycloak tidak bisa memutuskan (direct grant nonaktif, MFA, required
   action), pengguna diarahkan ke halaman SSO resmi lewat `/auth/login`.
3. Akun yang **valid di SSO tetapi bukan korprodi terdaftar ditolak** —
   NIP (`nomor_identitas`) dicocokkan ke tabel `korprodi`.

### Cakupan data

Cakupan **selalu diturunkan dari identitas sesi, tidak pernah dipercaya dari
klien** (lihat `App\Support\SaptoUserContext`). Ada dua level:

| Role | Siapa | Cakupan |
| --- | --- | --- |
| `prodi` | Korprodi (NIP cocok di tabel `korprodi`) | Hanya prodi yang dipegang |
| `universitas` | Akun admin (`users.is_admin = true`) | Seluruh fakultas/prodi |

Role `fakultas` belum dipakai. NIP korprodi yang tidak cocok **ditolak
sepenuhnya**, bukan diturunkan ke akses penuh.

Kemungkinan balasan 403:

| Pesan | Sebab |
| --- | --- |
| `Akun SSO tidak memiliki nomor identitas...` | Klaim `nomor_identitas` kosong |
| `Akun ini belum terdaftar sebagai koordinator program studi di SAPTO.` | NIP tidak ada di tabel `korprodi` |
| `Program studi akun ini tidak ditemukan pada data referensi SAPTO.` | Prodi tidak ada di mirror Feeder (**biasanya karena database belum disinkron**) |

### Akun admin dan impersonation

Akun admin/developer SAPTO **tidak ada di Keycloak**. Login lewat form `/login`
yang sama, tetapi kredensialnya dicek secara lokal: `username` dicocokkan ke
`users.nomor_identitas` (dengan `is_admin = true`) dan password diverifikasi
terhadap hash bcrypt di `users.password` — lihat
`SsoController::attemptLocalAdminLogin()`.

Untuk menguji tampilan sebagai korprodi tertentu, admin memakai fitur "login
sebagai":

| Route | Keterangan |
| --- | --- |
| `GET /admin/login-as` | Daftar korprodi yang bisa dipakai |
| `POST /admin/login-as` | Mulai menyamar sebagai satu NIP |
| `POST /admin/stop-impersonating` | Kembali ke akun admin |

Semuanya berada di balik `sso.auth` dan dikunci `is_admin`; akun non-admin
dibalas **403**.

## API

Seluruh endpoint bersifat `GET`, **membutuhkan sesi login** (cookie), dan
menerima parameter `ts` (tahun) serta `program_studi_id`. Tanpa sesi: **401**.

Paling mudah diuji lewat browser setelah login. Untuk `curl`, ambil cookie sesi
dari form `/login` (butuh `_token` CSRF), lalu:

```bash
curl -b cookies.txt "http://localhost:8020/api/sapto/tables/ipk-lulusan/summary?ts=2026"
```

| Endpoint | Keterangan |
| --- | --- |
| `/api/sapto/dashboard/summary` | Ringkasan + `available_years` (halaman dashboard sudah dihapus, endpoint masih ada) |
| `/api/sapto/program-studi` | Daftar program studi |
| `/api/sapto/fakultas` | Daftar fakultas |
| `/api/sapto/tables/kerjasama-pendidikan/summary` | Tabel 1 — Pendidikan |
| `/api/sapto/tables/kerjasama-penelitian/summary` | Tabel 1 — Penelitian |
| `/api/sapto/tables/kerjasama-pkm/summary` | Tabel 1 — PkM |
| `/api/sapto/tables/dosen-tetap-pt/summary` | Tabel 3.a.1 |
| `/api/sapto/tables/dosen-pembimbing-ta/summary` | Tabel 3.a.2 |
| `/api/sapto/tables/dosen-pembimbing-ta/detail` | Tabel 3.a.2 (rincian) |
| `/api/sapto/tables/dosen-tidak-tetap/summary` | Tabel 3.a.4 |
| `/api/sapto/tables/penelitian-dtps/summary` | Tabel 3.b.2 |
| `/api/sapto/tables/publikasi-ilmiah-dtps/summary` | Tabel 3.b.4 — Publikasi Ilmiah DTPS |
| `/api/sapto/tables/pagelaran-dtps/summary` | Tabel 3.b.4 — Pagelaran/Pameran/Presentasi DTPS |
| `/api/sapto/tables/ipk-lulusan/summary` | Tabel 8.a |
| `/api/sapto/tables/prestasi-akademik/summary` | Tabel 8.b.1 |
| `/api/sapto/tables/prestasi-non-akademik/summary` | Tabel 8.b.2 |
| `/api/sapto/tables/masa-studi/summary` | Tabel 8.c |
| `/api/sapto/tables/waktu-tunggu/summary` | Tabel 8.d.1 — Waktu Tunggu Lulusan |
| `/api/sapto/tables/kesesuaian-bidang-kerja/summary` | Tabel 8.d.2 — Kesesuaian Bidang Kerja Lulusan |
| `/api/sapto/tables/tempat-kerja/summary` | Tabel 8.e.1 — Tempat Kerja Lulusan |
| `/api/sapto/tables/referensi-kepuasan-pengguna/summary` | Tabel Referensi 8.e.2 |
| `/sapto/mahasiswa-asing` | Tabel 2.b (jalur web, tanpa prefix `api`) |
| `/sapto/mahasiswa-asing/detail` | Tabel 2.b (rincian) |

Sidebar menampilkan seluruh 45 tabel BAN-PT; endpoint di atas adalah yang sudah
terimplementasi. Sisanya masih placeholder.

Tabel 8.d.1, 8.d.2, 8.e.1, dan Referensi 8.e.2 di atas cuma mengisi baris
"Jumlah Lulusan" secara otomatis (dari `list_mahasiswa_lulus_do`); kolom yang
butuh hasil survei tracer study alumni (waktu tunggu, kesesuaian bidang kerja,
tingkat/ukuran tempat kerja, tanggapan kepuasan pengguna) selalu bernilai 0
karena sumber datanya belum tersinkron ke sistem ini — respons API menandainya
lewat `source.partial: true`.

## Testing

```bash
docker compose exec app php artisan test
```

Test suite berjalan di atas **SQLite in-memory** dan tidak pernah menyentuh
database MySQL. Isolasi ini dijaga di dua tempat:

- `tests/TestCase.php` — memaksa koneksi sqlite `:memory:` **di dalam kode**,
  karena `docker-compose.yml` menyuntikkan `DB_CONNECTION=mysql` sebagai
  environment variable OS yang mengalahkan `<env>` di `phpunit.xml`. Guard ini
  ada setelah insiden 2026-07-02, ketika `RefreshDatabase` sempat menjalankan
  `migrate:fresh` pada database live. **Jangan dihapus.**
- `tests/Feature/DatabaseIsolationGuardTest.php` — menegaskan koneksi aktif
  benar-benar sqlite, bukan mysql.

Perlu diketahui: `<env>` di `phpunit.xml` hanya menulis `$_ENV`/`putenv()`,
sedangkan Laravel membaca `$_SERVER` lebih dulu. Karena itu `APP_ENV` memakai
`<server>` — tanpa itu, Laravel tidak sadar sedang testing, CSRF tetap
ditegakkan, dan seluruh `POST /login` gagal dengan 419.

## Catatan operasional

**Server ini menjalankan belasan project Docker Compose sekaligus**, banyak
`restart: always`. Port host yang bentrok baru ketahuan saat reboot: yang
kalah balapan mati dengan exit 255 dan Docker tidak mencoba lagi — ini pernah
menjatuhkan seluruh stack ini selama 25 jam (2026-07-26). Aturannya:

- Jangan publish port host kecuali benar-benar diakses dari luar (`db` di
  compose ini sengaja tidak publish port).
- Sebelum menambah `ports:`, pastikan portnya bebas:
  `ss -ltn | awk '{print $4}' | grep -oE '[0-9]+$' | sort -un`
- Jangan pakai `container_name:` — mematikan namespacing compose dan bisa
  bentrok dengan project lain yang punya nama service sama.
- Port project ini: **8020** (app), **8086** (phpMyAdmin). Db tanpa port host.

`docker/watchdog.sh` (dipasang lewat cron tiap 5 menit) menyalakan ulang stack
kalau `/up` mati atau ada service compose yang tidak `running`, dan mencatat
ke `storage/logs/watchdog.log`.

`docker/backup-db.sh` (cron tiap hari jam 03:00) men-dump `sapto_db` ke
`BACKUPP_SAPTO_PROD/sapto_db_<timestamp>.sql.gz` dan membuang dump yang lebih
tua dari 14 hari. Ini satu-satunya sumber pemulihan untuk `users` dan
`korprodi` — tabel-tabel lain adalah mirror Feeder yang bisa disinkron ulang,
tapi dua tabel itu data asli SAPTO. Log di `storage/logs/backup.log`.

**Branch.** `main` adalah branch utama; kerja harian lewat `dev-prod`, `DEV`,
atau branch pribadi (`dev-<nama>`), digabung ke `main` lewat pull request.
Branch tidak aktif diarsipkan ke namespace `archive/`.
