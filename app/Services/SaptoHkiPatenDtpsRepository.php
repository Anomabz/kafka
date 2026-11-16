<?php

namespace App\Services;

use App\Services\Concerns\ResolvesPublikasiDtpsRoster;
use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 3.b.7) Luaran Penelitian/PkM Lainnya -- Bagian I: HKI (Paten, Paten
 * Sederhana).
 *
 * Alur:
 *  1. Pilih prodi.
 *  2. Ambil DTPS per tahun TS-2..TS lewat ResolvesPublikasiDtpsRoster --
 *     definisi sama dengan Tabel 3.a.1 & 3.b.4 (homebase, ikatan kerja
 *     tetap A/B/F/H/I/N, id_prodi terdaftar di program_studi). Semua jenjang.
 *  3. Ambil HKI dari sister_detail_kekayaan_intelektual (id_jenis_publikasi
 *     41 = Paten nasional, 42 = Paten internasional). SISTER tidak punya kode
 *     terpisah untuk Paten Sederhana -- diinput sebagai Paten nasional (41),
 *     dan nomor_paten (awalan IDP/IDS) hampir selalu kosong sehingga tidak
 *     bisa dibedakan.
 *  4. detail.id_list -> sister_list_kekayaan_intelektual.id -> id_sdm.
 *  5. Cocokkan id_sdm dengan DTPS prodi (id_dosen = id_sdm), tampilkan HKI.
 *
 * Deduplikasi per detail.id sudah cukup: terverifikasi tidak ada paten dengan
 * judul sama tapi id berbeda (88 id = 88 judul). Baris dengan id sama berulang
 * di detail/list (per inventor / sinkronisasi ganda) digabung, id_sdm-nya
 * dikumpulkan.
 */
class SaptoHkiPatenDtpsRepository
{
    use ResolvesPublikasiDtpsRoster;
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;

    /** Kode id_jenis_publikasi HKI Paten (nasional/internasional). */
    private const PATEN_CODES = ['41', '42'];

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
                'Tabel 3.b.7 Bagian I disusun per program studi. Pilih satu program studi pada filter di atas untuk menampilkan daftar HKI (Paten, Paten Sederhana).');
        }

        $years = [$ts - 2, $ts - 1, $ts];

        // 1) DTPS per tahun (definisi bersama 3.a.1/3.b.4), semua jenjang.
        $dtpsIdsByYear = [];
        foreach ($years as $year) {
            $dtpsIdsByYear[$year] = empty($scopeProdiIds)
                ? []
                : array_flip($this->publikasiDtpsIds($year, $scopeProdiIds, []));
        }

        // 2) HKI + id_sdm dari list_kekayaan_intelektual.
        $luaranRows = $this->luaranRows($years);

        // 3) Cocokkan id_sdm dengan DTPS prodi.
        $items = [];
        foreach ($years as $year) {
            foreach ($luaranRows[$year] ?? [] as $row) {
                if (! $this->hasDtpsAuthor($row['id_sdm_list'], $dtpsIdsByYear[$year])) {
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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Paten, Paten Sederhana)',
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
     * HKI paten dari sister_detail_kekayaan_intelektual. Dosen (id_sdm)
     * diambil lewat detail.id_list -> sister_list_kekayaan_intelektual.id.
     * Dedup per detail.id; id_sdm dari semua baris list yang terhubung
     * dikumpulkan.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<int, array{luaran: string, keterangan: string, id_sdm_list: array<int, string>}>>
     */
    private function luaranRows(array $years): array
    {
        $cacheKey = 'sapto:hki-paten-dtps:luaran:v2:'.md5(json_encode($years));

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map(fn (int $y): string => (string) $y, $years);
            $yearPlaceholders = implode(',', array_fill(0, count($yearStrings), '?'));

            $byId = [];

            $query = DB::table('sister_detail_kekayaan_intelektual as d')
                ->join('sister_list_kekayaan_intelektual as l', 'l.id', '=', 'd.id_list')
                ->select(
                    'd.id',
                    'd.judul',
                    'd.tanggal',
                    'd.jenis_publikasi',
                    'd.nomor_paten',
                    'd.pemberi_paten',
                    'l.id_sdm'
                )
                ->whereIn('d.id_jenis_publikasi', self::PATEN_CODES)
                ->whereRaw("substr(d.tanggal,1,4) in ({$yearPlaceholders})", $yearStrings);

            foreach ($query->cursor() as $row) {
                $key = (string) $row->id;

                $byId[$key] ??= ['row' => $row, 'id_sdm_list' => []];

                $idSdm = trim((string) ($row->id_sdm ?? ''));
                if ($idSdm !== '') {
                    $byId[$key]['id_sdm_list'][$idSdm] = $idSdm;
                }
            }

            $byYear = array_fill_keys($years, []);

            foreach ($byId as $item) {
                $row = $item['row'];
                $year = (int) substr((string) $row->tanggal, 0, 4);

                if (! isset($byYear[$year])) {
                    continue;
                }

                $byYear[$year][] = [
                    'luaran' => (string) $row->judul,
                    'keterangan' => $this->keterangan($row),
                    'id_sdm_list' => array_values($item['id_sdm_list']),
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
            'table_name' => 'Luaran Penelitian/PkM Lainnya - HKI (Paten, Paten Sederhana)',
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
        return Cache::remember('sapto:hki-paten-dtps:years:v1', $this->cacheTtl(), function (): array {
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
            'name' => 'sister_detail_kekayaan_intelektual, sister_list_kekayaan_intelektual & list_penugasan_dosen (sinkronisasi lokal)',
            'partial' => true,
        ];
    }
}