<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PangkalanDataApiClient
{
    public function profilPerguruanTinggi(): array
    {
        return Cache::remember($this->cacheKey('profil-pt'), $this->cacheTtl(), function (): array {
            $payload = $this->get('api/feeder/profil-pt');

            return data_get($payload, 'data.0', []);
        });
    }

    public function programStudis(): Collection
    {
        return collect(Cache::remember($this->cacheKey('program-studi'), $this->cacheTtl(), function (): array {
            $profile = $this->profilPerguruanTinggi();
            $rows = $this->paginated('api/feeder/all-prodi', (int) config('sapto.api.program_studi_max_pages', 100));

            return $rows
                ->filter(fn (array $row): bool => $this->belongsToInstitution($row, $profile))
                ->map(fn (array $row): array => $this->programStudiPayload($row))
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
            ->filter(fn (array $programStudi): bool => ($programStudi['fakultas_id'] ?? '') !== '')
            ->map(fn (array $programStudi): array => [
                'id' => $programStudi['fakultas_id'],
                'nama' => $programStudi['fakultas'],
                'label' => $programStudi['fakultas'],
            ])
            ->unique('id')
            ->sortBy('nama')
            ->values();
    }

    public function dosenPembimbingRows(): Collection
    {
        $maxPages = (int) config('sapto.api.data_max_pages', 3);

        return collect(Cache::remember($this->cacheKey("dosen-pembimbing:pages:{$maxPages}"), $this->cacheTtl(), function () use ($maxPages): array {
            return $this->paginated('api/feeder/dosen-pembimbing', $maxPages)->all();
        }));
    }

    public function bimbingMahasiswaRows(): Collection
    {
        $maxPages = (int) config('sapto.api.data_max_pages', 10);

        return collect(Cache::remember($this->cacheKey("bimbing-mahasiswa:pages:{$maxPages}"), $this->cacheTtl(), function () use ($maxPages): array {
            return $this->paginated('api/feeder/bimbing-mahasiswa', $maxPages)->all();
        }));
    }

    /**
     * @param  array<int, string>  $ids
     */
    public function aktivitasMahasiswaForIds(array $ids, int $limit): Collection
    {
        $ids = collect($ids)
            ->filter(fn (mixed $id): bool => is_scalar($id) && (string) $id !== '')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->take(max(0, $limit));

        $activities = collect();
        $missingIds = [];

        foreach ($ids as $id) {
            $cacheKey = $this->cacheKey("aktivitas:{$id}");

            if (Cache::has($cacheKey)) {
                $activity = Cache::get($cacheKey);

                if (is_array($activity)) {
                    $activities->put($id, $activity);
                }

                continue;
            }

            $missingIds[] = $id;
        }

        foreach (array_chunk($missingIds, $this->activityPoolSize()) as $chunk) {
            $token = $this->token();

            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $token): array {
                    return collect($chunk)
                        ->map(fn (string $id) => $pool
                            ->as($id)
                            ->withToken($token)
                            ->acceptJson()
                            ->timeout($this->timeout())
                            ->connectTimeout($this->connectTimeout())
                            ->get($this->baseUrl().'/api/feeder/list-aktivitas-mahasiswa', [
                                'filter[id_aktivitas]' => $id,
                            ]))
                        ->all();
                });
            } catch (ConnectionException $exception) {
                throw new HttpException(503, 'API Pangkalan Data UNSRI tidak dapat dihubungi.', $exception);
            }

            foreach ($responses as $id => $response) {
                if (! $response instanceof Response || $response->failed()) {
                    continue;
                }

                $activity = data_get($response->json(), 'data.0');

                if (! is_array($activity)) {
                    Cache::put($this->cacheKey("aktivitas:{$id}"), false, $this->cacheTtl());

                    continue;
                }

                Cache::put($this->cacheKey("aktivitas:{$id}"), $activity, $this->cacheTtl());
                $activities->put((string) $id, $activity);
            }
        }

        return $activities;
    }

    public function get(string $path, array $query = []): array
    {
        $response = $this->sendGet($path, $query);

        if ($response->unauthorized()) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->sendGet($path, $query);
        }

        if ($response->failed()) {
            throw new HttpException(503, 'API Pangkalan Data UNSRI belum dapat mengirim data yang diminta.');
        }

        return $response->json() ?? [];
    }

    /**
     * Sama seperti get(), tapi mengembalikan Response mentah tanpa melempar
     * exception di status gagal. Dipakai sinkronisasi massal PdUnsriSyncJob:
     * 404/403 per tabel di sana adalah sinyal "lewati tabel ini", bukan error
     * yang harus menghentikan seluruh sinkronisasi.
     */
    public function getRaw(string $path, array $query = []): Response
    {
        $response = $this->sendGet($path, $query);

        if ($response->unauthorized()) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->sendGet($path, $query);
        }

        return $response;
    }

    /**
     * Cek cepat jalur data lewat Gravitee untuk badge status (PdUnsriHealthCheck).
     * Timeout pendek supaya API yang mati tidak menggantung request, dan
     * ConnectionException sengaja TIDAK dibungkus seperti di sendGet():
     * pemanggil perlu membedakan "gateway tidak terjangkau/timeout" dari
     * "sumber membalas error". Kegagalan ambil token tetap HttpException.
     */
    public function probe(string $path, array $query = [], int $timeout = 5, int $connectTimeout = 3): Response
    {
        $send = fn (): Response => Http::baseUrl($this->baseUrl())
            ->withToken($this->token())
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->get($path, $query);

        $response = $send();

        if ($response->unauthorized()) {
            Cache::forget($this->tokenCacheKey());
            $response = $send();
        }

        return $response;
    }

    public function paginated(string $path, int $maxPages, array $query = []): Collection
    {
        $items = collect();
        $maxPages = max(1, $maxPages);

        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = $this->get($path, array_merge($query, ['page' => $page]));
            $items = $items->concat(data_get($payload, 'data', []));
            $lastPage = (int) data_get($payload, 'meta.last_page', $page);

            if ($page >= $lastPage) {
                break;
            }
        }

        return $items->values();
    }

    private function aktivitasMahasiswaById(string $id): ?array
    {
        return Cache::remember($this->cacheKey("aktivitas:{$id}"), $this->cacheTtl(), function () use ($id): ?array {
            $payload = $this->get('api/feeder/list-aktivitas-mahasiswa', [
                'filter[id_aktivitas]' => $id,
            ]);

            $activity = data_get($payload, 'data.0');

            return is_array($activity) ? $activity : null;
        });
    }

    private function token(): string
    {
        $email = (string) config('services.pangkalan_data.email');
        $password = (string) config('services.pangkalan_data.password');

        if ($email === '' || $password === '') {
            throw new HttpException(503, 'Kredensial API Pangkalan Data UNSRI belum dikonfigurasi.');
        }

        return Cache::remember($this->tokenCacheKey(), $this->tokenCacheTtl(), function () use ($email, $password): string {
            try {
                // MODIFIKASI 1: Memanggil authBaseUrl() agar request token mengarah ke server asli
                // MODIFIKASI 4: Endpoint auth kampus adalah api/get-token, bukan api/login
                $response = Http::baseUrl($this->authBaseUrl())
                    ->acceptJson()
                    ->timeout($this->timeout())
                    ->connectTimeout($this->connectTimeout())
                    ->withQueryParameters([
                        'email' => $email,
                        'password' => $password,
                    ])
                    ->post('api/get-token');
            } catch (ConnectionException $exception) {
                throw new HttpException(503, 'API Pangkalan Data UNSRI tidak dapat dihubungi.', $exception);
            }

            if ($response->failed()) {
                throw new HttpException(503, 'Login API Pangkalan Data UNSRI gagal. Periksa email dan password API.');
            }

            // MODIFIKASI 5: Respons api/get-token menaruh token di root, bukan di data.token
            $token = data_get($response->json(), 'token');

            if (! is_string($token) || $token === '') {
                throw new HttpException(503, 'Token API Pangkalan Data UNSRI tidak ditemukan di respons login.');
            }

            return $token;
        });
    }

    private function sendGet(string $path, array $query): Response
    {
        try {
            return Http::baseUrl($this->baseUrl())
                ->withToken($this->token())
                ->acceptJson()
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new HttpException(503, 'API Pangkalan Data UNSRI tidak dapat dihubungi.', $exception);
        }
    }

    private function belongsToInstitution(array $row, array $profile): bool
    {
        $profileId = (string) data_get($profile, 'id_perguruan_tinggi', '');
        $profileCode = (string) data_get($profile, 'kode_perguruan_tinggi', '');

        if ($profileId !== '' && (string) data_get($row, 'id_perguruan_tinggi', '') === $profileId) {
            return true;
        }

        if ($profileCode !== '' && (string) data_get($row, 'kode_perguruan_tinggi', '') === $profileCode) {
            return true;
        }

        $profileName = str((string) data_get($profile, 'nama_perguruan_tinggi', ''))->lower()->toString();
        $institutionName = str((string) data_get($row, 'nama_perguruan_tinggi', ''))->lower()->toString();

        if ($profileName !== '') {
            return $institutionName === $profileName;
        }

        $keyword = str((string) config('sapto.institution_keyword', 'Sriwijaya'))->lower()->toString();

        return $keyword !== '' && str_contains($institutionName, $keyword);
    }

    private function programStudiPayload(array $row): array
    {
        $jenjang = SaptoProgramStudiRepository::cleanJenjang((string) data_get($row, 'nama_jenjang_pendidikan', ''));
        $nama = (string) data_get($row, 'nama_program_studi', '');
        $mapping = $this->studyProgramMappingFor($row);
        $fakultas = (string) ($mapping['faculty'] ?? '');
        $fakultasId = $fakultas === '' ? '' : Str::slug($fakultas);

        return [
            'id' => (string) data_get($row, 'id_prodi', ''),
            'kode' => (string) data_get($row, 'kode_program_studi', ''),
            'fakultas_id' => $fakultasId,
            'fakultas' => $fakultas === '' ? 'Belum Dipetakan' : $fakultas,
            'nama' => $nama,
            'jenjang' => $jenjang,
            'label' => SaptoProgramStudiRepository::composeLabel($jenjang, $nama),
        ];
    }

    private function studyProgramMappingFor(array $row): ?array
    {
        $code = (string) data_get($row, 'kode_program_studi', '');
        $level = $this->normalizeLevel((string) data_get($row, 'nama_jenjang_pendidikan', ''));
        $name = $this->normalizeName((string) data_get($row, 'nama_program_studi', ''));

        $mappings = $this->studyProgramMappings();

        return $mappings->first(function (array $mapping) use ($code, $level, $name): bool {
            if ($code !== '' && $mapping['code'] === $code) {
                return true;
            }

            return $mapping['level'] === $level && $mapping['normalized_name'] === $name;
        });
    }

    private function studyProgramMappings(): Collection
    {
        return collect(Cache::remember($this->cacheKey('program-studi-fakultas-map'), $this->cacheTtl(), function (): array {
            $path = database_path('data/program_studi.csv');

            if (! is_file($path)) {
                return [];
            }

            $handle = fopen($path, 'r');

            if ($handle === false) {
                return [];
            }

            $headers = fgetcsv($handle) ?: [];
            $rows = [];

            while (($values = fgetcsv($handle)) !== false) {
                $record = array_combine($headers, $values);

                if (! is_array($record)) {
                    continue;
                }

                $name = trim((string) ($record['nama'] ?? ''));

                $rows[] = [
                    'code' => trim((string) ($record['kode'] ?? '')),
                    'faculty' => $this->normalizeFaculty((string) ($record['fakultas'] ?? '')),
                    'name' => $name,
                    'level' => $this->normalizeLevel((string) ($record['jenjang'] ?? '')),
                    'normalized_name' => $this->normalizeName($name),
                ];
            }

            fclose($handle);

            return $rows;
        }));
    }

    private function normalizeName(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(
            ['international', 'olah raga', 'tanamam', 'administrasi negara', 'obstertik', 'pendidikan luar sekolah'],
            ['internasional', 'olahraga', 'tanaman', 'administrasi publik', 'obstetrik', 'pendidikan masyarakat'],
            $value,
        );
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = (string) preg_replace('/\b(pjj|pskgj|program)\b/', ' ', $value);
        $value = (string) preg_replace('/\b(profesi)\s+\1\b/', 'profesi', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeLevel(string $level): string
    {
        return match (Str::lower($level)) {
            'sp1', 'sp-1' => 'Sp-1',
            'sp2', 'sp-2' => 'Sp-2',
            'profesi' => 'Profesi',
            default => Str::upper($level),
        };
    }

    private function normalizeFaculty(string $faculty): string
    {
        $faculty = trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii($faculty))));

        if ($faculty === 'KEDOKTERANM') {
            $faculty = 'KEDOKTERAN';
        }

        if ($faculty === '') {
            return '';
        }

        if ($faculty === 'PASCASARJANA') {
            return 'Pascasarjana';
        }

        if (str_starts_with($faculty, 'FAKULTAS ')) {
            $faculty = trim(substr($faculty, 9));
        }

        return 'Fakultas '.Str::title(Str::lower($faculty));
    }

    // MODIFIKASI 2: Menambahkan fungsi authBaseUrl() baru
    private function authBaseUrl(): string
    {
        return rtrim((string) config('services.pangkalan_data.base_url'), '/');
    }

    // MODIFIKASI 3: Mengubah baseUrl() untuk membelokkan rute data ke Gravitee Gateway
    private function baseUrl(): string
    {
        return rtrim(env('SAPTO_GATEWAY_URL', 'http://localhost:8077/sapto-api'), '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.pangkalan_data.timeout', 30));
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('services.pangkalan_data.connect_timeout', 10));
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }

    private function tokenCacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.token_cache_ttl', 3300));
    }

    private function activityPoolSize(): int
    {
        return max(1, (int) config('sapto.api.activity_pool_size', 20));
    }

    private function tokenCacheKey(): string
    {
        return 'pangkalan-data:token:'.md5((string) config('services.pangkalan_data.email'));
    }

    private function cacheKey(string $key): string
    {
        return 'pangkalan-data:'.md5($this->baseUrl()).":{$key}";
    }
}
