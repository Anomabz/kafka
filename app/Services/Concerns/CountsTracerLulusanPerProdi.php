<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Aggregat data tracer yang dicocokkan ke lulusan melalui NIM. */
trait CountsTracerLulusanPerProdi
{
    /**
     * @param  array<int, int>  $years
     * @return array<int, array{jumlah_terlacak: int, wt_1: int, wt_2: int, wt_3: int}>
     */
    private function tracerCountsByGraduationYear(string $cacheNamespace, string $prodiId, array $years, bool $diplomaTiga = false): array
    {
        $empty = array_fill_keys($years, [
            'jumlah_terlacak' => 0,
            'wt_1' => 0,
            'wt_2' => 0,
            'wt_3' => 0,
        ]);

        if (! Schema::hasTable('tracert_iku')) {
            return $empty;
        }

        $cacheKey = "sapto:{$cacheNamespace}:tracer-nim:v1:".md5(json_encode([$prodiId, $years, $diplomaTiga]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($prodiId, $years, $empty, $diplomaTiga): array {
            $yearExpr = $this->graduationYearSql();
            $graduates = DB::table('list_mahasiswa_lulus_do')
                ->select('nim', 'id_prodi')
                ->selectRaw("{$yearExpr} as tahun_lulus")
                ->where('nama_jenis_keluar', 'Lulus')
                ->where('id_prodi', $prodiId)
                ->whereIn(DB::raw($yearExpr), array_map('strval', $years))
                ->groupBy('nim', 'id_prodi')
                ->groupByRaw($yearExpr);

            $wt1 = $diplomaTiga ? 't.masa_tunggu_bulan < 3' : 't.masa_tunggu_bulan < 6';
            $wt2 = $diplomaTiga
                ? 't.masa_tunggu_bulan >= 3 AND t.masa_tunggu_bulan <= 6'
                : 't.masa_tunggu_bulan >= 6 AND t.masa_tunggu_bulan <= 18';
            $wt3 = $diplomaTiga ? 't.masa_tunggu_bulan > 6' : 't.masa_tunggu_bulan > 18';
            $latestTracer = DB::table('tracert_iku')
                ->selectRaw('MAX(id) as id')
                ->whereNotNull('nim')
                ->groupByRaw('TRIM(nim)');

            $rows = DB::query()
                ->fromSub($graduates, 'l')
                ->join('tracert_iku as t', function ($join): void {
                    $join->whereRaw('TRIM(t.nim) = TRIM(l.nim)');
                })
                ->joinSub($latestTracer, 'lt', fn ($join) => $join->on('t.id', '=', 'lt.id'))
                ->select('l.tahun_lulus')
                ->selectRaw('COUNT(DISTINCT l.nim) as jumlah_terlacak')
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$wt1} THEN l.nim END) as wt_1")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$wt2} THEN l.nim END) as wt_2")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$wt3} THEN l.nim END) as wt_3")
                ->groupBy('l.tahun_lulus')
                ->get();

            $result = $empty;
            foreach ($rows as $row) {
                $year = (int) $row->tahun_lulus;
                if (isset($result[$year])) {
                    foreach (['jumlah_terlacak', 'wt_1', 'wt_2', 'wt_3'] as $key) {
                        $result[$year][$key] = (int) $row->{$key};
                    }
                }
            }

            return $result;
        });
    }
}
