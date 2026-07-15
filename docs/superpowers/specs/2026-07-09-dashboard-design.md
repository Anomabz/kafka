# Dashboard SAPTO — Design

## Tujuan

Landing page baru yang jadi tampilan default begitu login, menggantikan Tabel 3.a.2)
Dosen Pembimbing Utama Tugas Akhir sebagai halaman pertama yang dilihat user. Isinya
ringkasan angka kunci lintas 5 tabel BAN-PT yang sudah punya data nyata (dari 46 tabel
di sidebar, cuma 5 yang sudah dikembangkan — sisanya masih placeholder "belum
dikembangkan"). Dashboard TIDAK menambah data baru; murni menyusun ulang angka yang
sudah dihitung masing-masing repository tabel.

Eksplisit di luar scope v1:
- Status sinkronisasi/teknis (kapan terakhir sync dari Pangkalan Data, dsb).
- 41 tabel yang belum punya data.
- Perbandingan antar-fakultas dalam satu tampilan.

## Audiens & scoping

Sama seperti halaman tabel lain: dashboard ikut Role aktif dari topbar (Universitas /
Fakultas / Prodi) dan TS aktif dari filter-bar. Kalau role = Fakultas, semua angka di
dashboard otomatis discope ke fakultas itu saja — tidak ada mode "selalu universitas".
Pengecualian: kartu Mahasiswa Asing ikut Role (fakultas/prodi) tapi tidak ikut TS —
lihat catatan di tabel kartu di bawah.

## Isi — 5 kartu

Satu kartu per domain, tiap kartu link ke halaman tabel detailnya lewat
`activateTable(tableId)` yang sudah ada.

| # | Kartu | tableId (link) | Angka utama | Sparkline TS-2/TS-1/TS |
|---|---|---|---|---|
| 1 | Dosen Tetap PT (3.a.1) | `dosen-tetap-pt` | Total dosen tetap, Laki-laki, Perempuan | Tidak — `SaptoDosenTetapRepository::summary()` mengembalikan snapshot satu titik waktu (daftar dosen aktif saat ini), bukan seri per tahun. Jangan dipaksakan. |
| 2 | Dosen Pembimbing TA (3.a.2) | `dosen-pembimbing-ta` | Total dosen pembimbing, Total mhs bimbingan PS diakreditasi (TS), Total mhs bimbingan PS lain (TS) | Ya — `rows` per-dosen punya kolom TS-2/TS-1/TS untuk kedua kategori; dijumlah lintas dosen per tahun. |
| 3 | IPK Lulusan (8.a) | `ipk-lulusan` | IPK rata-rata (TS), Jumlah lulusan (TS) | Ya — `rows` sudah satu baris per tahun (TS-2/TS-1/TS) dengan `ipk_rata`, tinggal diambil langsung. |
| 4 | Masa Studi Lulusan (8.c) | `masa-studi` | Rata-rata masa studi (tahun), Total lulusan (kohort) | Ya — per angkatan (`tahun_masuk`), pakai 3 kohort terakhir sebagai proxy tren. Sumbu bukan TS tapi tahun masuk — beri label berbeda dari kartu lain (bukan "TS-2/TS-1/TS"). |
| 5 | Mahasiswa Asing (2.b) | `mahasiswa-asing` | Total mhs asing aktif, Penuh waktu, Paruh waktu | Ya — tiap baris (`data_mahasiswa_asing`) punya `asing_ft_ts2/ts1/ts` dan `asing_pt_ts2/ts1/ts` per prodi, dijumlah lintas prodi. **Catatan:** domain ini tidak ikut dropdown TS — sudah terdokumentasi sebagai keterbatasan sengaja (`mahasiswa-asing-filter.blade.php` memang tidak punya field TS), TS-nya selalu 2023/2024/2025 hardcoded di controller. Kartu ini akan selalu menampilkan window itu terlepas dari TS yang dipilih user — bukan bug baru, mengikuti perilaku yang sudah ada. |

Setiap kartu: skeleton loading pola `.is-loading` yang sudah ada di app.css. Kalau
domain itu gagal diambil (exception di satu repository), HANYA kartu itu yang
menampilkan pesan error kecil (`sp-alert-warn` versi ringkas) — kartu lain tetap
tampil normal. Dashboard tidak pernah gagal total karena satu tabel bermasalah.

## Navigasi & layout

- Section baru `#dashboard-page` di `sapto.blade.php`, mengikuti pola persis
  `#table-page` / `#mahasiswa-asing-page` / `#placeholder-page` yang sudah ada
  (show/hide lewat `activateTable()`/state, bukan route Laravel baru).
- Jadi section yang aktif secara default saat load pertama (ganti
  `currentTableId = "dosen-pembimbing-ta"` jadi `"dashboard"`).
- Sidebar (`components/sidebar.blade.php`): tambah satu link "Dashboard" dengan ikon
  home, ditaruh di atas `@foreach ($groups ...)`, di luar grouping "Tabel N" karena
  secara semantik bukan salah satu dari 46 tabel BAN-PT.
- Filter: reuse `@include('components.filter-bar')` yang sama (TS/Fakultas/Program
  Studi/Unit Aktif) di atas grid kartu.
- Grid kartu: 1 kolom di mobile (`<640px`, pelajaran dari perbaikan filter-bar
  sebelumnya — konten lebar-variabel jangan dipaksa kolom sempit), 2 kolom di
  tablet/desktop.

## Arsitektur data

Satu endpoint baru, bukan 5 fetch terpisah dari frontend:

```
GET /api/sapto/dashboard/summary
```

Middleware sama dengan endpoint sapto lain: `['web', 'sso.auth']`, prefix `sapto`.
Query/header sama seperti tabel lain (`ts`, `X-SAPTO-Role`,
`X-SAPTO-Fakultas-Id`, `X-SAPTO-Program-Studi-Id`).

- `App\Http\Controllers\Api\Sapto\DashboardController::summary()` — thin, sama
  pola seperti `DosenTetapController` dkk: resolve TS + `SaptoUserContext`, delegasi
  ke repository, `response()->json(...)`.
- `App\Services\SaptoDashboardRepository::summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array`
  — memanggil `SaptoDosenTetapRepository`, `SaptoDosenPembimbingTaRepository`
  (dikonfirmasi, dipakai `DosenPembimbingTaController`), `SaptoIpkLulusanRepository`,
  `SaptoMasaStudiRepository` — keempatnya sudah pola Repository yang konsisten
  (terima `$ts`, `$fakultasId`, `$programStudiId`). Setiap panggilan dibungkus
  try/catch sendiri-sendiri supaya satu domain gagal tidak menjatuhkan seluruh
  response — domain yang gagal dikembalikan dengan `"error": true` di kartunya,
  bukan HTTP 500 total.

  **Mahasiswa Asing beda pola** — `MahasiswaController::data_mahasiswa_asing()`
  saat ini query SQL mentah langsung di controller (tidak ada repository, tidak
  pakai `SaptoUserContext`, TS hardcoded, tidak baca `$ts` dari request sama
  sekali). Supaya dashboard tidak menduplikasi ~40 baris SQL + logic pemetaan
  fakultas dari CSV itu ke tempat kedua (dua sumber kebenaran yang bisa
  ngedrift), bagian dari pekerjaan ini adalah **ekstraksi kecil**: pindahkan
  query itu ke `App\Services\SaptoMahasiswaAsingRepository::summary(?string
  $fakultasId, ?string $programStudiId)` (tanpa parameter `$ts` — memang tidak
  didukung backend saat ini), lalu `MahasiswaController::data_mahasiswa_asing()`
  tinggal delegasi ke situ dan kembalikan response JSON persis seperti sekarang
  (tidak ada perubahan perilaku/response shape untuk endpoint yang sudah ada).
  Filter fakultas/prodi untuk repository baru ini meniru logic yang sekarang
  jalan di klien (`maFilter`/`maVisibleRows()` di `sapto.blade.php`) — bukan
  logic baru, cuma dipindah ke PHP supaya kartu dashboard bisa discope role
  tanpa fetch-semua-lalu-filter-di-JS.

Bentuk response (contoh):

```json
{
  "ts": 2025,
  "cards": [
    {
      "id": "dosen-tetap-pt",
      "table_code": "3.a.1",
      "metrics": [{"label": "Total dosen tetap", "value": 812}, ...],
      "trend": null
    },
    {
      "id": "ipk-lulusan",
      "table_code": "8.a",
      "metrics": [{"label": "IPK rata-rata (TS)", "value": 3.42}, ...],
      "trend": {"labels": ["2023", "2024", "2025"], "values": [3.31, 3.38, 3.42]}
    },
    ...
  ]
}
```

Sparkline dirender client-side sebagai inline SVG polyline kecil (3 titik data,
tanpa library chart baru — YAGNI untuk kebutuhan sekecil ini).

Kode JS dashboard (fetch + render 5 kartu) ditambahkan sebagai fungsi baru di
`<script>` yang sama di `sapto.blade.php`, mengikuti pola `renderSummary()` /
`renderMaSummary()` yang sudah ada — bukan file JS terpisah, supaya konsisten dengan
struktur satu-script yang sudah dipakai untuk semua section lain.

## Out of scope (v1)

- Status sync/teknis (`sync.py`, `SyncMahasiswa`).
- 41 tabel yang belum dikembangkan.
- Perbandingan antar-fakultas dalam satu tampilan (dashboard tetap single-scope
  sesuai role aktif, seperti halaman tabel lain).
- Sparkline untuk kartu yang datanya memang snapshot satu titik waktu (Dosen Tetap PT).
- Membuat kartu Mahasiswa Asing ikut dropdown TS — itu perubahan perilaku backend
  yang sudah ada (`data_mahasiswa_asing` hardcode 2023/2024/2025), di luar scope
  pekerjaan ini.
