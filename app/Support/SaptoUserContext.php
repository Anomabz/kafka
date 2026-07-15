<?php

namespace App\Support;

use App\Models\Korprodi;
use App\Services\SaptoProgramStudiRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaptoUserContext
{
    public function __construct(
        public readonly string $role,
        public readonly ?string $programStudiId = null,
        public readonly ?string $fakultasId = null,
    ) {}

    /**
     * Scope is derived from the logged-in SSO identity, never trusted from
     * the client. Only prodi-level access exists right now (via the korprodi
     * table, keyed by NIP) — fakultas/universitas-wide roles will plug in
     * here the same way once that entitlement data exists; until then an
     * unmatched NIP is denied entirely rather than falling back to full
     * ("universitas") access, which is what the old header-trusting version
     * effectively defaulted to for anyone.
     */
    public static function fromRequest(Request $request): self
    {
        $ssoUser = (array) $request->session()->get('sso_user', []);
        $nip = trim((string) ($ssoUser['nomor_identitas'] ?? ''));

        if ($nip === '') {
            throw new HttpException(403, 'Akun SSO tidak memiliki nomor identitas untuk menentukan hak akses SAPTO.');
        }

        $entitlements = Korprodi::query()->where('nip', $nip)->get();

        if ($entitlements->isEmpty()) {
            throw new HttpException(403, 'Akun ini belum terdaftar sebagai koordinator program studi di SAPTO.');
        }

        $entitledProgramStudis = app(SaptoProgramStudiRepository::class)->programStudis()
            ->whereIn('kode', $entitlements->pluck('kode_program_studi'));

        if ($entitledProgramStudis->isEmpty()) {
            throw new HttpException(403, 'Program studi akun ini tidak ditemukan pada data referensi SAPTO.');
        }

        $programStudi = self::chooseEntitledProgramStudi($request, $entitledProgramStudis);

        return new self('prodi', (string) $programStudi['id'], (string) $programStudi['fakultas_id']);
    }

    /**
     * Most korprodi coordinate a single prodi and need no choice. A few hold
     * dual appointments (multiple korprodi rows for the same NIP) — for
     * those, an optional selector is honoured, but only when it names a
     * program studi this NIP actually holds; otherwise it's ignored rather
     * than trusted, unlike the header this replaces.
     */
    private static function chooseEntitledProgramStudi(Request $request, Collection $entitledProgramStudis): array
    {
        if ($entitledProgramStudis->count() === 1) {
            return $entitledProgramStudis->first();
        }

        $requestedId = $request->header('X-SAPTO-Program-Studi-Id') ?: $request->query('program_studi_id');

        if ($requestedId !== null) {
            $match = $entitledProgramStudis->firstWhere('id', (string) $requestedId);

            if ($match !== null) {
                return $match;
            }
        }

        return $entitledProgramStudis->first();
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

    private static function isApiIdentifier(mixed $value): bool
    {
        return is_scalar($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}$/', (string) $value) === 1;
    }
}
