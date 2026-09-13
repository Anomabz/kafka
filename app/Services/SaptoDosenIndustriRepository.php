<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.a.5) Dosen Industri/Praktisi.
 *
 * PD UNSRI TIDAK BISA menjawab tabel ini, dan itu disengaja ditampilkan apa
 * adanya alih-alih menebak isinya:
 *
 *  - Tidak ada kolom NIDK di mana pun. list_penugasan_dosen hanya menyimpan
 *    nidn/nuptk, padahal NIDK justru penanda utama dosen industri/praktisi.
 *  - jenis_sdm di sister_sdm dan biodata_dosen hanya berisi "Dosen" dan
 *    "Tenaga Kependidikan"; status pegawai hanya PNS/CPNS/PPPK/NON ASN/
 *    Dokter Pendidik Klinis. Tidak ada kategori industri atau praktisi.
 *  - id_ikatan_kerja memang ada di list_penugasan_dosen, tapi seluruh tabel
 *    referensinya (ikatan_kerja, sister_ikatan_kerja, status_kepegawaian)
 *    kosong, jadi kode A/G/I/N/L tidak bisa diterjemahkan.
 *  - sister_list_riwayat_pekerjaan berisi pekerjaan SAMPINGAN dosen tetap
 *    (mis. "Universitas Sriwijaya - Wakil Dekan", "Tim Ahli / Konsultan"),
 *    bukan roster praktisi industri yang direkrut mengajar. Memakainya
 *    sebagai isi tabel akan menghasilkan daftar dosen tetap biasa -- salah
 *    di mata asesor.
 *
 * Jadi baris dikembalikan kosong, disertai notice dan jumlah kandidat yang
 * perlu diverifikasi manual. Roster sebenarnya harus datang dari SK dosen
 * industri/praktisi milik prodi.
 */
class SaptoDosenIndustriRepository
{
    use ResolvesSaptoScope;

    /** Jenjang yang wajib mengisi tabel ini menurut LKPS. */
    private const JENJANG_VOKASI = ['D3', 'D4', 'STr', 'Sarjana Terapan'];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
        private readonly SaptoTsResolver $tsResolver,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->tsResolver->ts();
        $kandidat = $this->kandidat($ts, $scopeProdiIds);

        return [
            'table_code' => '3.a.5',
            'table_name' => 'Dosen Industri/Praktisi',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            // Kolom persis Tabel 3.a.5 LKPS supaya bentuknya siap dipakai
            // begitu roster dosen industri tersedia dari prodi.
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'nama_dosen', 'label' => 'Nama Dosen Industri/Praktisi', 'align' => 'left'],
                ['key' => 'nidk', 'label' => 'NIDK', 'align' => 'left'],
                ['key' => 'perusahaan', 'label' => 'Perusahaan/Industri', 'align' => 'left'],
                ['key' => 'pendidikan_tertinggi', 'label' => 'Pendidikan Tertinggi', 'align' => 'left'],
                ['key' => 'bidang_keahlian', 'label' => 'Bidang Keahlian', 'align' => 'left'],
                ['key' => 'sertifikat', 'label' => 'Sertifikat Profesi/Kompetensi/Industri', 'align' => 'left'],
                ['key' => 'mata_kuliah', 'label' => 'Mata Kuliah yang Diampu', 'align' => 'left'],
                ['key' => 'sks', 'label' => 'Bobot Kredit (sks)', 'align' => 'right'],
            ],
            'rows' => [],
            'notice' => $this->notice($programStudi, $kandidat),
            'metrics' => [
                ['label' => 'Dosen industri/praktisi tercatat', 'value' => 0],
                ['label' => 'Kandidat untuk verifikasi manual', 'value' => $kandidat],
                ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
            ],
            'source' => [
                'name' => 'Pangkalan Data UNSRI -- tidak menyediakan penanda dosen industri/praktisi (NIDK)',
                'partial' => true,
            ],
        ];
    }

    /**
     * Dosen di lingkup ini yang punya pekerjaan MASIH BERJALAN di instansi
     * selain UNSRI. Bukan jawaban tabel, hanya petunjuk ke mana admin harus
     * memverifikasi. Dihitung lewat NIDN karena sister_* memakai id_sdm
     * sementara penugasan feeder memakai id_dosen/nidn.
     *
     * @param  array<int, string>|null  $scopeProdiIds
     */
    private function kandidat(int $ts, ?array $scopeProdiIds): int
    {
        $cacheKey = 'sapto:dosen-industri:kandidat:v1:'.md5(json_encode([$ts, $scopeProdiIds]));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $scopeProdiIds): int {
            $query = DB::table('sister_list_riwayat_pekerjaan as r')
                ->join('sister_sdm as s', 's.id_sdm', '=', 'r.id_sdm')
                ->join('list_penugasan_dosen as p', function ($join): void {
                    $join->on('p.nidn', '=', 's.nidn')->whereRaw("p.nidn <> ''");
                })
                ->whereRaw("coalesce(r.selesai_bekerja, '') in ('', '0000-00-00')")
                ->whereRaw("coalesce(r.instansi, '') <> ''")
                ->where('r.instansi', 'not like', '%Sriwijaya%')
                ->where('r.instansi', 'not like', '%UNSRI%')
                ->whereRaw('substr(p.nama_tahun_ajaran,1,4) = ?', [(string) $ts]);

            if ($scopeProdiIds !== null) {
                $query->whereIn('p.id_prodi', $scopeProdiIds);
            }

            return (int) $query->distinct()->count('p.id_dosen');
        });
    }

    /** @param  array<string, mixed>|null  $programStudi */
    private function notice(?array $programStudi, int $kandidat): string
    {
        $pesan = 'Data PD UNSRI belum bisa mengenali dosen industri/praktisi: tidak ada kolom NIDK, '
            .'jenis SDM hanya "Dosen" dan "Tenaga Kependidikan", dan tabel referensi ikatan kerja kosong. '
            .'Roster tabel ini harus diisi dari SK dosen industri/praktisi milik program studi.';

        if ($kandidat > 0) {
            $pesan .= sprintf(
                ' Sebagai titik awal verifikasi manual, %d dosen di lingkup ini tercatat punya pekerjaan '
                .'yang masih berjalan di instansi selain UNSRI.',
                $kandidat,
            );
        }

        // LKPS: tabel ini hanya diisi program Diploma Tiga/Sarjana Terapan.
        $jenjang = (string) ($programStudi['jenjang'] ?? '');

        if ($programStudi !== null && ! in_array($jenjang, self::JENJANG_VOKASI, true)) {
            $pesan .= ' Catatan LKPS: tabel ini hanya diisi program Diploma Tiga/Sarjana Terapan, '
                .'sedangkan program studi terpilih berjenjang '.($jenjang ?: 'lain').'.';
        }

        return $pesan;
    }

    /**
     * Sumber tahun disamakan dengan Dosen Tetap/Tidak Tetap supaya pemilih
     * tahun di halaman konsisten antar tabel dosen.
     *
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:dosen-industri:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(nama_tahun_ajaran,1,4) as thn
                from list_penugasan_dosen
                where nama_tahun_ajaran is not null and nama_tahun_ajaran <> ''
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }
}
