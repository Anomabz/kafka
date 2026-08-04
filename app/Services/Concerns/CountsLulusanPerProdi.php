<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Jumlah lulusan per tahun lulus dari list_mahasiswa_lulus_do, untuk satu
 * prodi. Dipakai bersama oleh semua tabel 8.d/8.e per-prodi (waktu tunggu,
 * kesesuaian bidang kerja, dst) supaya baris "Jumlah Lulusan" konsisten di
 * semua tabel itu -- sama seperti tujuan ComputesGraduationYear (dipakai
 * berdampingan, class pemakai wajib punya trait itu juga).
 */
trait CountsLulusanPerProdi
{
    /**
     * @param  array<int, int>  $years
     * @return array<int, int> tahun => jumlah
     */
    private function lulusanCountsByYear(string $cacheNamespace, string $prodiId, array $years): array
    {
        $cacheKey = "sapto:{$cacheNamespace}:lulusan:v1:".md5(json_encode([$prodiId, $years]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($prodiId, $years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearExpr = $this->graduationYearSql();
            $placeholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $rows = DB::select("
                select {$yearExpr} as thn, count(*) as jumlah
                from list_mahasiswa_lulus_do
                where nama_jenis_keluar = 'Lulus'
                  and id_prodi = ?
                  and {$yearExpr} in ({$placeholders})
                group by {$yearExpr}
            ", array_merge([$prodiId], $yearStrings));

            $result = [];
            foreach ($rows as $row) {
                $result[(int) $row->thn] = (int) $row->jumlah;
            }

            return $result;
        });
    }

    /**
     * @return array<int, int>
     */
    private function lulusanAvailableYears(string $cacheNamespace): array
    {
        return Cache::remember("sapto:{$cacheNamespace}:years:v1", $this->cacheTtl(), function (): array {
            $yearExpr = $this->graduationYearSql();

            $rows = DB::select("
                select distinct {$yearExpr} as thn
                from list_mahasiswa_lulus_do
                where nama_jenis_keluar = 'Lulus'
                  and {$yearExpr} is not null
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }
}
