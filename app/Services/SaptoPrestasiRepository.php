<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Basis dua varian Tabel 8.b BAN-PT:
 *
 * - {@see SaptoPrestasiAkademikRepository} "8.b.1) Prestasi Akademik Mahasiswa"
 * - {@see SaptoPrestasiNonAkademikRepository} "8.b.2) Prestasi Non-akademik Mahasiswa"
 *
 * Sumber: list_prestasi_mahasiswa. KLASIFIKASI akademik/non-akademik TIDAK
 * mengikuti nama_jenis_prestasi mentah -- terverifikasi banyak salah pilih
 * (PORPROV/PON/marching band berlabel "Sains", lomba karya tulis ilmiah
 * berlabel "Lain-lain"). Aturan (lihat classifyKategori()), berurutan:
 *  1. Kata kunci "bukan lomba" (mobility, exchange, magang, service learning,
 *     bootcamp) -> keikutsertaan program, bukan prestasi; tidak tampil di
 *     8.b.1 maupun 8.b.2.
 *  2. Kata kunci non-akademik (olahraga, seni, e-sport, MTQ, pramuka, duta,
 *     wirausaha/kewirausahaan -- mis. P2MW, sesuai keputusan pengguna --
 *     dst.) -> non-akademik, apa pun labelnya.
 *  3. Kata kunci akademik (ilmiah, esai, debat, olimpiade, PKM, inovasi,
 *     business plan, MUN, dst.) -> akademik, apa pun labelnya. Business plan
 *     tetap akademik karena dikenali lewat "business"/"bussines", diperiksa
 *     setelah aturan non-akademik tapi tidak memuat kata "wirausaha".
 *  4. Tanpa kata kunci: label "Sains" -> akademik; selain itu (Seni,
 *     Olahraga, Lain-lain, tak dikenal) -> non-akademik, supaya tetap tampil
 *     dan bisa dipindah manual saat finalisasi.
 * Kata pendek memakai \b (batas kata) agar "band" tidak cocok "Bandung",
 * "kti" tidak cocok "praktikum", "pon" tidak cocok "respon". "bridge"
 * sengaja tidak dipakai (bisa lomba desain jembatan); "Cabor Bridge"
 * tertangkap lewat "cabor".
 *
 * DEDUPLIKASI: prestasi tim tercatat satu baris per anggota. Di tabel LKPS
 * dihitung per KEGIATAN: (nama kegiatan ternormalisasi, tahun, tingkat,
 * prestasi) -- dilakukan SETELAH filter scope, jadi tim lintas prodi tetap
 * tampil di tabel tiap prodi anggotanya. Variasi ejaan nama lomba ("Esai" vs
 * "Essay") tidak digabung otomatis; rapikan manual.
 *
 * PRODI: list_prestasi_mahasiswa tidak punya id_prodi. Prodi dicari lewat
 * id_mahasiswa ke list_mahasiswa; dipilih registrasi TERAKHIR yang periode
 * masuknya <= tahun prestasi (mahasiswa S1 -> S2 di UNSRI: prestasi semasa
 * S1 tetap milik S1). Bila tidak ada yang memenuhi, dipakai registrasi
 * terbaru.
 *
 * Tingkat: "Nasional" -> Nasional, "Internasional" -> Internasional, selain
 * itu (Sekolah, Propinsi, Kab/kota, "Lainnya", tak dikenal) -> Lokal/Wilayah.
 * "Prestasi yang Dicapai" dari `peringkat` sebagai "Juara {n}"; kosong/0
 * ditampilkan "-".
 *
 * Catatan data: prestasi 2024-2025 hampir tidak diinput ke feeder
 * (2023: ~670 baris, 2024: 24, 2025: 50; semua "sudah sync") -- masalah
 * hulu, bukan sinkronisasi.
 *
 * Cache data mentah dipakai bersama kedua varian (klasifikasi dilakukan
 * setelah cache).
 */
abstract class SaptoPrestasiRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    public const AKADEMIK = 'akademik';

    public const NON_AKADEMIK = 'non_akademik';

    public const BUKAN_LOMBA = 'bukan_lomba';

    private const RE_BUKAN_LOMBA = '/mobility|exchange|magang|service learning|bootcamp/iu';

    private const RE_NON_AKADEMIK = '/porprov|porseni|\bpon\b|olahraga|olaharaga|rekreasi|kejuaraan|kejurnas|kejurprov|cabor|futsal|sepak ?bola|basket|\bvoli|badminton|bulu tangkis|tenis|silat|karate|taekwondo|judo|muay ?thai|tinju|renang|atletik|catur|e-?sport|mlbb|mobile legend|marching|paduan suara|\btari|\bband\b|musik|nyanyi|vokal|teater|\bfilm|fotogra|photograph|vlog|cerpen|puisi|sastra|\bseni\b|mtq|tilawah|pramuka|menwa|ketangkasan|\bduta\b|putri|bujang gadis|wirausaha/iu';

    private const RE_AKADEMIK = '/ilmiah|scien|writing|esai|essai|esay|essay|\bl?kti\b|paper|poster|debat|debate|speech|pidato|moot|peradilan semu|mediation|law fair|\blex\b|olimpiade|olympiad|onmipa|sains|\bpkm\b|pimnas|kreativitas mahasiswa|mahasiswa berprestasi|pilmapres|inovasi|innovat|inven|invan|creativity|ipitex|research|reseach|riset|robot|programming|hackathon|coding|ui\/ux|business|bussines|enterprise|studi kasus|case competition|karya tulis|engineering competition|education competition|micro macro|nursing|nurse|nutri|ujian nasional|seminar|kongres|\bmun\b|model united nations/iu';

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    abstract protected function tableCode(): string;

    abstract protected function tableName(): string;

    /**
     * Kategori LKPS varian ini: self::AKADEMIK atau self::NON_AKADEMIK.
     */
    abstract protected function kategori(): string;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 2, $ts - 1, $ts];
        $kategori = $this->kategori();

        $rows = $scopeProdiIds === []
            ? []
            : $this->prestasiRows($years)
                ->filter(fn (array $row): bool => $row['kategori'] === $kategori)
                ->filter(fn (array $row): bool => $scopeProdiIds === null || in_array($row['id_prodi'], $scopeProdiIds, true))
                // Satu baris per kegiatan (prestasi tim = satu baris).
                ->unique('dedup_key')
                ->sortBy([['tahun_sort', 'desc'], ['nama_kegiatan', 'asc']])
                ->values()
                ->map(fn (array $row, int $index): array => [
                    'no' => $index + 1,
                    'nama_kegiatan' => $row['nama_kegiatan'],
                    'tahun_perolehan' => $row['tahun_perolehan'],
                    'tingkat_lokal' => $row['tingkat'] === 'wilayah' ? '✓' : '',
                    'tingkat_nasional' => $row['tingkat'] === 'nasional' ? '✓' : '',
                    'tingkat_internasional' => $row['tingkat'] === 'internasional' ? '✓' : '',
                    'prestasi_dicapai' => $row['prestasi_dicapai'],
                ])
                ->all();

        return [
            'table_code' => $this->tableCode(),
            'table_name' => $this->tableName(),
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'nama_kegiatan', 'label' => 'Nama Kegiatan', 'align' => 'left'],
                ['key' => 'tahun_perolehan', 'label' => 'Waktu Perolehan (YYYY)', 'align' => 'left'],
                ['key' => 'tingkat_lokal', 'label' => 'Lokal/Wilayah', 'align' => 'left'],
                ['key' => 'tingkat_nasional', 'label' => 'Nasional', 'align' => 'left'],
                ['key' => 'tingkat_internasional', 'label' => 'Internasional', 'align' => 'left'],
                ['key' => 'prestasi_dicapai', 'label' => 'Prestasi yang Dicapai', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Semua baris prestasi TS-2..TS (semua jenis) dengan kategori LKPS, prodi,
     * dan kunci deduplikasi dilekatkan. Dipakai bersama kedua varian.
     *
     * @param  array<int, int>  $years
     * @return Collection<int, array{id_prodi: ?string, kategori: string, dedup_key: string, nama_kegiatan: string, tahun_perolehan: int, tahun_sort: int, tingkat: string, prestasi_dicapai: string}>
     */
    private function prestasiRows(array $years): Collection
    {
        $cacheKey = 'sapto:prestasi:rows:v3:'.md5(json_encode($years));

        $cached = Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);

            $prestasi = DB::table('list_prestasi_mahasiswa')
                ->select(
                    'id_prestasi',
                    'id_mahasiswa',
                    'id_jenis_prestasi',
                    'nama_jenis_prestasi',
                    'nama_prestasi',
                    'nama_tingkat_prestasi',
                    'peringkat',
                    'tahun_prestasi'
                )
                ->whereIn('tahun_prestasi', $yearStrings)
                ->orderBy('id_prestasi')
                ->get();

            $registrasi = $this->registrasiFor(
                $prestasi->pluck('id_mahasiswa')->unique()->filter()->values()->all()
            );

            return $prestasi
                ->map(function (object $row) use ($registrasi): array {
                    $tahun = (int) $row->tahun_prestasi;
                    $nama = trim((string) $row->nama_prestasi);
                    $tingkat = $this->classifyTingkat($row->nama_tingkat_prestasi);
                    $prestasiLabel = $this->prestasiLabel($row->peringkat);

                    return [
                        'id_prodi' => $this->prodiPadaTahun($registrasi[(string) $row->id_mahasiswa] ?? [], $tahun),
                        'kategori' => $this->classifyKategori($nama, $row->id_jenis_prestasi, $row->nama_jenis_prestasi),
                        'dedup_key' => implode('|', [$this->normalize($nama) ?: 'id:'.$row->id_prestasi, $tahun, $tingkat, $prestasiLabel]),
                        'nama_kegiatan' => $nama !== '' ? $nama : '-',
                        'tahun_perolehan' => $tahun,
                        'tahun_sort' => $tahun,
                        'tingkat' => $tingkat,
                        'prestasi_dicapai' => $prestasiLabel,
                    ];
                })
                ->values()
                ->all();
        });

        return collect($cached);
    }

    /**
     * Klasifikasi LKPS (lihat docblock kelas untuk urutan aturan).
     */
    private function classifyKategori(string $nama, mixed $idJenis, mixed $namaJenis): string
    {
        if (preg_match(self::RE_BUKAN_LOMBA, $nama)) {
            return self::BUKAN_LOMBA;
        }

        if (preg_match(self::RE_NON_AKADEMIK, $nama)) {
            return self::NON_AKADEMIK;
        }

        if (preg_match(self::RE_AKADEMIK, $nama)) {
            return self::AKADEMIK;
        }

        $isSains = (string) $idJenis === '1' || trim((string) $namaJenis) === 'Sains';

        return $isSains ? self::AKADEMIK : self::NON_AKADEMIK;
    }

    /**
     * Riwayat registrasi per mahasiswa dari list_mahasiswa, dibatasi ke
     * id_mahasiswa yang punya prestasi.
     *
     * @param  array<int, string>  $mahasiswaIds
     * @return array<string, array<int, array{periode: string, id_prodi: string}>>
     */
    private function registrasiFor(array $mahasiswaIds): array
    {
        if ($mahasiswaIds === []) {
            return [];
        }

        $result = [];

        foreach (DB::table('list_mahasiswa')
            ->select('id_mahasiswa', 'id_prodi', 'id_periode')
            ->whereIn('id_mahasiswa', $mahasiswaIds)
            ->cursor() as $row) {
            $result[(string) $row->id_mahasiswa][] = [
                'periode' => (string) ($row->id_periode ?? ''),
                'id_prodi' => (string) $row->id_prodi,
            ];
        }

        return $result;
    }

    /**
     * Registrasi terakhir yang periode masuknya <= tahun prestasi; bila tidak
     * ada, registrasi terbaru.
     *
     * @param  array<int, array{periode: string, id_prodi: string}>  $registrasi
     */
    private function prodiPadaTahun(array $registrasi, int $tahun): ?string
    {
        $best = null;
        $latest = null;

        foreach ($registrasi as $reg) {
            if ($latest === null || $reg['periode'] > $latest['periode']) {
                $latest = $reg;
            }

            $tahunMasuk = (int) substr($reg['periode'], 0, 4);
            if ($tahunMasuk > 0 && $tahunMasuk <= $tahun && ($best === null || $reg['periode'] > $best['periode'])) {
                $best = $reg;
            }
        }

        return ($best ?? $latest)['id_prodi'] ?? null;
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    private function classifyTingkat(?string $nama): string
    {
        $nama = trim((string) $nama);

        if ($nama === 'Internasional') {
            return 'internasional';
        }

        if ($nama === 'Nasional') {
            return 'nasional';
        }

        // Sekolah/Propinsi/Kecamatan/Kab-kota/"Lainnya"/nilai tak dikenal.
        return 'wilayah';
    }

    private function prestasiLabel(?string $peringkat): string
    {
        $n = (int) $peringkat;

        return $peringkat !== null && $peringkat !== '' && $n > 0 ? "Juara {$n}" : '-';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $lokal = count(array_filter($rows, fn (array $r): bool => $r['tingkat_lokal'] === '✓'));
        $nasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_nasional'] === '✓'));
        $internasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_internasional'] === '✓'));

        return [
            ['label' => 'Total prestasi (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Internasional', 'value' => $internasional],
            ['label' => 'Nasional', 'value' => $nasional],
            ['label' => 'Lokal/Wilayah', 'value' => $lokal],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:prestasi:years:v1', $this->cacheTtl(), function (): array {
            return DB::table('list_prestasi_mahasiswa')
                ->whereNotNull('tahun_prestasi')
                ->where('tahun_prestasi', '<>', '')
                ->distinct()
                ->pluck('tahun_prestasi')
                ->map(fn (mixed $year): int => (int) $year)
                ->filter(fn (int $year): bool => $year >= 2000 && $year <= 2100)
                ->sortDesc()
                ->values()
                ->all();
        });
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'list_prestasi_mahasiswa & list_mahasiswa (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}