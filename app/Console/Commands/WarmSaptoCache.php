<?php

namespace App\Console\Commands;

use App\Services\SaptoDashboardRepository;
use App\Services\SaptoDosenPembimbingTaRepository;
use App\Services\SaptoDosenTetapRepository;
use App\Services\SaptoDosenTidakTetapRepository;
use App\Services\SaptoIpkLulusanRepository;
use App\Services\SaptoKerjasamaPendidikanRepository;
use App\Services\SaptoKerjasamaPenelitianRepository;
use App\Services\SaptoKerjasamaPkmRepository;
use App\Services\SaptoPagelaranDtpsRepository;
use App\Services\SaptoPenelitianDtpsRepository;
use App\Services\SaptoPrestasiAkademikRepository;
use App\Services\SaptoPrestasiNonAkademikRepository;
use App\Services\SaptoProgramStudiRepository;
use App\Services\SaptoPublikasiIlmiahDtpsRepository;
use App\Services\SaptoWaktuTungguLulusanRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Isi cache tabel-tabel SAPTO di muka supaya pengunjung pertama tidak
 * menanggung biaya hitung dingin. Sumbernya query berat yang tidak bisa
 * ditolong index (mis. dosen-pembimbing-ta ~4 detik: full scan 400rb baris
 * dengan `lower(...) like '%...%'` + count(distinct) + group by), jadi
 * satu-satunya cara agar "buka pertama langsung cepat" adalah menghitungnya
 * sebelum ada yang membuka.
 *
 * Discope universitas (fakultas/prodi = null) — itu yang dilihat akun admin,
 * sekaligus mengisi sub-cache yang tidak bergantung scope (mis. daftar judul
 * publikasi/penelitian per tahun) sehingga korprodi pun ikut lebih cepat.
 *
 * ponytail: cukup scope universitas. Menghangatkan 97 prodi x 8 tabel makan
 * menit-menit; tambahkan --prodi kalau nanti korprodi mengeluh lambat.
 */
class WarmSaptoCache extends Command
{
    protected $signature = 'sapto:warm {--prodi : Panaskan juga scope tiap program studi yang punya akun korprodi}';

    protected $description = 'Hitung ulang cache ringkasan tabel SAPTO supaya load pertama cepat';

    /**
     * Dashboard didahulukan: dia memanggil banyak tabel sekaligus, jadi
     * sub-cache-nya menguntungkan sisa daftar di bawahnya.
     */
    private const REPOSITORIES = [
        'dashboard' => SaptoDashboardRepository::class,
        'dosen-pembimbing-ta' => SaptoDosenPembimbingTaRepository::class,
        'dosen-tetap-pt' => SaptoDosenTetapRepository::class,
        'dosen-tidak-tetap' => SaptoDosenTidakTetapRepository::class,
        'ipk-lulusan' => SaptoIpkLulusanRepository::class,
        'kerjasama-pendidikan' => SaptoKerjasamaPendidikanRepository::class,
        'kerjasama-penelitian' => SaptoKerjasamaPenelitianRepository::class,
        'kerjasama-pkm' => SaptoKerjasamaPkmRepository::class,
        'penelitian-dtps' => SaptoPenelitianDtpsRepository::class,
        'publikasi-ilmiah-dtps' => SaptoPublikasiIlmiahDtpsRepository::class,
        'pagelaran-dtps' => SaptoPagelaranDtpsRepository::class,
        'waktu-tunggu' => SaptoWaktuTungguLulusanRepository::class,
        'prestasi-akademik' => SaptoPrestasiAkademikRepository::class,
        'prestasi-non-akademik' => SaptoPrestasiNonAkademikRepository::class,
    ];

    public function handle(): int
    {
        $started = microtime(true);

        // Daftar fakultas/prodi dipakai setiap halaman dibuka.
        $this->warm('referensi fakultas & prodi', fn () => app(SaptoProgramStudiRepository::class)->programStudis());

        foreach (self::REPOSITORIES as $name => $class) {
            $this->warm($name, fn () => app($class)->summary(null, null, null));
        }

        if ($this->option('prodi')) {
            $this->warmProdiScopes();
        }

        $this->info(sprintf('Selesai dalam %.1f detik.', microtime(true) - $started));

        return self::SUCCESS;
    }

    /**
     * Scope universitas saja tidak menolong korprodi: tabel seperti
     * dosen-pembimbing-ta memfilter prodi di dalam SQL, jadi tiap prodi punya
     * cache sendiri dan pemakainya menanggung ~5 detik saat pertama buka.
     * Dipanaskan per prodi yang benar-benar punya akun korprodi (~97), bukan
     * seluruh 137 prodi, supaya tidak membuang waktu untuk prodi tanpa
     * pengguna. Berat (~menit), makanya hanya dipakai dari cron, bukan saat
     * container start.
     */
    private function warmProdiScopes(): void
    {
        $kodes = DB::table('korprodi')->distinct()->pluck('kode_program_studi')
            ->map(fn (mixed $kode): string => (string) $kode)->all();

        $prodis = app(SaptoProgramStudiRepository::class)->programStudis()
            ->filter(fn (array $p): bool => in_array((string) $p['kode'], $kodes, true))
            ->values();

        $this->line(sprintf('  -- scope korprodi: %d program studi --', $prodis->count()));

        foreach ($prodis as $prodi) {
            $started = microtime(true);

            foreach (self::REPOSITORIES as $class) {
                try {
                    app($class)->summary(null, null, (string) $prodi['id']);
                } catch (Throwable) {
                    // Satu prodi/tabel bermasalah tidak boleh menghentikan sisanya.
                }
            }

            $this->line(sprintf('  %-40s %6.0f ms', mb_strimwidth((string) $prodi['nama'], 0, 40), (microtime(true) - $started) * 1000));
        }
    }

    /**
     * Satu tabel yang gagal (mis. tabel sumber belum disinkron) tidak boleh
     * membatalkan pemanasan tabel lain.
     */
    private function warm(string $name, callable $callback): void
    {
        $started = microtime(true);

        try {
            $callback();
            $this->line(sprintf('  %-24s %6.0f ms', $name, (microtime(true) - $started) * 1000));
        } catch (Throwable $e) {
            $this->warn(sprintf('  %-24s GAGAL: %s', $name, $e->getMessage()));
        }
    }
}
