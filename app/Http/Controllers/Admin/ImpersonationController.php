<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penugasan;
use App\Services\SaptoProgramStudiRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Login sebagai" untuk akun admin — menggantikan URL /dev/login-as. Admin
 * bisa menyamar (impersonate) sebagai siapa pun yang terdaftar di
 * `penugasan` (korprodi, dekan, rektor) untuk menguji apa yang mereka lihat.
 * Karena admin sudah punya akses penuh (universitas), menyamar justru akses
 * yang sama atau LEBIH SEMPIT — tidak ada eskalasi hak, dan hanya admin
 * (is_admin=true) yang bisa membukanya. Identitas admin disimpan di session
 * ('impersonator_sso_user') supaya bisa dikembalikan.
 *
 * Setiap penyamaran dicatat ke log: admin bisa membuka data prodi mana pun
 * atas nama orang lain, dan untuk sistem akreditasi itu pertanyaan pertama
 * yang akan diajukan auditor.
 */
class ImpersonationController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $repo = app(SaptoProgramStudiRepository::class);
        $prodiByKode = $repo->programStudis()->keyBy('kode');
        $fakultasById = $repo->faculties()->keyBy('id');

        $orang = Penugasan::query()
            ->orderBy('nama')
            ->get()
            ->groupBy(fn (Penugasan $p): string => $p->nip ?: 'email:'.$p->email)
            ->map(function ($rows) use ($prodiByKode, $fakultasById): array {
                $first = $rows->first();
                $lingkup = $rows->map(fn (Penugasan $p): array => $p->lingkupInfo($prodiByKode, $fakultasById));
                $peran = Penugasan::peranTerluas($rows);

                return [
                    'penugasan_id' => $first->id,
                    'nama' => $first->nama ?: ($first->nip ?: (string) $first->email),
                    'inisial' => Penugasan::inisial($first->nama, $first->email ?: $first->nip),
                    'peran' => $peran,
                    'lingkup' => $lingkup->pluck('nama')->implode(' · '),
                    'bermasalah' => $lingkup->contains('ada', false),
                    'identitas' => collect([$first->nip ? 'NIP '.$first->nip : null, $first->email])->filter()->implode(' · '),
                    'tags' => $rows->pluck('peran')->unique()->implode(' ').($lingkup->contains('ada', false) ? ' bermasalah' : ''),
                    'urut' => array_search($peran, Penugasan::PERAN, true),
                    'search' => mb_strtolower(implode(' ', [$first->nama, $first->nip, $first->email, $lingkup->pluck('nama')->implode(' '), $lingkup->pluck('sub')->implode(' ')])),
                ];
            })
            ->sortBy([['urut', 'asc'], ['nama', 'asc']])
            ->values();

        return view('admin.login-as', [
            'orang' => $orang,
            'jumlah' => $orang->countBy('peran')->put('bermasalah', $orang->where('bermasalah', true)->count()),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        if ($request->boolean('any')) {
            // Uji jalur "akses ditolak" dengan NIP yang tidak terdaftar.
            $target = ['nip' => trim((string) $request->input('nip')), 'email' => null, 'nama' => 'Uji Tanpa Akses'];
        } else {
            $penugasan = Penugasan::query()->findOrFail((int) $request->input('penugasan_id'));
            $target = ['nip' => $penugasan->nip, 'email' => $penugasan->email, 'nama' => $penugasan->nama];
        }

        $admin = $request->session()->get('impersonator_sso_user') ?? $request->session()->get('sso_user');

        // Simpan identitas admin agar bisa dikembalikan; jangan tumpuk kalau
        // sudah menyamar (biar tidak kehilangan admin asli).
        if (! $request->session()->has('impersonator_sso_user')) {
            $request->session()->put('impersonator_sso_user', $admin);
        }

        // NIP dan email ASLI orangnya, bukan alamat palsu: SaptoUserContext
        // mencocokkan penugasan lewat keduanya, jadi yang terlihat benar-benar
        // sama dengan yang dilihat orang itu saat login sendiri.
        $request->session()->put('sso_user', [
            'sub' => 'impersonate-'.($target['nip'] ?: $target['email']),
            'name' => $target['nama'] ?: 'Uji Tanpa Akses',
            'email' => $target['email'],
            'nomor_identitas' => $target['nip'],
        ]);

        Log::info('Admin SAPTO menyamar sebagai pengguna lain.', [
            'admin_email' => $admin['email'] ?? null,
            'admin_nip' => $admin['nomor_identitas'] ?? null,
            'target_nip' => $target['nip'],
            'target_email' => $target['email'],
            'target_nama' => $target['nama'],
        ]);

        return redirect('/');
    }

    public function stop(Request $request)
    {
        $admin = $request->session()->pull('impersonator_sso_user');

        if ($admin) {
            Log::info('Admin SAPTO berhenti menyamar.', [
                'admin_email' => $admin['email'] ?? null,
                'admin_nip' => $admin['nomor_identitas'] ?? null,
                'terakhir_sebagai' => $request->session()->get('sso_user.name'),
            ]);

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
