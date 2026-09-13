<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Basis Tabel 1 Kerjasama Tridharma (Pendidikan/Penelitian/PkM). Sumber:
 * daftar_mou (MoU tingkat institusi) digabung dengan daftar_pks (PKS
 * pelaksana). Subclass hanya menyuplai kata kunci & metadata per bidang;
 * struktur kolom, klasifikasi Tingkat, penyaringan status dokumen tak valid,
 * dan format waktu/durasi identik lintas ketiga tabel. Kerjasama bersifat
 * institusi, bukan per-program-studi, jadi fakultas/prodi hanya divalidasi
 * untuk konteks UI (unit aktif), tidak menyaring baris.
 *
 * Dua kolom BAN-PT tidak punya field sumber di kedua tabel:
 * - "Tingkat" diinferensi dari teks jenis_mitra/nama lembaga (penanda LN ->
 *   Internasional, penanda pemerintah daerah -> Wilayah/Lokal, selain itu ->
 *   Nasional). Heuristik teks, bukan field bertag eksplisit — perlu
 *   diverifikasi manual sebelum dipakai di dokumen akreditasi resmi.
 * - "Manfaat bagi PS yang Diakreditasi" adalah narasi khusus per program studi
 *   dan tidak tersimpan di sistem manapun, sehingga selalu dikosongkan (null,
 *   dirender "-") dan wajib diisi manual di luar SAPTO.
 *
 * ponytail: klasifikasi "termasuk bidang X?" pada daftar_pks berbasis kata
 * kunci Indonesia; baris berbahasa Inggris/istilah tak lazim bisa terlewat.
 * Naikkan ke pencocokan yang lebih kaya (mis. daftar sinonim, atau kolom
 * klasifikasi manual) kalau cakupan kata kunci ini terbukti kurang.
 */
abstract class SaptoKerjasamaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    private const LOCAL_MARKERS = ['dinas', 'kabupaten', 'kota ', 'pemkot', 'pemkab', 'pemda', 'provinsi', 'kecamatan', 'desa '];

    private const INTERNATIONAL_MARKERS = ['luar negeri', 'internasional', 'international'];

    private const PKS_INVALID_STATUS = [
        'tidak bisa cz tidak ada mou', 'mou blm ketemu', 'mou expired',
        'tidak ada mou', 'mou tdk ketemu', 'tdk ada pks', 'dokumen tidak ada', 'gagal',
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    abstract protected function tableCode(): string;

    abstract protected function tableName(): string;

    /** Label bidang untuk kartu metrik, mis. "pendidikan", "penelitian". */
    abstract protected function bidangLabel(): string;

    abstract protected function cachePrefix(): string;

    /**
     * Pola LIKE spesifik bidang untuk kegiatan_kerjasama di daftar_mou.
     * '%dharma%' (MoU tridharma umum, berlaku ke semua bidang) selalu
     * ditambahkan otomatis, subclass tidak perlu mengulanginya.
     *
     * @return array<int, string>
     */
    abstract protected function mouBidangPatterns(): array;

    /**
     * Kata kunci spesifik bidang untuk menyaring judul kegiatan di daftar_pks.
     *
     * @return array<int, string>
     */
    abstract protected function pksBidangKeywords(): array;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);

        $availableYears = $this->availableYears();

        // TS yang dibawa dari tabel lain (mis. Dosen Pembimbing TS-nya 2025)
        // bisa jadi bukan tahun yang ada MoU/PKS-nya di sini — kalau dipakai
        // apa adanya, respons balik ts=2025 tapi 2025 tak ada di
        // available_years, dropdown TS di klien jadi tak punya opsi buat
        // dipilih (terlihat seperti "belum kepilih"). Jatuhkan ke tahun
        // terbaru yang benar-benar ada di sini, sama seperti kalau ts kosong.
        if ($ts === null || ! in_array($ts, $availableYears, true)) {
            $ts = $this->latestYear();
        }

        $rows = collect($this->mouRows($ts))
            ->concat($this->pksRows($ts))
            ->sortBy('lembaga_mitra')
            ->values()
            ->map(function (array $row, int $index): array {
                $row['no'] = $index + 1;

                return $row;
            })
            ->values()
            ->all();

        return [
            'table_code' => $this->tableCode(),
            'table_name' => $this->tableName(),
            'ts' => $ts,
            'available_years' => $availableYears,
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'lembaga_mitra', 'label' => 'Lembaga Mitra', 'align' => 'left'],
                ['key' => 'tingkat_internasional', 'label' => 'Internasional', 'align' => 'left'],
                ['key' => 'tingkat_nasional', 'label' => 'Nasional', 'align' => 'left'],
                ['key' => 'tingkat_wilayah', 'label' => 'Wilayah/Lokal', 'align' => 'left'],
                ['key' => 'judul_kegiatan', 'label' => 'Judul Kegiatan Kerjasama', 'align' => 'left'],
                ['key' => 'manfaat_ps', 'label' => 'Manfaat bagi PS yang Diakreditasi', 'align' => 'left'],
                ['key' => 'waktu_durasi', 'label' => 'Waktu dan Durasi', 'align' => 'left'],
                ['key' => 'bukti_kerjasama', 'label' => 'Bukti Kerjasama', 'align' => 'left'],
                // align 'left' (bukan 'num') supaya tahun tidak diberi
                // pemisah ribuan oleh formatter angka locale id-ID di frontend.
                ['key' => 'tahun_berakhir', 'label' => 'Tahun Berakhir (YYYY)', 'align' => 'left'],
            ],
            'rows' => $rows,
            'metrics' => $this->metrics($rows),
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mouRows(int $ts): array
    {
        $cacheKey = "sapto:{$this->cachePrefix()}:mou:v1:{$ts}";
        $patterns = array_merge(['%dharma%'], $this->mouBidangPatterns());

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $patterns): array {
            return DB::table('daftar_mou')
                ->select('perguruan_tinggi_lembaga', 'no_kerjasama', 'mulai', 'berakhir', 'kegiatan_kerjasama', 'jenis_mitra')
                ->where(function ($query) use ($patterns): void {
                    foreach ($patterns as $pattern) {
                        $query->orWhere('kegiatan_kerjasama', 'like', $pattern);
                    }
                })
                ->whereRaw('(mulai is null or substr(mulai,1,4) <= ?)', [(string) $ts])
                ->whereRaw('(berakhir is null or substr(berakhir,1,4) >= ?)', [(string) $ts])
                ->get()
                ->map(fn (object $row): array => $this->buildRow(
                    lembagaMitra: (string) ($row->perguruan_tinggi_lembaga ?? ''),
                    jenisMitra: (string) ($row->jenis_mitra ?? ''),
                    judulKegiatan: (string) ($row->kegiatan_kerjasama ?? ''),
                    mulai: $row->mulai,
                    berakhir: $row->berakhir,
                    noKerjasama: (string) ($row->no_kerjasama ?? ''),
                ))
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pksRows(int $ts): array
    {
        $cacheKey = "sapto:{$this->cachePrefix()}:pks:v1:{$ts}";
        $keywords = $this->pksBidangKeywords();

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($ts, $keywords): array {
            return DB::table('daftar_pks')
                ->select('nama_perguruan_tinggi_lembaga', 'no_kerjasama', 'periode_mulai', 'periode_berakhir', 'kegiatan_kerjasama', 'jangka_waktu', 'jenis_mitra', 'status_sikerma')
                ->whereRaw('(periode_mulai is null or substr(periode_mulai,1,4) <= ?)', [(string) $ts])
                ->whereRaw('(periode_berakhir is null or substr(periode_berakhir,1,4) >= ?)', [(string) $ts])
                ->get()
                ->filter(fn (object $row): bool => $this->isValidAgreement((string) ($row->status_sikerma ?? ''))
                    && $this->matchesKeywords((string) ($row->kegiatan_kerjasama ?? ''), $keywords))
                ->map(fn (object $row): array => $this->buildRow(
                    lembagaMitra: (string) ($row->nama_perguruan_tinggi_lembaga ?? ''),
                    jenisMitra: (string) ($row->jenis_mitra ?? ''),
                    judulKegiatan: (string) ($row->kegiatan_kerjasama ?? ''),
                    mulai: $row->periode_mulai,
                    berakhir: $row->periode_berakhir,
                    noKerjasama: (string) ($row->no_kerjasama ?? ''),
                    jangkaWaktu: (string) ($row->jangka_waktu ?? ''),
                ))
                ->values()
                ->all();
        });
    }

    private function isValidAgreement(string $status): bool
    {
        $status = Str::lower($status);

        foreach (self::PKS_INVALID_STATUS as $marker) {
            if (str_contains($status, $marker)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesKeywords(string $kegiatan, array $keywords): bool
    {
        $text = Str::lower($kegiatan);

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildRow(
        string $lembagaMitra,
        string $jenisMitra,
        string $judulKegiatan,
        ?string $mulai,
        ?string $berakhir,
        string $noKerjasama,
        string $jangkaWaktu = '',
    ): array {
        $tingkat = $this->classifyTingkat($jenisMitra, $lembagaMitra);
        $tahunBerakhir = $berakhir !== null && $berakhir !== '' ? (int) substr($berakhir, 0, 4) : null;

        return [
            'lembaga_mitra' => $lembagaMitra !== '' ? $lembagaMitra : '-',
            'tingkat_internasional' => $tingkat['internasional'] ? '✓' : '',
            'tingkat_nasional' => $tingkat['nasional'] ? '✓' : '',
            'tingkat_wilayah' => $tingkat['wilayah'] ? '✓' : '',
            'judul_kegiatan' => $judulKegiatan !== '' ? $judulKegiatan : '-',
            // Tidak ada field sumber untuk manfaat per-PS; wajib diisi manual.
            'manfaat_ps' => null,
            'waktu_durasi' => $this->periodLabel($mulai, $berakhir, $jangkaWaktu),
            'bukti_kerjasama' => $noKerjasama !== '' ? "No. {$noKerjasama}" : '-',
            'tahun_berakhir' => $tahunBerakhir,
        ];
    }

    /**
     * @return array{internasional: bool, nasional: bool, wilayah: bool}
     */
    private function classifyTingkat(string $jenisMitra, string $namaLembaga): array
    {
        $haystack = Str::lower("{$jenisMitra} {$namaLembaga}");

        foreach (self::INTERNATIONAL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return ['internasional' => true, 'nasional' => false, 'wilayah' => false];
            }
        }

        // Penanda "LN" (kode jenis_mitra khas: "Perguruan Tinggi LN") sebagai kata utuh.
        if (preg_match('/\bln\b/', $haystack) === 1) {
            return ['internasional' => true, 'nasional' => false, 'wilayah' => false];
        }

        foreach (self::LOCAL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return ['internasional' => false, 'nasional' => false, 'wilayah' => true];
            }
        }

        return ['internasional' => false, 'nasional' => true, 'wilayah' => false];
    }

    private function periodLabel(?string $mulai, ?string $berakhir, string $jangkaWaktu): string
    {
        $start = $this->formatDate($mulai);
        $end = $this->formatDate($berakhir);

        if ($start !== null && $end !== null) {
            return "{$start} s.d. {$end}";
        }

        if ($start !== null) {
            return $jangkaWaktu !== '' ? "Mulai {$start} ({$jangkaWaktu})" : "Mulai {$start}";
        }

        return $jangkaWaktu !== '' ? $jangkaWaktu : '-';
    }

    private function formatDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = date_create($value);

        return $date instanceof \DateTime ? $date->format('d-m-Y') : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows): array
    {
        $internasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_internasional'] === '✓'));
        $nasional = count(array_filter($rows, fn (array $r): bool => $r['tingkat_nasional'] === '✓'));
        $wilayah = count(array_filter($rows, fn (array $r): bool => $r['tingkat_wilayah'] === '✓'));

        return [
            ['label' => "Total kerjasama {$this->bidangLabel()}", 'value' => count($rows)],
            ['label' => 'Internasional', 'value' => $internasional],
            ['label' => 'Nasional', 'value' => $nasional],
            ['label' => 'Wilayah/Lokal', 'value' => $wilayah],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember("sapto:{$this->cachePrefix()}:years:v1", $this->cacheTtl(), function (): array {
            return DB::table('daftar_mou')->whereNotNull('tahun')->distinct()->pluck('tahun')
                ->merge(DB::table('daftar_pks')->whereNotNull('tahun')->distinct()->pluck('tahun'))
                ->map(fn (mixed $year): int => (int) $year)
                ->push($this->latestYear())
                ->unique()
                ->filter(fn (int $year): bool => $year >= 2000 && $year <= 2100)
                ->sortDesc()
                ->values()
                ->all();
        });
    }

    /**
     * Dipakai juga untuk menyusun available_years di sini (tabel ini tidak
     * punya "tahun data" sendiri). Nilainya mengikuti TS bersama supaya tidak
     * lebih maju daripada tabel lain -- dulu dikunci manual ke 2025.
     */
    private function latestYear(): int
    {
        return $this->ts();
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'daftar_mou & daftar_pks (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
