<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Korprodi;
use App\Services\SaptoProgramStudiRepository;
use Illuminate\Http\Request;

/**
 * "Login sebagai" untuk akun admin — menggantikan URL /dev/login-as. Admin
 * bisa menyamar (impersonate) sebagai korprodi manapun untuk menguji apa yang
 * mereka lihat. Karena admin sudah punya akses penuh (universitas), menyamar
 * jadi korprodi justru akses LEBIH SEMPIT — tidak ada eskalasi hak, dan hanya
 * admin (is_admin=true) yang bisa membukanya. Identitas admin disimpan di
 * session ('impersonator_sso_user') supaya bisa dikembalikan.
 */
class ImpersonationController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $prodiIndex = app(SaptoProgramStudiRepository::class)->programStudis()->keyBy('kode');

        $korprodis = Korprodi::query()
            ->orderBy('nama')
            ->get()
            ->groupBy('nip')
            ->map(function ($rows) use ($prodiIndex): array {
                $first = $rows->first();
                $prodiLabels = $rows
                    ->map(fn ($r): string => $prodiIndex->get($r->kode_program_studi)['label'] ?? $r->kode_program_studi)
                    ->all();

                return [
                    'nip' => (string) $first->nip,
                    'nama' => (string) $first->nama,
                    'prodi' => implode(' · ', $prodiLabels),
                    'search' => strtolower($first->nama.' '.$first->nip.' '.implode(' ', $prodiLabels)),
                ];
            })
            ->values();

        return view('admin.login-as', ['korprodis' => $korprodis]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $nip = trim((string) $request->input('nip'));
        $korprodi = Korprodi::query()->where('nip', $nip)->first();

        // 'any=1' -> uji jalur "akses ditolak" dengan NIP yang bukan korprodi.
        if (! $korprodi && ! $request->boolean('any')) {
            abort(404, 'NIP tidak terdaftar sebagai korprodi.');
        }

        // Simpan identitas admin agar bisa dikembalikan; jangan tumpuk kalau
        // sudah menyamar (biar tidak kehilangan admin asli).
        if (! $request->session()->has('impersonator_sso_user')) {
            $request->session()->put('impersonator_sso_user', $request->session()->get('sso_user'));
        }

        $request->session()->put('sso_user', [
            'sub' => 'impersonate-'.$nip,
            'name' => $korprodi->nama ?? 'Uji Tanpa Akses',
            'email' => 'impersonate@sapto.local',
            'nomor_identitas' => $nip,
        ]);

        return redirect('/');
    }

    public function stop(Request $request)
    {
        $admin = $request->session()->pull('impersonator_sso_user');

        if ($admin) {
            $request->session()->put('sso_user', $admin);
        }

        return redirect()->route('admin.login-as');
    }

    private function ensureAdmin(Request $request): void
    {
        $sso = (array) $request->session()->get('sso_user', []);

        abort_unless($sso['is_admin'] ?? false, 403, 'Halaman ini khusus akun admin/developer SAPTO.');
    }
}
