<?php

namespace App\Services;

use App\Services\Concerns\ComputesGraduationYear;
use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Detail alumni tracer yang cocok dengan daftar lulusan berdasarkan NIM. */
class SaptoTracerLulusanRepository
{
    use ComputesGraduationYear;
    use ResolvesSaptoScope;

    public function __construct(private readonly SaptoProgramStudiRepository $client) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detail(array $years, string $filter, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        if ($scopeProdiIds === [] || ! Schema::hasTable('tracert_iku')) {
            return [];
        }

        $yearExpr = $this->graduationYearSql();
        $graduates = DB::table('list_mahasiswa_lulus_do')
            ->select([
                'nim',
                'nama_mahasiswa as nama',
                'nama_program_studi as program_studi',
                'angkatan',
                'id_prodi',
            ])
            ->selectRaw("{$yearExpr} as tahun_lulus")
            ->where('nama_jenis_keluar', 'Lulus')
            ->whereIn(DB::raw($yearExpr), array_map('strval', $years));

        if ($scopeProdiIds !== null) {
            $graduates->whereIn('id_prodi', $scopeProdiIds);
        }

        $graduates->distinct();

        $latestTracer = DB::table('tracert_iku')
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('nim')
            ->groupByRaw('TRIM(nim)');

        $query = DB::query()
            ->fromSub($graduates, 'l')
            ->join('tracert_iku as t', function ($join): void {
                $join->whereRaw('TRIM(t.nim) = TRIM(l.nim)');
            })
            ->joinSub($latestTracer, 'lt', fn ($join) => $join->on('t.id', '=', 'lt.id'))
            ->select([
                'l.nim',
                'l.nama',
                'l.program_studi',
                'l.angkatan',
                'l.tahun_lulus',
                't.tahun as tahun_tracer',
                't.nama_kegiatan_tracer',
                't.kategori',
                't.masa_tunggu_bulan',
                't.gaji_penghasilan',
                't.ump',
                't.jenis_tempat_kerja_studi',
                't.posisi',
                't.tanggal_respon',
                't.catatan',
            ]);

        if ($filter !== 'jumlah_terlacak') {
            $isD3 = strtoupper(trim((string) ($programStudi['jenjang'] ?? ''))) === 'D3';
            match ($filter) {
                'wt_1' => $query->where('t.masa_tunggu_bulan', '<', $isD3 ? 3 : 6),
                'wt_2' => $query->whereBetween('t.masa_tunggu_bulan', $isD3 ? [3, 6] : [6, 18]),
                'wt_3' => $query->where('t.masa_tunggu_bulan', '>', $isD3 ? 6 : 18),
                default => null,
            };
        }

        return $query
            ->orderBy('l.nama')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }
}
