<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penugasan;
use App\Services\KafkaProducerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Menyamar sebagai pengguna lain untuk akun admin — dipicu dari tombol
 * "Lihat SAPTO sebagai …" di tiap baris Kelola peran. Admin bisa menyamar
 * (impersonate) sebagai siapa pun yang terdaftar di `penugasan` (korprodi,
 * dekan, rektor) untuk menguji apa yang mereka lihat.
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
    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $penugasan = Penugasan::query()->findOrFail((int) $request->input('penugasan_id'));
        $target = ['nip' => $penugasan->nip, 'email' => $penugasan->email, 'nama' => $penugasan->nama];

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
        // Pelaku di event = admin asli (actorFromRequest membaca
        // impersonator_sso_user, yang baru saja diisi di atas).
        app(KafkaProducerService::class)->audit('impersonation.started', [
            'target_nip' => $target['nip'],
            'target_email' => $target['email'],
            'target_nama' => $target['nama'],
        ], $request);

        return redirect('/');
    }

    public function stop(Request $request)
    {
        // Sebelum sesi dikembalikan ke admin: setelahnya sso_user sudah admin
        // dan penanda penyamaran hilang.
        if ($request->session()->has('impersonator_sso_user')) {
            app(KafkaProducerService::class)->audit('impersonation.stopped', [
                'terakhir_sebagai' => $request->session()->get('sso_user.name'),
            ], $request);
        }

        $admin = $request->session()->pull('impersonator_sso_user');

        if ($admin) {
            Log::info('Admin SAPTO berhenti menyamar.', [
                'admin_email' => $admin['email'] ?? null,
                'admin_nip' => $admin['nomor_identitas'] ?? null,
                'terakhir_sebagai' => $request->session()->get('sso_user.name'),
            ]);

            $request->session()->put('sso_user', $admin);
        }

        return redirect()->route('admin.peran');
    }

    private function ensureAdmin(Request $request): void
    {
        $sso = (array) $request->session()->get('sso_user', []);

        abort_unless($sso['is_admin'] ?? false, 403, 'Halaman ini khusus akun admin/developer SAPTO.');
    }
}
