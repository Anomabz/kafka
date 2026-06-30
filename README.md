# SAPTO

Tahap pertama SAPTO read-only untuk menampilkan halaman `Tabel 3.a.2) Dosen Pembimbing Utama Tugas Akhir`.
Sumber data utama memakai API Pangkalan Data UNSRI.

## Jalankan dengan Docker

```bash
docker compose up --build
```

Aplikasi tersedia di `http://localhost:8001`.

## Konfigurasi API

Isi kredensial API di `.env`:

```dotenv
PANGKALAN_DATA_BASE_URL=https://pangkalan-data.unsri.ac.id
PANGKALAN_DATA_EMAIL=email_api
PANGKALAN_DATA_PASSWORD="password_api"
```

Endpoint `all-prodi` API mengembalikan data nasional, sehingga aplikasi mengambil profil PT UNSRI lalu memfilter daftar prodi dari halaman-halaman `all-prodi`. Tabel 3.a.2 memakai `bimbing-mahasiswa` dan lookup `list-aktivitas-mahasiswa` untuk prodi/tahun, jadi jumlah halaman dan lookup aktivitas dibatasi lewat env berikut:

```dotenv
SAPTO_API_PRODI_MAX_PAGES=100
SAPTO_API_DATA_MAX_PAGES=3
SAPTO_API_ACTIVITY_LOOKUP_LIMIT=120
```

Naikkan `SAPTO_API_DATA_MAX_PAGES` dan `SAPTO_API_ACTIVITY_LOOKUP_LIMIT` bila ingin cakupan data yang lebih luas. Respons API menyertakan field `source.partial` untuk menandai bila data yang dihitung masih terkena batas lookup.

## API Read-only

Daftar program studi:

```bash
curl -H "X-SAPTO-Role: universitas" http://localhost:8001/api/sapto/program-studi
```

Daftar fakultas:

```bash
curl -H "X-SAPTO-Role: universitas" http://localhost:8001/api/sapto/fakultas
```

Summary Tabel 3.a.2 untuk role universitas:

```bash
curl -H "X-SAPTO-Role: universitas" "http://localhost:8001/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026&program_studi_id=uuid_prodi"
```

Summary Tabel 3.a.2 untuk role fakultas:

```bash
curl \
  -H "X-SAPTO-Role: fakultas" \
  -H "X-SAPTO-Fakultas-Id: fakultas-ekonomi" \
  "http://localhost:8001/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026"
```

Summary Tabel 3.a.2 untuk role program studi:

```bash
curl \
  -H "X-SAPTO-Role: prodi" \
  -H "X-SAPTO-Program-Studi-Id: uuid_prodi_akun" \
  "http://localhost:8001/api/sapto/tables/dosen-pembimbing-ta/summary?ts=2026&program_studi_id=uuid_prodi_lain"
```

Untuk role program studi, backend mengabaikan `program_studi_id` dari query dan memakai konteks akun dari header `X-SAPTO-Program-Studi-Id`. Header ini adalah stand-in development sampai modul autentikasi/session sebenarnya tersedia.
Untuk role fakultas, backend membatasi data dari header `X-SAPTO-Fakultas-Id` dan mengabaikan pilihan fakultas dari query. Role universitas dapat memakai `fakultas_id` dan `program_studi_id` sebagai filter.
