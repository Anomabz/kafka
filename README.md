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

Migrasi **tidak** berjalan otomatis kalau `.env` disalin dari `.env.example`.
`docker-compose.yml` memang menyetel `RUN_MIGRATIONS: "${RUN_MIGRATIONS:-true}"`
(lihat `docker/entrypoint.sh`), tetapi `.env.example` — dan `.env` di server ini —
menyetel `RUN_MIGRATIONS=false`, dan nilai itulah yang dibaca compose. Jalankan
manual:

```bash
docker compose exec app php artisan migrate --seed --force
```

Karena kode ter-mount langsung ke container (lihat [Catatan
operasional](#catatan-operasional)) sedangkan skema baru berubah saat migrasi
dijalankan, perubahan yang membutuhkan tabel baru harus **dimigrasikan dulu,
baru kodenya** — migrasi seperti itu dibuat aditif supaya kode lama tetap jalan
selama jeda tersebut.

`KorprodiSeeder` mengisi tabel `penugasan` dengan roster korprodi awal dari
`database/data/korprodi.csv` (98 baris) — **hanya bila `penugasan` belum berisi
korprodi**, supaya seeding ulang tidak menghidupkan lagi akses yang sudah
dicabut admin. `ProgramStudiSeeder` sengaja dinonaktifkan — lihat penjelasannya
di `database/seeders/DatabaseSeeder.php`.

Entrypoint juga memanaskan cache tabel di latar belakang tiap kali container
start (`php artisan sapto:warm` lalu `sapto:warm --prodi`, dua tahap: scope
universitas dulu ~10 detik, baru ~97 scope prodi yang punya korprodi di
`penugasan` ~7 menit) — supaya
pengunjung pertama setelah cache habis tidak menanggung query berat (mis.
`dosen-pembimbing-ta` full scan ~400rb baris).

Aset frontend perlu dibangun sekali, jika tidak halaman depan akan error 500
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
Keycloak untuk client tersebut. Login memakai authorization code flow ke
halaman login bawaan Keycloak; SAPTO tidak punya form kredensial sendiri,
jadi **Direct Access Grants boleh dimatikan** di client. Client `app-sapto`
juga tidak punya service account, jadi Admin API Keycloak tidak tersedia —
nama pengguna tidak bisa dicari dari SSO, hanya dari data Feeder
(`list_mahasiswa`, `biodata_dosen`).

### Captcha (Cloudflare Turnstile)

```dotenv
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

Opsional — kosongkan **keduanya** untuk mematikan captcha (tombol Masuk
langsung aktif, tanpa widget). Aktif hanya bila kedua kunci terisi; satu kunci
saja sengaja dianggap mati (`SsoController::turnstileSiteKey()`), supaya site
key tanpa secret tidak mengunci semua orang keluar.

Widget dipasang di tombol **Masuk** welcome page, di **bawah** tombolnya —
bukan pengganti password, karena password tetap diketik di halaman Keycloak
(lihat [Autentikasi dan hak akses](#autentikasi-dan-hak-akses)). Fungsinya
menahan bot yang membanjiri `POST /auth/login`, bukan pertahanan kredensial.
`GET /auth/login` sengaja dialihkan ke `/` supaya URL itu tidak bisa dipakai
melompati captcha.

Site key Cloudflare **terikat ke hostname** yang didaftarkan (`Turnstile → Add
widget → Hostname`). Kunci yang didaftarkan untuk `sapto.unsri.ac.id` akan
gagal (kartu galat bawaan Cloudflare "Tidak dapat terhubung ke situs web")
kalau diuji lewat `localhost` atau IP host — tambahkan hostname uji terpisah
di dashboard Turnstile kalau butuh, atau uji dengan header `Host:
sapto.unsri.ac.id` yang dipetakan ke IP host ini.

Cloudflare yang tidak bisa dihubungi dari server (gangguan jaringan, bukan
captcha gagal) sengaja dianggap **lolos** — dicatat ke log sebagai peringatan
— supaya satu gangguan tidak mengunci semua orang keluar dari SAPTO.

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
sesi yang sah:

- halaman depan (`/`) menampilkan **welcome page di URL itu juga** — tidak ada
  `/login` di address bar;
- halaman lain dialihkan ke `/`, dan alamat asalnya dibuka lagi setelah login;
- permintaan API dibalas **401**.

`/login` tinggal pengalihan ke `/` untuk bookmark lama.

Alurnya:

1. Tombol **Masuk** di welcome page → `/auth/login` → halaman login resmi
   Keycloak. Password tidak pernah diketik di SAPTO.
2. Keycloak kembali ke `/auth/callback`. Akun yang **valid di SSO tetapi tidak
   terdaftar** — bukan admin dan tidak punya baris di `penugasan` — ditolak
   dengan modal "Akses ditolak". Penolakan dicatat ke log beserta NIP dan email
   yang benar-benar dikirim Keycloak.
3. Sesi SSO dipakai bersama aplikasi kampus lain: yang sudah login di aplikasi
   lain langsung masuk tanpa mengetik ulang.

**Single logout.** `EnsureSsoAuthenticated` menanyakan ulang sesi ke Keycloak
lewat refresh token, paling sering sekali per 60 detik per pengguna. Begitu
sesi SSO berakhir (logout di aplikasi mana pun), SAPTO ikut keluar dalam ≤60
detik. Keycloak yang tidak bisa dihubungi sengaja dianggap "masih login",
supaya satu gangguan jaringan tidak menendang semua pengguna. `/auth/logout`
mengakhiri sesi Keycloak juga.

Aplikasi lain yang logout **hanya lokal** (tidak memanggil endpoint logout
Keycloak) tidak mengakhiri sesi SSO — dalam kasus itu SAPTO memang benar tetap
login. Perbaikannya ada di aplikasi tersebut.

### Pencocokan identitas

Keycloak **tidak selalu mengirim `nomor_identitas`**. Karena itu admin dan
penugasan dicocokkan lewat **NIP/NIM atau email** — cukup salah satu yang
cocok. Isi keduanya bila tahu. Kalau sesi tidak membawa NIP maupun email, tidak
ada yang cocok (dijaga eksplisit; tanpa penjaga itu kueri akan cocok ke semua
baris).

### Peran dan cakupan data

Cakupan **selalu diturunkan dari identitas sesi di server** (lihat
`App\Support\SaptoUserContext`), tidak pernah dipercaya dari klien.

| Peran | Sumber | Role | Cakupan |
| --- | --- | --- | --- |
| Admin | `users.is_admin = true` | `universitas` | Semua data + halaman admin |
| Rektor | `penugasan`, peran `rektor` | `universitas` | Semua fakultas dan prodi |
| Dekan | `penugasan`, peran `dekan`, `lingkup` = `fakultas_id` | `fakultas` | Dikunci ke fakultasnya; boleh memilih prodi di dalamnya |
| Korprodi | `penugasan`, peran `korprodi`, `lingkup` = `kode_program_studi` | `prodi` | Hanya prodinya |

- Orang yang punya beberapa peran mendapat **peran terluas**.
- Hak akses dihitung ulang tiap request, jadi **mencabut penugasan berlaku
  seketika**. Status admin disimpan di sesi saat login, jadi pencabutan admin
  baru berlaku di login berikutnya.
- `fakultas_id` adalah slug nama fakultas dari `database/data/program_studi.csv`
  (mis. `fakultas-ilmu-komputer`, `pascasarjana`).
- Dekan yang meminta `program_studi_id` di luar fakultasnya ditolak **403** di
  `SaptoUserContext::resolveProgramStudiId()`. Penjaganya sengaja di situ, bukan
  di repository, karena tidak semua repository melewati `validateScope()`.
- **Pengecualian: Tabel 1 Kerjasama tidak disaring untuk peran apa pun.**
  `daftar_mou` tidak punya kolom unit, dan `daftar_pks.unit_pelaksana` kosong
  di ±92% baris (sumber SIKERMA). Menyaringnya sekarang membuat tabel kosong
  untuk hampir semua prodi; perbaikannya adalah mengisi unit pelaksana di sumber.

Kemungkinan balasan 403:

| Pesan | Sebab |
| --- | --- |
| `Akun ini belum terdaftar di SAPTO...` | Bukan admin dan tidak ada di `penugasan` |
| `Program studi akun ini tidak ditemukan pada data referensi SAPTO.` | Kode prodi korprodi tidak ada di mirror Feeder `program_studi` (**biasanya karena database belum disinkron**; di halaman *Kelola peran* baris seperti ini ditandai *Bermasalah*) |
| `Fakultas akun ini tidak ditemukan pada data referensi SAPTO.` | `fakultas_id` dekan tidak ada di data referensi |
| `Program studi ini berada di luar fakultas akun Anda.` | Dekan meminta prodi fakultas lain |

### Akun admin

Admin adalah **akun SSO biasa** yang ditandai `is_admin = true` di tabel `users`
(dicocokkan lewat `nomor_identitas` atau `email`). Tidak ada login lokal —
kolom `password` tidak dipakai dan boleh acak. Admin sengaja **tidak** bisa
dikelola dari UI, supaya satu salah hapus tidak bisa mengunci semua orang
keluar. Menambah admin:

```bash
docker compose exec app php artisan tinker --execute="App\Models\User::updateOrCreate(
    ['email' => 'nama@unsri.ac.id'],
    ['name' => 'Nama Lengkap', 'nomor_identitas' => '197801212008121003', 'is_admin' => true,
     'password' => bcrypt(Illuminate\Support\Str::random(64))]);"
```

### Halaman admin

Akun admin melihat link **Kelola role** di atas profilnya di sidebar.

| Route | Keterangan |
| --- | --- |
| `GET /admin/peran` | Kelola peran: daftar, filter per peran, cari, tambah akses (popup) |
| `POST /admin/peran` | Tambah penugasan (`peran`, `nip`/`email`, `lingkup_prodi`/`lingkup_fakultas`) |
| `DELETE /admin/peran/{id}` | Cabut penugasan |
| `GET /admin/login-as` | Login sebagai: pilih orang yang terdaftar |
| `POST /admin/login-as` | Mulai menyamar (`penugasan_id`), atau uji NIP tak terdaftar (`any=1`) |
| `POST /admin/stop-impersonating` | Kembali ke akun admin |

Semuanya dikunci `is_admin` (akun lain dibalas **403**). Setiap pemberian,
pencabutan, dan penyamaran dicatat ke `storage/logs/laravel.log` beserta admin
pelakunya.

## API

Seluruh endpoint bersifat `GET`, **membutuhkan sesi login** (cookie), dan
menerima parameter `ts` (tahun), `fakultas_id` (admin/rektor), dan
`program_studi_id` (admin/rektor/dekan). Parameter di luar cakupan akun
diabaikan atau ditolak — lihat [Peran dan cakupan data](#peran-dan-cakupan-data).
Tanpa sesi: **401**.

Paling mudah diuji lewat browser setelah login. Untuk `curl`, login lewat
browser, salin cookie `sapto-session` dari devtools, lalu:

```bash
curl -b "sapto-session=..." "http://localhost:8020/api/sapto/tables/ipk-lulusan/summary?ts=2026"
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
| `/api/sapto/tables/pkm-dtps/summary` | Tabel 3.b.3 — PkM DTPS |
| `/api/sapto/tables/publikasi-ilmiah-dtps/summary` | Tabel 3.b.4 — Publikasi Ilmiah DTPS |
| `/api/sapto/tables/pagelaran-dtps/summary` | Tabel 3.b.4 — Pagelaran/Pameran/Presentasi DTPS |
| `/api/sapto/tables/hki-paten-dtps/summary` | Tabel 3.b.7 — HKI (Paten, Paten Sederhana) |
| `/api/sapto/tables/hki-hak-cipta-dtps/summary` | Tabel 3.b.7 — HKI (Hak Cipta, Desain Produk Industri, dll.) |
| `/api/sapto/tables/teknologi-dtps/summary` | Tabel 3.b.7 — Teknologi Tepat Guna, Produk, Karya Seni, Rekayasa Sosial |
| `/api/sapto/tables/buku-dtps/summary` | Tabel 3.b.7 — Buku ber-ISBN, Book Chapter |
| `/api/sapto/tables/ipk-lulusan/summary` | Tabel 8.a |
| `/api/sapto/tables/prestasi-akademik/summary` | Tabel 8.b.1 |
| `/api/sapto/tables/prestasi-non-akademik/summary` | Tabel 8.b.2 |
| `/api/sapto/tables/masa-studi/summary` | Tabel 8.c |
| `/api/sapto/tables/waktu-tunggu/summary` | Tabel 8.d.1 — Waktu Tunggu Lulusan |
| `/api/sapto/tables/kesesuaian-bidang-kerja/summary` | Tabel 8.d.2 — Kesesuaian Bidang Kerja Lulusan |
| `/api/sapto/tables/tempat-kerja/summary` | Tabel 8.e.1 — Tempat Kerja Lulusan |
| `/api/sapto/tables/referensi-kepuasan-pengguna/summary` | Tabel Referensi 8.e.2 |
| `/sapto/mahasiswa-asing` | Tabel 2.b (jalur web, tanpa prefix `api`) |
| `/sapto/mahasiswa-asing/detail` | Tabel 2.b (rincian; untuk dekan tanpa prodi terpilih = semua prodi di fakultasnya) |

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
ditegakkan, dan seluruh request `POST` di test (mis. `/admin/peran`) gagal
dengan 419.

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

Container `app` me-mount root project ini ke `/var/www/html`, jadi **perubahan
kode langsung berlaku tanpa rebuild** — dengan jeda sampai 60 detik, karena
opcache memeriksa ulang file paling cepat tiap 60 detik (`revalidate_freq=60`).
Aset Vite tetap harus di-build ulang (`npm run build`) agar class Tailwind baru
ikut ter-compile; sampai itu terjadi, class baru diam-diam tidak berlaku.

`docker/watchdog.sh` (dipasang lewat cron tiap 5 menit) menyalakan ulang stack
kalau `/up` mati atau ada service compose yang tidak `running`, dan mencatat
ke `storage/logs/watchdog.log`.

`docker/backup-db.sh` (cron tiap hari jam 03:00) men-dump `sapto_db` ke
`BACKUPP_SAPTO_PROD/sapto_db_<timestamp>.sql.gz` dan membuang dump yang lebih
tua dari 14 hari. Ini satu-satunya sumber pemulihan untuk `users` (daftar
admin) dan `penugasan` (hak akses korprodi/dekan/rektor) — tabel-tabel lain
adalah mirror Feeder yang bisa disinkron ulang, tapi dua tabel itu data asli
SAPTO. Tabel `korprodi` lama masih ada sebagai cadangan pra-2026-09-11 dan tidak
dibaca kode lagi. Log di `storage/logs/backup.log`.

**Branch.** `main` adalah branch utama; kerja harian lewat `dev-prod`, `DEV`,
atau branch pribadi (`dev-<nama>`), digabung ke `main` lewat pull request.
Branch tidak aktif diarsipkan ke namespace `archive/`.
