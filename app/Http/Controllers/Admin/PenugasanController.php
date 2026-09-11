<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penugasan;
use App\Services\SaptoProgramStudiRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /admin/peran — admin menentukan siapa boleh masuk SAPTO dan sebagai apa:
 * korprodi (satu prodi), dekan (satu fakultas), atau rektor (semua).
 * Akun yang tidak ada di sini (dan bukan admin) ditolak saat login.
 *
 * Admin sendiri sengaja tidak dikelola dari halaman ini — lihat catatan di
 * App\Models\Penugasan.
 */
class PenugasanController extends Controller
{
    public function index(Request $request, SaptoProgramStudiRepository $repo)
    {
        $this->ensureAdmin($request);

        $programStudis = $repo->programStudis();
        $prodiByKode = $programStudis->keyBy('kode');
        $fakultasById = $repo->faculties()->keyBy('id');

        $penugasans = Penugasan::query()
            ->orderByRaw("case peran when 'rektor' then 0 when 'dekan' then 1 else 2 end")
            ->orderBy('nama')
            ->get()
            ->map(function (Penugasan $p) use ($prodiByKode, $fakultasById): array {
                $lingkup = $p->lingkupInfo($prodiByKode, $fakultasById);
                $label = $p->label($prodiByKode, $fakultasById);

                return [
                    'id' => $p->id,
                    'peran' => $p->peran,
                    'nama' => $p->nama ?: ($p->nip ?: (string) $p->email),
                    'nip' => $p->nip,
                    'email' => $p->email,
                    'inisial' => Penugasan::inisial($p->nama, $p->email ?: $p->nip),
                    'lingkup' => $lingkup,
                    'label' => $label,
                    'dibuat_oleh' => $p->dibuat_oleh,
                    'tags' => $p->peran.($lingkup['ada'] ? '' : ' bermasalah'),
                    'search' => mb_strtolower(implode(' ', [$p->nama, $p->nip, $p->email, $label, $lingkup['sub']])),
                ];
            });

        return view('admin.peran', [
            'penugasans' => $penugasans,
            'jumlah' => $penugasans->countBy('peran')->put('bermasalah', $penugasans->where('lingkup.ada', false)->count()),
            'prodis' => $programStudis->sortBy([['fakultas', 'asc'], ['label', 'asc']])->values(),
            'fakultas' => $fakultasById->values(),
        ]);
    }

    public function store(Request $request, SaptoProgramStudiRepository $repo)
    {
        $this->ensureAdmin($request);

        // Rapikan dulu sebelum validasi: NIP sering disalin dengan spasi
        // ("19780121 200812 1 003"), email kadang huruf besar.
        $request->merge([
            'nip' => preg_replace('/\s+/', '', (string) $request->input('nip')) ?: null,
            'email' => strtolower(trim((string) $request->input('email'))) ?: null,
            'nama' => trim((string) $request->input('nama')),
        ]);

        $data = $request->validate([
            'peran' => ['required', Rule::in(Penugasan::PERAN)],
            'nip' => ['nullable', 'regex:/^\d{5,32}$/', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:nip'],
            'nama' => ['nullable', 'string', 'max:191'],
            'lingkup_prodi' => ['nullable', 'string', 'required_if:peran,korprodi'],
            'lingkup_fakultas' => ['nullable', 'string', 'required_if:peran,dekan'],
        ], [
            'nip.regex' => 'NIP/NIM hanya boleh berisi angka (5–32 digit).',
            'nip.required_without' => 'Isi NIP/NIM atau email — minimal salah satu.',
            'email.required_without' => 'Isi NIP/NIM atau email — minimal salah satu.',
            'lingkup_prodi.required_if' => 'Pilih program studi untuk korprodi.',
            'lingkup_fakultas.required_if' => 'Pilih fakultas untuk dekan.',
        ]);

        $lingkup = match ($data['peran']) {
            'korprodi' => $data['lingkup_prodi'],
            'dekan' => $data['lingkup_fakultas'],
            default => null,
        };

        // Lingkup wajib benar-benar ada di data referensi: kode prodi atau id
        // fakultas yang salah ketik akan membuat orangnya lolos login lalu
        // mentok 403 di setiap tabel, tanpa tahu kenapa.
        $valid = match ($data['peran']) {
            'korprodi' => $repo->programStudis()->contains('kode', $lingkup),
            'dekan' => $repo->faculties()->contains('id', $lingkup),
            default => true,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['lingkup' => 'Program studi/fakultas tidak ditemukan di data referensi SAPTO.']);
        }

        $sudahAda = Penugasan::query()
            ->where('peran', $data['peran'])
            ->where('lingkup', $lingkup)
            ->untukIdentitas($data['nip'], $data['email'])
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages(['nip' => 'Orang ini sudah terdaftar dengan peran dan lingkup yang sama.']);
        }

        $admin = (array) $request->session()->get('sso_user', []);

        $penugasan = Penugasan::create([
            'peran' => $data['peran'],
            'nip' => $data['nip'],
            'email' => $data['email'],
            'nama' => $data['nama'] ?? '',
            'lingkup' => $lingkup,
            'dibuat_oleh' => (string) ($admin['email'] ?? $admin['nomor_identitas'] ?? ''),
        ]);

        Log::info('Admin SAPTO memberi akses.', $this->jejak($admin, $penugasan));

        return redirect()->route('admin.peran')->with('status', 'Akses ditambahkan: '.($penugasan->nama ?: $penugasan->nip ?: $penugasan->email).'.');
    }

    public function destroy(Request $request, Penugasan $penugasan)
    {
        $this->ensureAdmin($request);

        $admin = (array) $request->session()->get('sso_user', []);
        $penugasan->delete();

        // Tidak perlu membuang sesi orangnya: SaptoUserContext menghitung
        // ulang hak akses setiap request, jadi pencabutan berlaku seketika.
        Log::info('Admin SAPTO mencabut akses.', $this->jejak($admin, $penugasan));

        return redirect()->route('admin.peran')->with('status', 'Akses dicabut: '.($penugasan->nama ?: $penugasan->nip ?: $penugasan->email).'.');
    }

    /** @return array<string, mixed> */
    private function jejak(array $admin, Penugasan $penugasan): array
    {
        return [
            'admin_email' => $admin['email'] ?? null,
            'admin_nip' => $admin['nomor_identitas'] ?? null,
            'peran' => $penugasan->peran,
            'lingkup' => $penugasan->lingkup,
            'target_nip' => $penugasan->nip,
            'target_email' => $penugasan->email,
            'target_nama' => $penugasan->nama,
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        $sso = (array) $request->session()->get('sso_user', []);

        abort_unless($sso['is_admin'] ?? false, 403, 'Halaman ini khusus akun admin SAPTO.');
    }
}
