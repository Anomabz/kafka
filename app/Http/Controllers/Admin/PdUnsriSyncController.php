<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PdUnsriSyncDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * /admin/sync-pd-unsri — status sinkronisasi mingguan data akademik dari PD
 * UNSRI per tabel, tombol mulai manual, dan tombol batal. Lihat
 * PdUnsriSyncDispatcher (data halaman) dan SyncPdUnsriTableJob (mekanisme).
 */
class PdUnsriSyncController extends Controller
{
    public function index(Request $request, PdUnsriSyncDispatcher $dispatcher)
    {
        $this->ensureAdmin($request);

        return view('admin.sync-pd-unsri', [
            'payload' => $dispatcher->statusPayload(),
            'syncTables' => config('pd_unsri_sync_tables.tables', []),
        ]);
    }

    /** Dipolling halaman tiap 5 detik saat berjalan, 60 detik saat diam. */
    public function status(Request $request, PdUnsriSyncDispatcher $dispatcher): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($dispatcher->statusPayload())
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, PdUnsriSyncDispatcher $dispatcher)
    {
        $this->ensureAdmin($request);

        $syncAll = $request->boolean('all');
        $tableNames = array_values(config('pd_unsri_sync_tables.tables', []));
        $validated = $syncAll ? [] : $request->validate([
            'table' => ['required', 'string', Rule::in($tableNames)],
        ]);

        if ($dispatcher->isRunning()) {
            return redirect()->route('admin.sync-pd-unsri')
                ->with('status', 'Sinkronisasi sebelumnya masih berjalan, belum dimulai ulang.');
        }

        $admin = (array) $request->session()->get('sso_user', []);
        $batch = $dispatcher->dispatch(
            'admin:'.($admin['email'] ?? $admin['nomor_identitas'] ?? 'tidak diketahui'),
            $syncAll ? [] : [$validated['table']],
        );

        Log::info($syncAll
            ? 'Admin SAPTO memulai sinkronisasi seluruh tabel PD UNSRI manual.'
            : 'Admin SAPTO memulai sinkronisasi satu tabel PD UNSRI manual.', [
            'admin_email' => $admin['email'] ?? null,
            'admin_nip' => $admin['nomor_identitas'] ?? null,
            'batch_id' => $batch->id,
            'seluruh_tabel' => $syncAll,
            'jumlah_tabel' => $batch->totalJobs,
            'tabel' => $syncAll ? null : $validated['table'],
        ]);

        return redirect()->route('admin.sync-pd-unsri')
            ->with('status', $syncAll
                ? "Sinkronisasi seluruh {$batch->totalJobs} tabel dimulai di latar belakang."
                : "Sinkronisasi tabel {$validated['table']} dimulai di latar belakang.");
    }

    public function cancel(Request $request, PdUnsriSyncDispatcher $dispatcher)
    {
        $this->ensureAdmin($request);

        $batch = $dispatcher->cancelLatest();

        if ($batch === null) {
            return redirect()->route('admin.sync-pd-unsri')
                ->with('status', 'Tidak ada sinkronisasi yang sedang berjalan.');
        }

        $admin = (array) $request->session()->get('sso_user', []);

        Log::warning('Admin SAPTO membatalkan sinkronisasi PD UNSRI.', [
            'admin_email' => $admin['email'] ?? null,
            'admin_nip' => $admin['nomor_identitas'] ?? null,
            'batch_id' => $batch->id,
            'sudah_diproses' => $batch->totalJobs - $batch->pendingJobs,
            'total_tabel' => $batch->totalJobs,
        ]);

        return redirect()->route('admin.sync-pd-unsri')
            ->with('status', 'Sinkronisasi dibatalkan. Tabel yang sedang ditarik berhenti dengan aman; data lama tetap dipakai.');
    }

    private function ensureAdmin(Request $request): void
    {
        $sso = (array) $request->session()->get('sso_user', []);

        abort_unless($sso['is_admin'] ?? false, 403, 'Halaman ini khusus akun admin SAPTO.');
    }
}
