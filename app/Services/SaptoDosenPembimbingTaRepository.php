<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaptoDosenPembimbingTaRepository
{
    private ?array $dataset = null;

    public function __construct(
        private readonly PangkalanDataApiClient $client,
    ) {}

    public function summary(int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $years = $this->years($ts);
        $programStudis = $this->client->programStudis();
        $programStudi = $programStudiId === null ? null : $this->programStudiById($programStudiId, $programStudis);
        $fakultas = $fakultasId === null ? null : $this->fakultasById($fakultasId, $programStudis);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(400, 'Program studi tidak ditemukan.');
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(400, 'Fakultas tidak ditemukan.');
        }

        if ($programStudi !== null && $fakultasId !== null && (string) $programStudi['fakultas_id'] !== $fakultasId) {
            throw new HttpException(400, 'Program studi tidak berada dalam fakultas yang dipilih.');
        }

        $dataset = $this->dataset();
        /** @var Collection<int, array<string, mixed>> $records */
        $records = $dataset['records'];
        $baseRecords = $records->whereIn('tahun', array_values($years));

        if ($fakultasId !== null) {
            $baseRecords = $baseRecords
                ->filter(fn (array $record): bool => (string) ($record['fakultas_id'] ?? '') === $fakultasId)
                ->values();
        }

        if ($programStudiId !== null) {
            $selectedDosenIds = $baseRecords
                ->filter(fn (array $record): bool => (string) $record['program_studi_id'] === $programStudiId)
                ->pluck('dosen_id')
                ->unique()
                ->values()
                ->all();

            $records = $baseRecords->whereIn('dosen_id', $selectedDosenIds);
        } else {
            $records = $baseRecords;
        }

        $rows = $records
            ->groupBy('dosen_id')
            ->map(function (Collection $dosenRecords) use ($years, $programStudiId): array {
                $first = $dosenRecords->first();
                $psDiakreditasi = $this->blankYearCounts();
                $psLainPt = $this->blankYearCounts();

                foreach ($dosenRecords as $record) {
                    $yearKey = array_search((int) $record['tahun'], $years, true);

                    if ($yearKey === false) {
                        continue;
                    }

                    if ($programStudiId === null || (string) $record['program_studi_id'] === $programStudiId) {
                        $psDiakreditasi[$yearKey]++;
                    } else {
                        $psLainPt[$yearKey]++;
                    }
                }

                $psDiakreditasi['rata_rata'] = $this->average($psDiakreditasi);
                $psLainPt['rata_rata'] = $this->average($psLainPt);

                return [
                    'dosen_id' => (string) $first['dosen_id'],
                    'nama_dosen' => $first['nama_dosen'],
                    'nidn' => $first['nidn'],
                    'nidk' => null,
                    'nip' => null,
                    'identifier' => $first['nidn'] ?: ($first['nuptk'] ?? null),
                    'ps_diakreditasi' => $psDiakreditasi,
                    'ps_lain_pt' => $psLainPt,
                    'rata_rata_semua_ps' => $this->average([
                        'ts_2' => $psDiakreditasi['ts_2'] + $psLainPt['ts_2'],
                        'ts_1' => $psDiakreditasi['ts_1'] + $psLainPt['ts_1'],
                        'ts' => $psDiakreditasi['ts'] + $psLainPt['ts'],
                    ]),
                ];
            })
            ->sortBy('nama_dosen')
            ->values()
            ->map(function (array $row, int $index): array {
                $row['no'] = $index + 1;

                return $row;
            })
            ->values();

        return [
            'table_code' => '3.a.2',
            'table_name' => 'Dosen Pembimbing Utama Tugas Akhir',
            'ts' => $ts,
            'year_columns' => $years,
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'rows' => $rows,
            'totals' => $this->totals($rows->all()),
            'source' => $this->sourcePayload($dataset['meta']),
        ];
    }

    public function detail(int $ts, ?string $fakultasId, ?string $programStudiId, string $dosenId, string $scope, string $yearKey): array
    {
        $years = $this->years($ts);
        $programStudis = $this->client->programStudis();
        $programStudi = $programStudiId === null ? null : $this->programStudiById($programStudiId, $programStudis);
        $fakultas = $fakultasId === null ? null : $this->fakultasById($fakultasId, $programStudis);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(400, 'Program studi tidak ditemukan.');
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(400, 'Fakultas tidak ditemukan.');
        }

        if ($programStudi !== null && $fakultasId !== null && (string) $programStudi['fakultas_id'] !== $fakultasId) {
            throw new HttpException(400, 'Program studi tidak berada dalam fakultas yang dipilih.');
        }

        $dataset = $this->dataset();
        /** @var Collection<int, array<string, mixed>> $records */
        $records = $dataset['records'];

        $items = $records
            ->filter(fn (array $record): bool => (string) $record['dosen_id'] === $dosenId)
            ->filter(fn (array $record): bool => (int) $record['tahun'] === $years[$yearKey]);

        if ($fakultasId !== null) {
            $items = $items->filter(fn (array $record): bool => (string) ($record['fakultas_id'] ?? '') === $fakultasId);
        }

        if ($programStudiId !== null) {
            $items = $scope === 'ps_diakreditasi'
                ? $items->filter(fn (array $record): bool => (string) $record['program_studi_id'] === $programStudiId)
                : $items->filter(fn (array $record): bool => (string) $record['program_studi_id'] !== $programStudiId);
        } elseif ($scope === 'ps_lain_pt') {
            $items = collect();
        }

        $items = $items
            ->sortBy('nama_mahasiswa')
            ->values();

        $first = $items->first()
            ?? $records->first(fn (array $record): bool => (string) $record['dosen_id'] === $dosenId);

        return [
            'dosen_id' => $dosenId,
            'nama_dosen' => $first['nama_dosen'] ?? null,
            'scope' => $scope,
            'year_key' => $yearKey,
            'year' => $years[$yearKey],
            'items' => $items->map(fn (array $item): array => [
                'mahasiswa_id' => $item['mahasiswa_id'],
                'nim' => $item['nim'],
                'nama_mahasiswa' => $item['nama_mahasiswa'],
                'fakultas_id' => $item['fakultas_id'],
                'fakultas' => $item['fakultas'],
                'program_studi_id' => $item['program_studi_id'],
                'program_studi' => $item['program_studi'],
                'judul_tugas_akhir' => $item['judul_tugas_akhir'],
                'jenis_tugas_akhir' => $item['jenis_tugas_akhir'],
                'tahun' => $item['tahun'],
                'status' => $item['status'],
                'sumber_data' => $item['sumber_data'],
            ])->values(),
            'source' => $this->sourcePayload($dataset['meta']),
        ];
    }

    private function dataset(): array
    {
        if ($this->dataset !== null) {
            return $this->dataset;
        }

        $cacheKey = 'sapto:dosen-pembimbing-ta:dataset:v4:'.md5(json_encode([
            'data_pages' => (int) config('sapto.api.data_max_pages', 10),
            'activity_lookup_limit' => (int) config('sapto.api.activity_lookup_limit', 600),
        ]));

        $dataset = Cache::remember($cacheKey, $this->cacheTtl(), function (): array {
            return $this->buildDataset();
        });

        $dataset['records'] = collect($dataset['records']);

        return $this->dataset = $dataset;
    }

    private function buildDataset(): array
    {
        $rows = $this->client->bimbingMahasiswaRows();
        $programIndex = $this->client->programStudis()->keyBy('id');
        $candidates = $rows->filter(fn (array $row): bool => $this->isPrimaryFinalAssignment($row))->values();
        $activityLookupLimit = (int) config('sapto.api.activity_lookup_limit', 600);
        $activityIds = $candidates
            ->pluck('id_aktivitas')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        $activities = $this->client->aktivitasMahasiswaForIds($activityIds->all(), $activityLookupLimit);

        $records = $candidates
            ->map(function (array $row) use ($activities, $programIndex): ?array {
                $activityId = (string) data_get($row, 'id_aktivitas', '');
                $activity = $activities->get($activityId);

                if ($activity === null) {
                    return null;
                }

                $year = $this->yearFromSemester((string) data_get($activity, 'id_semester', ''));
                $program = $programIndex->get((string) data_get($activity, 'id_prodi', ''));

                if ($year === null) {
                    return null;
                }

                return [
                    'mahasiswa_id' => (string) data_get($row, 'id_registrasi_mahasiswa', data_get($row, 'id_bimbing_mahasiswa', '')),
                    'nim' => (string) data_get($row, 'nim', ''),
                    'nama_mahasiswa' => (string) data_get($row, 'nama_mahasiswa', ''),
                    'dosen_id' => (string) data_get($row, 'id_dosen', ''),
                    'nama_dosen' => (string) data_get($row, 'nama_dosen', ''),
                    'nidn' => (string) data_get($row, 'nidn', ''),
                    'nuptk' => (string) data_get($row, 'nuptk', ''),
                    'fakultas_id' => (string) data_get($program, 'fakultas_id', ''),
                    'fakultas' => (string) data_get($program, 'fakultas', 'Belum Dipetakan'),
                    'program_studi_id' => (string) data_get($activity, 'id_prodi', ''),
                    'program_studi' => (string) data_get($activity, 'nama_prodi', ''),
                    'judul_tugas_akhir' => (string) data_get($activity, 'judul', ''),
                    'jenis_tugas_akhir' => (string) data_get($row, 'jenis_aktivitas', data_get($row, 'nama_kategori_kegiatan', '')),
                    'tahun' => $year,
                    'status' => (string) data_get($activity, 'status_sync', ''),
                    'sumber_data' => 'Pangkalan Data UNSRI API',
                ];
            })
            ->filter()
            ->values();

        return [
            'records' => $records->all(),
            'meta' => [
                'dosen_page_limit' => (int) config('sapto.api.data_max_pages', 10),
                'dosen_rows_scanned' => $rows->count(),
                'candidate_rows' => $candidates->count(),
                'activity_ids_available' => $activityIds->count(),
                'activity_lookup_limit' => $activityLookupLimit,
                'activity_lookups_used' => $activityIds->take(max(0, $activityLookupLimit))->count(),
                'matched_rows' => $records->count(),
            ],
        ];
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }

    private function programStudiById(string $id, ?Collection $programStudis = null): ?array
    {
        return ($programStudis ?? $this->client->programStudis())
            ->first(fn (array $programStudi): bool => (string) $programStudi['id'] === $id);
    }

    private function fakultasById(string $id, ?Collection $programStudis = null): ?array
    {
        $programStudi = ($programStudis ?? $this->client->programStudis())
            ->first(fn (array $item): bool => (string) ($item['fakultas_id'] ?? '') === $id);

        if ($programStudi === null) {
            return null;
        }

        return [
            'id' => $programStudi['fakultas_id'],
            'nama' => $programStudi['fakultas'],
            'label' => $programStudi['fakultas'],
        ];
    }

    private function isPrimaryFinalAssignment(array $row): bool
    {
        if ((string) data_get($row, 'pembimbing_ke') !== '1') {
            return false;
        }

        $type = str((string) data_get($row, 'jenis_aktivitas', data_get($row, 'nama_kategori_kegiatan', '')))->lower()->toString();

        return str_contains($type, 'tugas akhir')
            || str_contains($type, 'skripsi')
            || str_contains($type, 'tesis')
            || str_contains($type, 'disertasi');
    }

    private function yearFromSemester(string $semesterId): ?int
    {
        if (preg_match('/^[0-9]{4}/', $semesterId, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function sourcePayload(array $meta): array
    {
        return [
            'name' => 'Pangkalan Data UNSRI API',
            'dosen_page_limit' => $meta['dosen_page_limit'],
            'dosen_rows_scanned' => $meta['dosen_rows_scanned'],
            'candidate_rows' => $meta['candidate_rows'],
            'activity_ids_available' => $meta['activity_ids_available'],
            'activity_lookup_limit' => $meta['activity_lookup_limit'],
            'activity_lookups_used' => $meta['activity_lookups_used'],
            'matched_rows' => $meta['matched_rows'],
            'partial' => $meta['activity_ids_available'] > $meta['activity_lookup_limit'],
        ];
    }

    /**
     * @return array{ts_2: int, ts_1: int, ts: int}
     */
    private function years(int $ts): array
    {
        return [
            'ts_2' => $ts - 2,
            'ts_1' => $ts - 1,
            'ts' => $ts,
        ];
    }

    /**
     * @return array{ts_2: int, ts_1: int, ts: int}
     */
    private function blankYearCounts(): array
    {
        return [
            'ts_2' => 0,
            'ts_1' => 0,
            'ts' => 0,
        ];
    }

    /**
     * @param  array{ts_2: int|float, ts_1: int|float, ts: int|float}  $counts
     */
    private function average(array $counts): float
    {
        return round(($counts['ts_2'] + $counts['ts_1'] + $counts['ts']) / 3, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function totals(array $rows): array
    {
        $totals = [
            'ps_diakreditasi' => $this->blankYearCounts(),
            'ps_lain_pt' => $this->blankYearCounts(),
        ];

        foreach ($rows as $row) {
            foreach (['ts_2', 'ts_1', 'ts'] as $yearKey) {
                $totals['ps_diakreditasi'][$yearKey] += $row['ps_diakreditasi'][$yearKey];
                $totals['ps_lain_pt'][$yearKey] += $row['ps_lain_pt'][$yearKey];
            }
        }

        $totals['ps_diakreditasi']['rata_rata'] = $this->average($totals['ps_diakreditasi']);
        $totals['ps_lain_pt']['rata_rata'] = $this->average($totals['ps_lain_pt']);

        return $totals;
    }
}
