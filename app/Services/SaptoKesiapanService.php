<?php

namespace App\Services;

use App\Http\Controllers\Api\Sapto as Api;
use App\Support\SaptoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "Cincin kesiapan data akreditasi" untuk Beranda mobile: satu angka per tabel
 * LKPS (lengkap / sebagian / kosong) plus persentase keseluruhan.
 *
 * Status TIDAK dihitung ulang di sini. Tiap tabel dipanggil lewat controller
 * summary-nya sendiri (persis yang dipanggil halaman web), jadi definisi
 * "ada isinya" dan scope pengguna (SaptoUserContext) tidak mungkin melenceng
 * dari yang tampil di tabel -- satu sumber kebenaran per tabel, sama seperti
 * SaptoDashboardRepository. Bedanya, di sini controller dipanggil langsung
 * dengan Request turunan, bukan lewat router: sub-request penuh akan mengulang
 * middleware sso.auth (pengecekan sesi ke Keycloak) dan sesi/cookie untuk
 * setiap dari ~45 tabel, dan gagal diam-diam bila middleware berubah.
 */
class SaptoKesiapanService
{
    // Sama dengan TTL ringkasan tabel (24 jam, lihat RefreshSaptoCacheJob):
    // isi tabel hanya berubah saat sinkronisasi PD UNSRI, dan job itu
    // menjalankan cache:clear setelahnya, jadi cincin ikut segar. TTL pendek
    // cuma membuat Beranda menghitung ulang 45 tabel (~8 detik saat dingin)
    // tanpa ada data yang berubah.
    private const CACHE_TTL = 86400;

    /** Catatan tabel yang barisnya hanya templat (lihat klasifikasi()). */
    public const PERLU_ISI_MANUAL = 'Perlu diisi manual';

    /**
     * Semua tabel yang tampil di Beranda, urut seperti daftar $tables di
     * routes/web.php, dengan id yang sama. Nilai null = belum punya endpoint.
     * Kunci = id tabel (BUKAN slug endpoint: beberapa berbeda, mis.
     * 'ewmp-dosen-tetap' vs endpoint 'ewmp-dosen').
     *
     * Tabel baru wajib ditambah di sini juga; SaptoKesiapanTest menjaga agar
     * tiap route tables/{slug}/summary punya wakil di daftar ini.
     *
     * @var array<string, array{0: class-string, 1: string}|null>
     */
    private const TABLES = [
        'kerjasama-pendidikan' => [Api\KerjasamaPendidikanController::class, 'summary'],
        'kerjasama-penelitian' => [Api\KerjasamaPenelitianController::class, 'summary'],
        'kerjasama-pkm' => [Api\KerjasamaPkmController::class, 'summary'],
        'seleksi-mahasiswa' => [Api\SeleksiMahasiswaController::class, 'summary'],
        'mahasiswa-asing' => [Api\MahasiswaController::class, 'data_mahasiswa_asing'],
        'dosen-tetap-pt' => [Api\DosenTetapController::class, 'summary'],
        'dosen-pembimbing-ta' => [Api\DosenPembimbingTaController::class, 'summary'],
        'ewmp-dosen-tetap' => [Api\EwmpDosenController::class, 'summary'],
        'dosen-tidak-tetap' => [Api\DosenTidakTetapController::class, 'summary'],
        'dosen-industri' => [Api\DosenIndustriController::class, 'summary'],
        'rekognisi-dosen' => [Api\RekognisiDtpsController::class, 'summary'],
        'penelitian-dtps' => [Api\PenelitianDtpsController::class, 'summary'],
        'pkm-dtps' => [Api\PkmDtpsController::class, 'summary'],
        'publikasi-ilmiah-dtps' => [Api\PublikasiIlmiahDtpsController::class, 'summary'],
        'pagelaran-dtps' => [Api\PagelaranDtpsController::class, 'summary'],
        'sitasi-dtps' => [Api\SitasiDtpsController::class, 'summary'],
        'produk-jasa-dtps' => [Api\ProdukJasaDtpsController::class, 'summary'],
        'hki-paten-dtps' => [Api\HkiPatenDtpsController::class, 'summary'],
        'hki-hak-cipta-dtps' => [Api\HkiHakCiptaDtpsController::class, 'summary'],
        'teknologi-dtps' => [Api\TeknologiDtpsController::class, 'summary'],
        'buku-dtps' => [Api\BukuDtpsController::class, 'summary'],
        'penggunaan-dana' => [Api\PenggunaanDanaController::class, 'summary'],
        'kurikulum' => [Api\KurikulumController::class, 'summary'],
        'integrasi-pembelajaran' => [Api\IntegrasiPembelajaranController::class, 'summary'],
        'kepuasan-mahasiswa' => [Api\KepuasanMahasiswaController::class, 'summary'],
        'penelitian-melibatkan-mahasiswa' => [Api\PenelitianMelibatkanMhsController::class, 'summary'],
        'penelitian-rujukan-tema' => [Api\PenelitianRujukanTemaController::class, 'summary'],
        'pkm-melibatkan-mahasiswa' => [Api\PkmMelibatkanMhsController::class, 'summary'],
        'ipk-lulusan' => [Api\IpkLulusanController::class, 'summary'],
        'prestasi-akademik' => [Api\PrestasiAkademikController::class, 'summary'],
        'prestasi-non-akademik' => [Api\PrestasiNonAkademikController::class, 'summary'],
        'masa-studi' => [Api\MasaStudiController::class, 'summary'],
        'waktu-tunggu' => [Api\WaktuTungguController::class, 'summary'],
        'kesesuaian-bidang-kerja' => [Api\KesesuaianBidangKerjaController::class, 'summary'],
        'tempat-kerja' => [Api\TempatKerjaController::class, 'summary'],
        'referensi-kepuasan-pengguna' => [Api\ReferensiKepuasanPenggunaController::class, 'summary'],
        'kepuasan-pengguna' => [Api\KepuasanPenggunaController::class, 'summary'],
        'publikasi-mahasiswa' => [Api\PublikasiMahasiswaController::class, 'summary'],
        'pagelaran-mahasiswa' => [Api\PagelaranMahasiswaController::class, 'summary'],
        'sitasi-mahasiswa' => [Api\SitasiMahasiswaController::class, 'summary'],
        'produk-jasa-mahasiswa' => [Api\ProdukJasaMahasiswaController::class, 'summary'],
        'hki-paten-mahasiswa' => [Api\HkiPatenMahasiswaController::class, 'summary'],
        'hki-hak-cipta-mahasiswa' => [Api\HkiHakCiptaMahasiswaController::class, 'summary'],
        'teknologi-mahasiswa' => [Api\TeknologiMahasiswaController::class, 'summary'],
        'buku-mahasiswa' => [Api\BukuMahasiswaController::class, 'summary'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(Request $request, ?int $ts): array
    {
        // Scope diselesaikan SEBELUM apa pun dijalankan, di luar isolasi galat
        // per tabel: akun tak terdaftar (403) atau fakultas_id ngawur (400)
        // harus menggagalkan seluruh permintaan, bukan berubah menjadi 45
        // tabel "Gagal dimuat" yang menyesatkan.
        $context = SaptoUserContext::fromRequest($request);
        $fakultasId = $context->resolveFakultasId($request);
        $programStudiId = $context->resolveProgramStudiId($request);

        // Kunci memakai scope EFEKTIF (peran + fakultas/prodi hasil resolusi),
        // bukan query mentah: hasil tiap tabel hanya bergantung pada scope itu,
        // dan hanya pengguna dengan scope sama yang boleh berbagi cache. Query
        // mentah akan membuat dekan bisa "menyeberang" lewat parameter yang
        // diabaikan resolver.
        $key = 'sapto:kesiapan:v5:'.sha1(json_encode([
            $context->role, $context->fakultasId, $context->programStudiId,
            $fakultasId, $programStudiId, $ts,
        ]));

        $jenis = $this->jenisProgram($programStudiId);

        $hasil = Cache::remember($key, self::CACHE_TTL, fn (): array => $this->hitung($request, $ts, $jenis));

        // Di luar cache: murah, dan harus langsung berubah begitu sinkronisasi selesai.
        return $hasil + ['last_sync_at' => $this->lastSyncAt()];
    }

    /**
     * @return array<string, mixed>
     */
    private function hitung(Request $request, ?int $ts, ?string $jenis): array
    {
        $tables = [];
        $tsTabel = [];
        $wajib = config('lkps.wajib', []);

        foreach (self::TABLES as $id => $handler) {
            // Templat LKPS: sebagian tabel hanya untuk jenis program tertentu
            // (mis. 6.b hanya Magister/Doktor). Tidak dipanggil sama sekali.
            if ($jenis !== null && isset($wajib[$id]) && ! in_array($jenis, $wajib[$id], true)) {
                $tables[] = $this->baris($id, 'tidak_wajib', 0,
                    'Tidak wajib untuk program '.config("lkps.label.{$jenis}", $jenis).' menurut templat LKPS BAN-PT.');

                continue;
            }

            if ($handler === null) {
                $tables[] = $this->baris($id, 'kosong', 0, 'Belum tersedia');

                continue;
            }

            try {
                $envelope = $this->panggil($request, $ts, $handler);
            } catch (Throwable $e) {
                // Satu tabel rusak tidak boleh menjatuhkan cincin; sisanya tetap terhitung.
                report($e);
                $tables[] = $this->baris($id, 'kosong', 0, 'Gagal dimuat');

                continue;
            }

            if ($envelope === null) {
                $tables[] = $this->baris($id, 'kosong', 0, 'Gagal dimuat');

                continue;
            }

            if (isset($envelope['ts']) && is_numeric($envelope['ts'])) {
                $tsTabel[] = (int) $envelope['ts'];
            }

            $tables[] = $this->klasifikasi($id, $envelope);
        }

        $counts = array_count_values(array_column($tables, 'status'));
        // `perlu_prodi` (belum bisa dinilai di lingkup ini) dan `tidak_wajib`
        // (bukan kewajiban jenis program ini) tidak ikut dihitung.
        $total = count($tables) - ($counts['perlu_prodi'] ?? 0) - ($counts['tidak_wajib'] ?? 0);
        $lengkap = $counts['lengkap'] ?? 0;
        $sebagian = $counts['sebagian'] ?? 0;

        return [
            'success' => true,
            'ts' => $this->tsDominan($tsTabel) ?? $ts,
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'total' => $total,
                'lengkap' => $lengkap,
                'sebagian' => $sebagian,
                'kosong' => $counts['kosong'] ?? 0,
                'perlu_prodi' => $counts['perlu_prodi'] ?? 0,
                'tidak_wajib' => $counts['tidak_wajib'] ?? 0,
                // "Terisi" = tabel yang sudah berisi baris untuk lingkup ini.
                // `sebagian` (source.partial) berarti ada KOLOM yang sumbernya
                // belum disinkronkan -- keterbatasan sistem, bukan kekurangan
                // prodi -- jadi ikut dihitung terisi. Menghitung hanya
                // `lengkap` membuat hampir semua prodi tampak "7% siap".
                'terisi' => $lengkap + $sebagian,
                'percent' => $total > 0 ? (int) round(($lengkap + $sebagian) / $total * 100) : 0,
            ],
            'tables' => $tables,
        ];
    }

    /**
     * Kode jenis program (config lkps.jenjang) prodi terpilih; null bila
     * tidak ada prodi terpilih atau jenjangnya tidak ada di templat LKPS.
     */
    private function jenisProgram(?string $programStudiId): ?string
    {
        if ($programStudiId === null) {
            return null;
        }

        $jenjang = app(SaptoProgramStudiRepository::class)->programStudis()
            ->firstWhere('id', $programStudiId)['jenjang'] ?? null;

        return config('lkps.jenjang')[(string) $jenjang] ?? null;
    }

    /**
     * @param  array{0: class-string, 1: string}  $handler
     * @return array<string, mixed>|null null bila respons bukan 2xx / bukan JSON objek
     */
    private function panggil(Request $induk, ?int $ts, array $handler): ?array
    {
        $query = array_filter([
            'ts' => $ts,
            'fakultas_id' => $induk->query('fakultas_id'),
            'program_studi_id' => $induk->query('program_studi_id'),
        ], fn ($nilai): bool => $nilai !== null);

        // Request baru per tabel, bukan $induk yang dipakai bersama: controller
        // boleh saja memutasi request-nya. Sesi + resolver user disalin karena
        // SaptoUserContext::fromRequest() membaca identitas dari sesi;
        // header pemilih prodi ikut supaya korprodi rangkap dapat prodi yang sama.
        $sub = Request::create($induk->path(), 'GET', $query);
        $sub->setLaravelSession($induk->session());
        $sub->setUserResolver($induk->getUserResolver());

        if ($induk->headers->has('X-SAPTO-Program-Studi-Id')) {
            $sub->headers->set('X-SAPTO-Program-Studi-Id', $induk->header('X-SAPTO-Program-Studi-Id'));
        }

        [$controller, $method] = $handler;
        $respons = app()->call([app($controller), $method], ['request' => $sub]);

        if (! $respons instanceof JsonResponse || ! $respons->isSuccessful()) {
            return null;
        }

        $data = $respons->getData(true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array{id: string, status: string, rows: int, note: string|null}
     */
    private function klasifikasi(string $id, array $envelope): array
    {
        // Tabel 2.b tidak memakai envelope generik: barisnya di 'data' dan
        // tidak ada source/notice.
        $baris = $envelope['rows'] ?? $envelope['data'] ?? [];
        $rows = is_array($baris) ? count($baris) : 0;

        if ($rows === 0) {
            $notice = $envelope['notice'] ?? null;

            // Tabel per-prodi yang dibuka di lingkup fakultas/universitas
            // sengaja kosong sampai prodi dipilih -- bukan data yang kurang.
            // Tidak ada penanda terstruktur di envelope; semua repository
            // tabel semacam ini memakai frasa yang sama di notice-nya.
            if (is_string($notice) && stripos($notice, 'disusun per program studi') !== false) {
                return $this->baris($id, 'perlu_prodi', 0, $notice);
            }

            return $this->baris($id, 'kosong', 0, is_string($notice) && $notice !== '' ? $notice : null);
        }

        // source.template: baris tabel hanya label/nol -- templat isian manual
        // (mis. 4 Penggunaan Dana, 5.c Kepuasan) atau tabel agregat yang semua
        // jumlahnya 0 di lingkup ini. Dulu dihitung "terisi" dan menaikkan
        // persentase kelengkapan tanpa ada data.
        // Catatan lengkapnya ada di notice tabel itu sendiri.
        if (($envelope['source']['template'] ?? false) === true) {
            return $this->baris($id, 'kosong', 0, self::PERLU_ISI_MANUAL);
        }

        $sebagian = ($envelope['source']['partial'] ?? false) === true;

        return $this->baris($id, $sebagian ? 'sebagian' : 'lengkap', $rows, null);
    }

    /**
     * @return array{id: string, status: string, rows: int, note: string|null}
     */
    private function baris(string $id, string $status, int $rows, ?string $note): array
    {
        return ['id' => $id, 'status' => $status, 'rows' => $rows, 'note' => $note];
    }

    /**
     * Tiap tabel punya default TS sendiri bila query tanpa ts (placeholder
     * memakai tahun berjalan, sisanya tahun terbaru di database); yang paling
     * banyak dipakai itulah yang mewakili "TS" cincin ini.
     *
     * @param  array<int, int>  $daftar
     */
    private function tsDominan(array $daftar): ?int
    {
        if ($daftar === []) {
            return null;
        }

        $hitung = array_count_values($daftar);
        arsort($hitung);

        return (int) array_key_first($hitung);
    }

    /**
     * Sumber yang sama dengan badge topbar (PdUnsriHealthCheck::lastSync):
     * batch sinkronisasi PD UNSRI terakhir yang selesai dan tidak dibatalkan.
     * Dibaca langsung dari job_batches, bukan lewat PdUnsriHealthCheck, karena
     * yang itu bisa memicu probe jaringan ke gateway bila cache health kosong.
     */
    private function lastSyncAt(): ?string
    {
        try {
            $selesai = DB::table('job_batches')
                ->where('name', PdUnsriSyncDispatcher::BATCH_NAME)
                ->whereNull('cancelled_at')
                ->whereNotNull('finished_at')
                ->max('finished_at');
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $selesai ? now()->setTimestamp((int) $selesai)->toIso8601String() : null;
    }
}
