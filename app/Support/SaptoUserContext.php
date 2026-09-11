<?php

namespace App\Support;

use App\Models\Penugasan;
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
     * the client, and denied by default: an account with no row in
     * `penugasan` gets nothing rather than falling back to full access.
     *
     * Peran -> role:
     *   rektor   -> 'universitas' (semua fakultas & prodi)
     *   dekan    -> 'fakultas'    (terkunci di fakultasnya)
     *   korprodi -> 'prodi'       (terkunci di prodinya)
     *
     * Penugasan dicocokkan lewat NIP ATAU email (Penugasan::untukIdentitas),
     * karena realm Keycloak kadang tidak mengirim `nomor_identitas` sama
     * sekali. Orang yang merangkap mendapat peran terluasnya.
     *
     * Admin (users.is_admin, SsoController::isSaptoAdmin) tetap jalur
     * terpisah dan selalu 'universitas'.
     *
     * Dihitung ulang setiap request, jadi mencabut penugasan dari
     * /admin/peran berlaku seketika tanpa perlu membuang sesi siapa pun.
     */
    public static function fromRequest(Request $request): self
    {
        $ssoUser = (array) $request->session()->get('sso_user', []);

        if ($ssoUser['is_admin'] ?? false) {
            return new self('universitas');
        }

        $penugasans = Penugasan::query()
            ->untukIdentitas($ssoUser['nomor_identitas'] ?? null, $ssoUser['email'] ?? null)
            ->get();

        return match (Penugasan::peranTerluas($penugasans)) {
            'rektor' => new self('universitas'),
            'dekan' => self::forDekan($penugasans->where('peran', 'dekan')),
            'korprodi' => self::forKorprodi($request, $penugasans->where('peran', 'korprodi')),
            default => throw new HttpException(403, 'Akun ini belum terdaftar di SAPTO. Hubungi admin SAPTO untuk didaftarkan.'),
        };
    }

    private static function forDekan(Collection $penugasans): self
    {
        $faculties = app(SaptoProgramStudiRepository::class)->faculties()->pluck('id')->map(fn ($id): string => (string) $id);

        // ponytail: dekan rangkap dua fakultas tidak ada di dunia nyata, jadi
        // yang pertama valid dipakai. Kalau suatu saat ada, tiru pola
        // chooseEntitledProgramStudi() di bawah.
        $fakultasId = $penugasans->pluck('lingkup')
            ->map(fn ($id): string => (string) $id)
            ->first(fn (string $id): bool => $faculties->contains($id));

        if ($fakultasId === null) {
            throw new HttpException(403, 'Fakultas akun ini tidak ditemukan pada data referensi SAPTO.');
        }

        return new self('fakultas', null, $fakultasId);
    }

    private static function forKorprodi(Request $request, Collection $penugasans): self
    {
        $entitledProgramStudis = app(SaptoProgramStudiRepository::class)->programStudis()
            ->whereIn('kode', $penugasans->pluck('lingkup'));

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

    /**
     * Keterangan peran untuk ditampilkan (profil di sidebar). Diturunkan
     * dari konteks yang sama yang membatasi data, jadi labelnya tidak
     * mungkin berbeda dari hak akses yang benar-benar berlaku.
     *
     * @return array{peran: string, lingkup: string, cakupan: string}
     */
    public function keterangan(): array
    {
        $repo = app(SaptoProgramStudiRepository::class);

        return match ($this->role) {
            'fakultas' => (function () use ($repo): array {
                $fakultas = $repo->faculties()->firstWhere('id', $this->fakultasId)['nama'] ?? (string) $this->fakultasId;

                return ['peran' => 'Dekan', 'lingkup' => $fakultas, 'cakupan' => 'semua program studi di '.$fakultas];
            })(),
            'prodi' => (function () use ($repo): array {
                $prodi = $repo->programStudis()->firstWhere('id', $this->programStudiId)['label'] ?? (string) $this->programStudiId;

                return ['peran' => 'Koordinator Program Studi', 'lingkup' => $prodi, 'cakupan' => 'hanya '.$prodi];
            })(),
            default => ['peran' => 'Rektor', 'lingkup' => 'Universitas Sriwijaya', 'cakupan' => 'semua fakultas & program studi'],
        };
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

        // Dekan boleh memilih prodi, tapi hanya di fakultasnya sendiri.
        // Dijaga di sini -- satu titik yang dilewati semua controller --
        // bukan diserahkan ke masing-masing repository: 9 repository tidak
        // melewati ResolvesSaptoScope::validateScope(), jadi tanpa penjaga ini
        // dekan cukup mengganti program_studi_id di URL untuk mengintip prodi
        // fakultas lain.
        if ($this->role === 'fakultas') {
            $programStudi = app(SaptoProgramStudiRepository::class)->programStudis()
                ->firstWhere('id', (string) $programStudiId);

            if ($programStudi === null || (string) $programStudi['fakultas_id'] !== $this->fakultasId) {
                throw new HttpException(403, 'Program studi ini berada di luar fakultas akun Anda.');
            }
        }

        return (string) $programStudiId;
    }

    private static function isApiIdentifier(mixed $value): bool
    {
        return is_scalar($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}$/', (string) $value) === 1;
    }
}
