<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Roster id_sdm DTPS untuk tabel keluarga "Luaran/Publikasi" SISTER (3.b.4
 * dst) -- diekstrak dari SaptoPublikasiDtpsRepository supaya bisa dipakai
 * ulang oleh tabel lain di keluarga yang sama (mis. 3.b.7) tanpa duplikasi.
 *
 * BEDA dari CountsLulusanPerProdi/tabel 3.b.2-3.b.3 (yang pakai
 * list_penugasan_dosen.a_sp_homebase + id_prodi): keluarga publikasi ini
 * sumbernya sister_list_penugasan (pipeline sync SISTER terpisah), dan
 * prodi dicocokkan lewat unit_kerja = nama_program_studi (string), bukan
 * id_prodi -- karena sister_list_penugasan tidak punya kolom id_prodi.
 *
 * Menggunakan sister_list_penugasan dengan ROW_NUMBER untuk memilih
 * penugasan homebase terakhir tiap dosen yang masih aktif pada tahun
 * tertentu, disaring jenjang_pendidikan. Class pemakai wajib punya
 * `cacheTtl()` (lihat ResolvesSaptoScope).
 */
trait ResolvesPublikasiDtpsRoster
{
    /**
     * @param  array<int, string>|null  $scopeProdiIds
     * @param  array<int, string>  $jenjangValues
     * @return array<int, string>
     */
    private function publikasiDtpsIds(int $year, ?array $scopeProdiIds, array $jenjangValues): array
    {
        $cacheKey = 'sapto:publikasi-dtps:roster:v2:'.md5(json_encode([$year, $scopeProdiIds, $jenjangValues]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($year, $scopeProdiIds, $jenjangValues): array {
            $bindings = [];
            $unitFilter = '';

            if ($scopeProdiIds !== null && $scopeProdiIds !== []) {
                $namaProdis = DB::table('program_studi')
                    ->whereIn('id_prodi', $scopeProdiIds)
                    ->whereNotNull('nama_program_studi')
                    ->where('nama_program_studi', '<>', '')
                    ->pluck('nama_program_studi')
                    ->all();

                if ($namaProdis === []) {
                    return [];
                }

                $placeholders = implode(',', array_fill(0, count($namaProdis), '?'));
                $unitFilter = "AND pg.unit_kerja IN ({$placeholders})";
                $bindings = $namaProdis;
            } else {
                $unitFilter = "AND pg.unit_kerja IN (SELECT nama_program_studi FROM program_studi WHERE nama_program_studi IS NOT NULL AND nama_program_studi <> '')";
            }

            $jenjangPlaceholders = implode(',', array_fill(0, count($jenjangValues), '?'));
            $fullBindings = array_merge($bindings, [(string) $year, (string) $year], $jenjangValues);

            $rows = DB::select("
                SELECT id_sdm
                FROM (
                    SELECT
                        pg.id_sdm,
                        pg.jenjang_pendidikan,
                        pg.tanggal_mulai,
                        pg.tanggal_keluar,
                        ROW_NUMBER() OVER (
                            PARTITION BY pg.id_sdm
                            ORDER BY SUBSTR(pg.tanggal_mulai, 1, 10) DESC,
                                     (pg.apakah_penugasan_homebase IN ('1','Ya','ya','Y','true')) DESC,
                                     pg.id DESC
                        ) AS rn
                    FROM sister_list_penugasan pg
                    WHERE pg.perguruan_tinggi = 'Universitas Sriwijaya'
                      AND SUBSTR(pg.tanggal_mulai, 1, 4) BETWEEN '1900' AND '2100'
                      AND pg.apakah_penugasan_homebase IN ('1','Ya','ya','Y','true')
                      {$unitFilter}
                ) ranked
                WHERE rn = 1
                  AND SUBSTR(tanggal_mulai, 1, 4) <= ?
                  AND (tanggal_keluar IS NULL OR tanggal_keluar = '' OR SUBSTR(tanggal_keluar, 1, 4) >= ?)
                  AND jenjang_pendidikan IN ({$jenjangPlaceholders})
            ", $fullBindings);

            return array_values(array_unique(array_map(
                fn (object $row): string => (string) $row->id_sdm,
                $rows,
            )));
        });
    }
}
