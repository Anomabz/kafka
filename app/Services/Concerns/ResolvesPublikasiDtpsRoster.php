<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Roster id_sdm dosen untuk tabel keluarga "Luaran/Publikasi" SISTER (3.b.4
 * dst) -- dipakai ulang oleh tabel lain di keluarga yang sama (3.b.7 Paten,
 * Hak Cipta, Teknologi, Buku; 3.b.1 Rekognisi).
 *
 * Roster per tahun = (a) DTPS DITAMBAH (b) dosen pengajar (keputusan
 * pengguna, pola sama dengan 3.a.1 / EWMP / 3.b.2 / 3.b.3):
 *
 * (a) DTPS -- definisi SAMA dengan Tabel 3.a.1:
 *     - list_penugasan_dosen, baris homebase (a_sp_homebase = 1)
 *     - ikatan kerja tetap: A, B, F, H, I, N
 *     - TS = 4 digit awal nama_tahun_ajaran
 *     - id_prodi wajib terdaftar di program_studi (buang unit non-akademik)
 *     - prodi disaring menurut jenjang (program_studi.nama_jenjang_pendidikan)
 *
 * (b) Dosen yang MENGAJAR kelas prodi di scope pada tahun itu
 *     (dosen_pengajar_kelas_kuliah, semester ganjil/genap/pendek; prodi
 *     disaring jenjang yang sama). Ikut hanya bila $termasukPengajar = true
 *     (default). 3.b.1 Rekognisi memanggil dengan false karena butuh daftar
 *     DTPS murni untuk kolom tanda DTPS dan menambah pengajar sendiri.
 *
 * id_dosen feeder diperlakukan sama dengan id_sdm SISTER (terverifikasi
 * >99%).
 *
 * Class pemakai wajib punya `cacheTtl()` (lihat ResolvesSaptoScope).
 */
trait ResolvesPublikasiDtpsRoster
{
    /**
     * @param  int  $year  TS (tahun awal tahun ajaran, mis. 2025 untuk 2025/2026)
     * @param  array<int, string>|null  $scopeProdiIds  null/[] = semua prodi
     * @param  array<int, string>  $jenjangValues  mis. ['S1','S2','S3']; [] = tanpa filter jenjang
     * @param  bool  $termasukPengajar  true = DTPS + dosen pengajar; false = DTPS saja
     * @return array<int, string>  daftar id_sdm (= id_dosen) unik
     */
    private function publikasiDtpsIds(int $year, ?array $scopeProdiIds, array $jenjangValues, bool $termasukPengajar = true): array
    {
        $cacheKey = 'sapto:publikasi-dtps:roster:v4:'.md5(json_encode([$year, $scopeProdiIds, $jenjangValues, $termasukPengajar]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($year, $scopeProdiIds, $jenjangValues, $termasukPengajar): array {
            // Harus sama persis dengan filter ikatan kerja di Tabel 3.a.1.
            $ikatanKerjaTetap = ['A', 'B', 'F', 'H', 'I', 'N'];

            $prodiValid = function ($sub) use ($jenjangValues): void {
                $sub->select('id_prodi')
                    ->from('program_studi')
                    ->whereNotNull('nama_program_studi')
                    ->where('nama_program_studi', '<>', '');

                if ($jenjangValues !== []) {
                    $sub->whereIn('nama_jenjang_pendidikan', $jenjangValues);
                }
            };

            $query = DB::table('list_penugasan_dosen as lpd')
                ->where('lpd.a_sp_homebase', '1')
                ->whereIn('lpd.id_ikatan_kerja', $ikatanKerjaTetap)
                ->whereRaw('substr(lpd.nama_tahun_ajaran,1,4) = ?', [(string) $year])
                ->whereNotNull('lpd.id_dosen')
                ->where('lpd.id_dosen', '<>', '')
                ->whereIn('lpd.id_prodi', $prodiValid);

            if ($scopeProdiIds !== null && $scopeProdiIds !== []) {
                $query->whereIn('lpd.id_prodi', $scopeProdiIds);
            }

            $ids = $query->distinct()->pluck('lpd.id_dosen');

            if ($termasukPengajar) {
                $pengajar = DB::table('dosen_pengajar_kelas_kuliah as dp')
                    ->whereRaw('left(dp.id_semester,4) = ?', [(string) $year])
                    ->whereNotNull('dp.id_dosen')
                    ->where('dp.id_dosen', '<>', '')
                    ->whereIn('dp.id_prodi', $prodiValid);

                if ($scopeProdiIds !== null && $scopeProdiIds !== []) {
                    $pengajar->whereIn('dp.id_prodi', $scopeProdiIds);
                }

                $ids = $ids->merge($pengajar->distinct()->pluck('dp.id_dosen'));
            }

            return $ids
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();
        });
    }
}