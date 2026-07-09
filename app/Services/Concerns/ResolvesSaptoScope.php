<?php

namespace App\Services\Concerns;

use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Logika scope role SAPTO yang sama untuk semua tabel per-program-studi:
 * - prodi terpilih   -> hanya prodi itu
 * - fakultas terpilih -> seluruh prodi dalam fakultas
 * - tidak keduanya   -> null (tingkat universitas / seluruh PT)
 *
 * Dipakai bersama SaptoProgramStudiRepository sebagai sumber daftar prodi.
 */
trait ResolvesSaptoScope
{
    protected function programStudiById(Collection $programStudis, string $id): ?array
    {
        return $programStudis->first(fn (array $programStudi): bool => (string) $programStudi['id'] === $id);
    }

    protected function fakultasById(Collection $programStudis, string $id): ?array
    {
        $programStudi = $programStudis->first(fn (array $item): bool => (string) ($item['fakultas_id'] ?? '') === $id);

        if ($programStudi === null) {
            return null;
        }

        return [
            'id' => $programStudi['fakultas_id'],
            'nama' => $programStudi['fakultas'],
            'label' => $programStudi['fakultas'],
        ];
    }

    /**
     * @return array<int, string>|null  null = seluruh PT (tanpa filter prodi)
     */
    protected function scopeProdiIds(Collection $programStudis, ?string $fakultasId, ?string $programStudiId): ?array
    {
        if ($programStudiId !== null) {
            return [$programStudiId];
        }

        if ($fakultasId !== null) {
            return $programStudis
                ->where('fakultas_id', $fakultasId)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        }

        return null;
    }

    /**
     * Validasi kombinasi fakultas/prodi terpilih dan kembalikan metadata-nya.
     *
     * @return array{0: array|null, 1: array|null}  [programStudi, fakultas]
     */
    protected function validateScope(Collection $programStudis, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudi = $programStudiId === null ? null : $this->programStudiById($programStudis, $programStudiId);
        $fakultas = $fakultasId === null ? null : $this->fakultasById($programStudis, $fakultasId);

        if ($programStudiId !== null && $programStudi === null) {
            throw new HttpException(400, 'Program studi tidak ditemukan.');
        }

        if ($fakultasId !== null && $fakultas === null) {
            throw new HttpException(400, 'Fakultas tidak ditemukan.');
        }

        if ($programStudi !== null && $fakultasId !== null && (string) $programStudi['fakultas_id'] !== $fakultasId) {
            throw new HttpException(400, 'Program studi tidak berada dalam fakultas yang dipilih.');
        }

        return [$programStudi, $fakultas];
    }

    protected function cacheTtl(): int
    {
        return max(1, (int) config('services.pangkalan_data.cache_ttl', 3600));
    }
}
