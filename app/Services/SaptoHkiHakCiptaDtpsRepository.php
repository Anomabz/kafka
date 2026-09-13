<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian II: HKI (Hak Cipta,
 * Desain Produk Industri, dll.). Sama bentuk & sumber dengan
 * {@see SaptoHkiPatenDtpsRepository} (Bagian I): tabel LISTING dari
 * sister_detail_kekayaan_intelektual, DTPS lewat roster
 * {@see ResolvesPublikasiDtpsRoster} sama seperti Tabel 3.b.4.
 *
 * Baris difilter id_jenis_publikasi 43/44 (Hak cipta nasional/internasional)
 * DAN id_kategori_capaian_luaran BUKAN '1' -- kode kategori 1 (Produk
 * Teknologi Tepat Guna) sudah masuk Bagian III (SaptoTeknologiDtpsRepository),
 * jadi harus dikecualikan di sini supaya judul yang sama tidak dobel di dua
 * bagian (34 baris di data nyata beririsan, sudah dicek sebelum implementasi).
 * "Keterangan" sama persis Bagian I: jenis_publikasi + "No. {nomor_paten}"
 * (kalau ada) + pemberi_paten -- field nomor_paten/pemberi_paten dipakai
 * generik utk semua jenis HKI di SISTER, bukan cuma paten (sudah
 * diverifikasi berisi nomor registrasi hak cipta).
 */
class SaptoHkiHakCiptaDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Kode id_jenis_publikasi Hak Cipta (nasional/internasional). */
    private const HAK_CIPTA_CODES = ['43', '44'];

    /** Kode kategori "Produk Teknologi Tepat Guna" -- sudah dihitung di Bagian III. */
    private const KATEGORI_TEKNOLOGI_TEPAT_GUNA = '1';

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'luaran', 'label' => 'Luaran Penelitian dan PkM', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
        ['key' => 'keterangan', 'label' => 'Keterangan', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();

        if ($programStudi === null) {
            return $this->noticeEnvelope($ts, $fakultas, $programStudi,
                'Tabel 3.b.7 Bagian II disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar HKI (Hak Cipta, Desain Produk Industri, dll.).');
        }

        $years = [$ts - 2, $ts - 1, $ts];
        $jenjang = $this->jenjangFilter();

        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = $scopeProdiIds === [] ? [] : $this->publikasiDtpsIds($year, $scopeProdiIds, $jenjang);
        }

        $luaranRows = $this->luaranRows($years);

        $items = [];
        foreach ($years as $year) {
            $dtpsIds = array_flip($dtpsIdsByYear[$year]);

            foreach ($luaranRows[$year] ?? [] as $row) {
                if (! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIds)) {
                    continue;
                }

                $items[] = [
                    'luaran' => $row['luaran'],
                    'tahun' => $year,
                    'keterangan' => $row['keterangan'],
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['tahun'] <=> $a['tahun'] ?: $a['luaran'] <=> $b['luaran']);

        $rows = collect($items)
            ->values()
            ->map(fn (array $row, int $index): array => ['no' => $index + 1] + $row)
            ->all();

        return [
            'table_code' => '3.b.7',
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Hak Cipta, Desain Produk Industri, dll.)',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'metrics' => $this->metrics($rows, $ts),
            'notice' => null,
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * Jenjang gabungan varian akademik + vokasi, sama seperti Bagian I/III/IV.
     *
     * @return array<int, string>
     */
    private function jenjangFilter(): array
    {
        return ['S1', 'S2', 'S3', 'D3', 'D4', 'Sarjana Terapan', 'Magister Terapan', 'Doktor Terapan'];
    }

    /**
     * Judul HKI hak cipta/desain dari sister_detail_kekayaan_intelektual,
     * dedup per id, dikelompokkan per tahun. Mengecualikan kategori
     * "Produk Teknologi Tepat Guna" (sudah masuk Bagian III).
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function luaranRows(array $years): array
    {
        $cacheKey = 'sapto:hki-hak-cipta-dtps:luaran:v1:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $byId = [];

            foreach (DB::table('sister_detail_kekayaan_intelektual')
                ->select('id', 'judul', 'tanggal', 'jenis_publikasi', 'nomor_paten', 'pemberi_paten', 'penulis')
                ->whereIn('id_jenis_publikasi', self::HAK_CIPTA_CODES)
                ->where(function ($query): void {
                    $query->whereNull('id_kategori_capaian_luaran')
                        ->orWhere('id_kategori_capaian_luaran', '<>', self::KATEGORI_TEKNOLOGI_TEPAT_GUNA);
                })
                ->whereRaw("substr(tanggal,1,4) in ({$yearPlaceholders})", $yearStrings)
                ->cursor() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $row) {
                $year = (int) substr((string) $row->tanggal, 0, 4);

                if (! isset($byYear[$year])) {
                    continue;
                }

                $byYear[$year][] = [
                    'luaran' => (string) $row->judul,
                    'keterangan' => $this->keterangan($row),
                    'id_sdm_list' => $this->dosenIdsFromPenulis($row->penulis),
                ];
            }

            return $byYear;
        });
    }

    private function keterangan(object $row): string
    {
        $nomorPaten = trim((string) ($row->nomor_paten ?? ''));

        $parts = array_filter([
            trim((string) ($row->jenis_publikasi ?? '')),
            $nomorPaten !== '' ? "No. {$nomorPaten}" : '',
            trim((string) ($row->pemberi_paten ?? '')),
        ], fn (string $part): bool => $part !== '');

        return implode(', ', $parts);
    }

    /**
     * @return array<int, string>
     */
    private function dosenIdsFromPenulis(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn (mixed $penulis): bool => is_array($penulis) && ($penulis['jenis'] ?? null) === 'Dosen' && ! empty($penulis['id_sdm']))
            ->map(fn (array $penulis): string => (string) $penulis['id_sdm'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $idSdmList
     * @param  array<string, int>  $dtpsIdsFlipped
     */
    private function hasDtpsAuthor(array $idSdmList, array $dtpsIdsFlipped): bool
    {
        foreach ($idSdmList as $id) {
            if (isset($dtpsIdsFlipped[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, value: int|float|string}>
     */
    private function metrics(array $rows, int $ts): array
    {
        $tsCount = collect($rows)->where('tahun', $ts)->count();

        return [
            ['label' => 'Total judul (TS-2..TS)', 'value' => count($rows)],
            ['label' => 'Judul (TS)', 'value' => $tsCount],
            ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
        ];
    }

    private function noticeEnvelope(int $ts, ?array $fakultas, ?array $programStudi, string $notice): array
    {
        return [
            'table_code' => '3.b.7',
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Hak Cipta, Desain Produk Industri, dll.)',
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [],
            'rows' => [],
            'metrics' => [],
            'notice' => $notice,
            'source' => $this->sourcePayload(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Cache::remember('sapto:hki-hak-cipta-dtps:years:v1', $this->cacheTtl(), function (): array {
            $rows = DB::select("
                select distinct substr(nama_tahun_ajaran,1,4) as thn
                from list_penugasan_dosen
                where a_sp_homebase = '1'
                  and nama_tahun_ajaran is not null
                  and nama_tahun_ajaran <> ''
                order by thn desc
            ");

            return array_values(array_filter(
                array_map(fn (object $row): int => (int) $row->thn, $rows),
                fn (int $year): bool => $year >= 2000 && $year <= 2100
            ));
        });
    }

    private function sourcePayload(): array
    {
        return [
            'name' => 'sister_detail_kekayaan_intelektual & sister_list_penugasan (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}
