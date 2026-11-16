<?php

namespace App\Http\Controllers\Api\Sapto;

use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Basis controller untuk tabel-tabel placeholder Kategori B.
 * Setiap subclass cukup override properti + metode abstrak.
 */
abstract class SaptoPlaceholderController extends SaptoTableController
{
    /** Kode tabel BAN-PT, mis. '3.b.5' */
    protected string $tableCode = '';

    /** Nama tabel untuk display */
    protected string $tableName = '';

    /** Unit count untuk metrics, mis. 'karya' */
    protected string $unit = 'entri';

    /** Notice yang menjelaskan mengapa data kosong */
    protected string $notice = 'Data belum tersedia.';

    /** Partial flag untuk source */
    protected bool $partial = true;

    public function summary(Request $request): JsonResponse
    {
        $ts = $this->validateTs($request);
        $context = SaptoUserContext::fromRequest($request);
        $fakultasId = $context->resolveFakultasId($request);
        $programStudiId = $context->resolveProgramStudiId($request);

        $ts ??= $this->defaultTs();
        [$fakultas, $programStudi] = $this->resolveScope($fakultasId, $programStudiId);

        return response()->json([
            'table_code' => $this->tableCode,
            'table_name' => $this->tableName,
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => $this->buildColumns(),
            'rows' => $this->buildRows($ts),
            'metrics' => $this->buildMetrics($ts),
            'notice' => $this->buildNotice($ts, $programStudi),
            'source' => [
                'name' => $this->sourceName(),
                'partial' => $this->partial,
            ],
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, align?: string, group?: string}>
     */
    abstract protected function buildColumns(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function buildRows(int $ts): array;

    /**
     * @return array<int, array{label: string, value: int|float|string}>
     */
    protected function buildMetrics(int $ts): array
    {
        return [
            ['label' => "Total {$this->unit}", 'value' => 0],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @param  int  $ts
     */
    protected function buildNotice(int $ts, ?array $programStudi): ?string
    {
        if ($programStudi === null) {
            return "Tabel {$this->tableCode} disusun per program studi. Pilih satu program studi pada filter di atas.";
        }

        return $this->notice;
    }

    protected function sourceName(): string
    {
        return 'Data belum tersedia';
    }

    protected function defaultTs(): int
    {
        return (int) now()->year;
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(): array
    {
        $key = "sapto:{$this->tableCode}:years:v1";

        return \Illuminate\Support\Facades\Cache::remember($key, 3600, function (): array {
            $rows = \Illuminate\Support\Facades\DB::select("
                SELECT DISTINCT CAST(SUBSTR(nama_tahun_ajaran, 1, 4) AS UNSIGNED) as thn
                FROM list_penugasan_dosen
                WHERE nama_tahun_ajaran IS NOT NULL
                  AND nama_tahun_ajaran <> ''
                ORDER BY thn DESC
                LIMIT 10
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    /**
     * @return array{0: array|null, 1: array|null}
     */
    private function resolveScope(?string $fakultId, ?string $programStudiId): array
    {
        $programStudis = app(\App\Services\SaptoProgramStudiRepository::class)->programStudis();
        $fakult = null;
        $programStudi = null;

        if ($programStudiId !== null) {
            $programStudi = $programStudis->first(
                fn (array $ps): bool => (string) $ps['id'] === $programStudiId
            );
        }

        if ($fakultId !== null && $fakult === null) {
            $ps = $programStudis->first(
                fn (array $ps): bool => (string) ($ps['fakultId'] ?? '') === $fakultId
            );
            if ($ps !== null) {
                $fakult = ['id' => $fakultId, 'nama' => $ps['fakult'] ?? ''];
            }
        }

        return [$fakult, $programStudi];
    }
}
