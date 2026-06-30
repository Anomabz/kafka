<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaptoUserContext
{
    public function __construct(
        public readonly string $role,
        public readonly ?string $programStudiId = null,
        public readonly ?string $fakultasId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $role = self::normalizeRole((string) $request->header('X-SAPTO-Role', 'universitas'));

        if ($role === null) {
            throw new HttpException(403, 'Role SAPTO tidak berhak mengakses data ini.');
        }

        if ($role === 'prodi') {
            $programStudiId = $request->header('X-SAPTO-Program-Studi-Id');

            if (! self::isApiIdentifier($programStudiId)) {
                throw new HttpException(403, 'Akun pengelola program studi belum memiliki program_studi_id.');
            }

            return new self($role, (string) $programStudiId);
        }

        if ($role === 'fakultas') {
            $fakultasId = $request->header('X-SAPTO-Fakultas-Id');

            if (! self::isApiIdentifier($fakultasId)) {
                throw new HttpException(403, 'Akun pengelola fakultas belum memiliki fakultas_id.');
            }

            return new self($role, null, (string) $fakultasId);
        }

        return new self($role);
    }

    public function canSelectFakultas(): bool
    {
        return $this->role === 'universitas';
    }

    public function canSelectProgramStudi(): bool
    {
        return in_array($this->role, ['universitas', 'fakultas'], true);
    }

    public function resolveFakultasId(Request $request): ?string
    {
        if ($this->role === 'fakultas') {
            return $this->fakultasId;
        }

        if ($this->role === 'prodi') {
            return null;
        }

        $fakultasId = $request->query('fakultas_id');

        if ($fakultasId === null || $fakultasId === '' || $fakultasId === 'all') {
            return null;
        }

        if (! self::isApiIdentifier($fakultasId)) {
            throw new HttpException(400, 'Parameter fakultas_id tidak valid.');
        }

        return (string) $fakultasId;
    }

    public function resolveProgramStudiId(Request $request): ?string
    {
        if ($this->role === 'prodi') {
            return $this->programStudiId;
        }

        $programStudiId = $request->query('program_studi_id');

        if ($programStudiId === null || $programStudiId === '' || $programStudiId === 'all') {
            return null;
        }

        if (! self::isApiIdentifier($programStudiId)) {
            throw new HttpException(400, 'Parameter program_studi_id tidak valid.');
        }

        return (string) $programStudiId;
    }

    private static function normalizeRole(string $role): ?string
    {
        $normalized = str($role)->lower()->replace([' ', '-'], '_')->toString();

        return match ($normalized) {
            'universitas',
            'sapto_universitas',
            'pengelola_universitas',
            'pengelola_sapto_universitas' => 'universitas',
            'fakultas',
            'pengelola_fakultas',
            'sapto_fakultas',
            'pengelola_sapto_fakultas' => 'fakultas',
            'prodi',
            'program_studi',
            'pengelola_prodi',
            'pengelola_program_studi' => 'prodi',
            default => null,
        };
    }

    private static function isApiIdentifier(mixed $value): bool
    {
        return is_scalar($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}$/', (string) $value) === 1;
    }
}
