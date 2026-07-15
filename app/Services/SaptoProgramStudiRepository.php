<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sumber fakultas/program studi dari database lokal (hasil sinkronisasi
 * Pangkalan Data), dipakai sebagai pengganti PangkalanDataApiClient::
 * programStudis()/faculties() karena kredensial API belum dikonfigurasi
 * di environment ini. Pemetaan fakultas memakai CSV yang sama dengan
 * MahasiswaController::facultyByProdiCode().
 */
class SaptoProgramStudiRepository
{
    public function programStudis(): Collection
    {
        return collect(Cache::remember($this->cacheKey('program-studi'), $this->cacheTtl(), function (): array {
            $facultyMap = $this->facultyByProdiCode();

            return collect(DB::select("
                select id_prodi, kode_program_studi, nama_program_studi, nama_jenjang_pendidikan
                from program_studi
                where nama_program_studi is not null and nama_program_studi <> ''
            "))
                ->map(function (object $row) use ($facultyMap): array {
                    $fakultas = $facultyMap[(string) $row->kode_program_studi] ?? 'Belum Dipetakan';
                    $fakultasId = Str::slug($fakultas) ?: 'belum-dipetakan';
                    $jenjang = (string) $row->nama_jenjang_pendidikan;
                    $nama = (string) $row->nama_program_studi;

                    return [
                        'id' => (string) $row->id_prodi,
                        'kode' => (string) $row->kode_program_studi,
                        'fakultas_id' => $fakultasId,
                        'fakultas' => $fakultas,
                        'nama' => $nama,
                        'jenjang' => $jenjang,
                        'label' => trim("{$jenjang} {$nama}"),
                    ];
                })
                ->filter(fn (array $row): bool => $row['id'] !== '' && $row['nama'] !== '')
                ->unique('id')
                ->sortBy([
                    ['fakultas', 'asc'],
                    ['jenjang', 'asc'],
                    ['nama', 'asc'],
                ])
                ->values()
                ->all();
        }));
    }

    public function faculties(): Collection
    {
        return $this->programStudis()
            ->filter(fn (array $programStudi): bool => $programStudi['fakultas_id'] !== '')
            ->map(fn (array $programStudi): array => [
                'id' => $programStudi['fakultas_id'],
                'nama' => $programStudi['fakultas'],
                'label' => $programStudi['fakultas'],
            ])
            ->unique('id')
            ->sortBy('nama')
            ->values();
    }

    /**
     * @return array<string, string> peta kode_program_studi -> nama fakultas
     */
    private function facultyByProdiCode(): array
    {
        $path = database_path('data/program_studi.csv');

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $map = [];

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($headers) !== count($values)) {
                continue;
            }

            $record = array_combine($headers, $values);
            $code = trim((string) ($record['kode'] ?? ''));

            if ($code === '') {
                continue;
            }

            $map[$code] = $this->normalizeFaculty((string) ($record['fakultas'] ?? ''));
        }

        fclose($handle);

        return $map;
    }

    private function normalizeFaculty(string $faculty): string
    {
        $faculty = trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii($faculty))));

        if ($faculty === '') {
            return 'Belum Dipetakan';
        }

        if ($faculty === 'PASCASARJANA') {
            return 'Pascasarjana';
        }

        if (str_starts_with($faculty, 'FAKULTAS ')) {
            $faculty = trim(substr($faculty, 9));
        }

        return 'Fakultas '.Str::title(Str::lower($faculty));
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }

    private function cacheKey(string $key): string
    {
        return "sapto:local-data:{$key}";
    }
}
