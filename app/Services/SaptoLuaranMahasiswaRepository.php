<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use App\Services\Concerns\ResolvesStudentAuthors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Basis empat bagian Tabel 8.f.4) Luaran Penelitian/PkM yang Dihasilkan
 * Mahasiswa (I Paten, II Hak Cipta dsb., III Teknologi/Produk/Karya Seni,
 * IV Buku/Book Chapter).
 *
 * Baris = satu karya di SISTER yang punya minimal satu PENULIS MAHASISWA dari
 * program studi di lingkup (lihat ResolvesStudentAuthors). Jenis karya per
 * bagian sama dengan padanannya di Tabel 3.b.7 (luaran DTPS).
 *
 * Sebelum 2026-09-30 keempat tabel ini menampilkan karya DOSEN (salinan
 * Tabel 3.b.7) sebagai luaran mahasiswa.
 *
 * Kelengkapan bergantung pada sumber: hanya karya yang rinciannya (daftar
 * penulis) ada di PD UNSRI yang bisa diperiksa, dan mahasiswa hanya
 * terhitung bila dosen mencantumkannya sebagai penulis di SISTER.
 */
abstract class SaptoLuaranMahasiswaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;
    use ResolvesStudentAuthors;

    private const COLUMNS = [
        ['key' => 'no', 'label' => 'No', 'align' => 'left'],
        ['key' => 'luaran', 'label' => 'Luaran Penelitian dan PkM', 'align' => 'left'],
        ['key' => 'nama_mahasiswa', 'label' => 'Mahasiswa', 'align' => 'left'],
        ['key' => 'tahun', 'label' => 'Tahun (YYYY)', 'align' => 'left'],
        ['key' => 'keterangan', 'label' => 'Keterangan', 'align' => 'left'],
    ];

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    /** Mis. "HKI (Paten, Paten Sederhana)". */
    abstract protected function partName(): string;

    /** Kunci cache pendek, mis. 'paten'. */
    abstract protected function slug(): string;

    /** sister_detail_kekayaan_intelektual atau sister_detail_publikasi. */
    abstract protected function sourceTable(): string;

    /**
     * Batasi baris sumber ke jenis karya bagian ini.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    abstract protected function filterJenis($query): void;

    /**
     * @param  object  $row  baris sumber
     */
    abstract protected function keterangan(object $row): string;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = [$ts - 2, $ts - 1, $ts];

        $numbered = [];
        $mahasiswa = [];

        foreach ($this->rows($years, $scopeProdiIds) as $index => $row) {
            foreach ($row['_mhs'] as $id) {
                $mahasiswa[$id] = true;
            }

            unset($row['_mhs']);
            $numbered[] = ['no' => $index + 1] + $row;
        }

        return [
            'table_code' => '8.f.4',
            'table_name' => 'Luaran Penelitian/PkM yang Dihasilkan Mahasiswa - '.$this->partName(),
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => self::COLUMNS,
            'rows' => $numbered,
            'metrics' => [
                ['label' => 'Luaran mahasiswa (TS-2..TS)', 'value' => count($numbered)],
                ['label' => 'Luaran mahasiswa (TS)', 'value' => count(array_filter($numbered, fn (array $r): bool => $r['tahun'] === $ts))],
                ['label' => 'Mahasiswa penulis', 'value' => count($mahasiswa)],
            ],
            'notice' => 'Dihitung dari karya di SISTER yang mencantumkan mahasiswa program studi ini sebagai penulis. Karya yang rinciannya belum tersedia di PD UNSRI, atau yang mahasiswanya tidak dicantumkan dosen di SISTER, tidak terhitung: periksa dan lengkapi secara manual.',
            'source' => ['name' => $this->sourceTable().' + list_mahasiswa (sinkronisasi PD UNSRI)', 'partial' => true],
        ];
    }

    /**
     * @param  list<int>  $years
     * @param  array<int, string>|null  $scopeProdiIds
     * @return list<array{luaran: string, nama_mahasiswa: string, tahun: int, keterangan: string, _mhs: list<string>}>
     */
    private function rows(array $years, ?array $scopeProdiIds): array
    {
        $key = 'sapto:luaran-mahasiswa:'.$this->slug().':v1:'.md5(json_encode([$years, $scopeProdiIds]));

        return Cache::remember($key, $this->cacheTtl(), function () use ($years, $scopeProdiIds): array {
            $yearStrings = array_map('strval', $years);
            $query = DB::table($this->sourceTable())
                ->whereRaw('substr(tanggal, 1, 4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings);
            $this->filterJenis($query);

            // Tabel detail SISTER bisa memuat beberapa baris untuk satu karya.
            $byId = [];
            foreach ($query->get() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $candidates = [];
            $allStudents = [];
            foreach ($byId as $row) {
                $students = $this->studentAuthors($row->penulis ?? null);

                if ($students !== []) {
                    $candidates[] = [$row, $students];
                    array_push($allStudents, ...$students);
                }
            }

            $prodiMap = $scopeProdiIds === null ? [] : $this->studentProdiMap($allStudents);
            $out = [];

            foreach ($candidates as [$row, $students]) {
                $inScope = $this->studentsInScope($students, $prodiMap, $scopeProdiIds);

                if ($inScope === []) {
                    continue;
                }

                $names = array_values(array_unique(array_filter(array_column($inScope, 'nama'))));
                $judul = trim((string) ($row->judul ?? '')) ?: trim((string) ($row->judul_artikel ?? ''));

                $out[] = [
                    'luaran' => $judul !== '' ? $judul : '-',
                    'nama_mahasiswa' => $names !== [] ? implode(', ', $names) : '-',
                    'tahun' => (int) substr((string) $row->tanggal, 0, 4),
                    'keterangan' => $this->keterangan($row),
                    '_mhs' => array_map(fn (array $s): string => $s['id_peserta_didik'] ?? ('nim:'.$s['nim']), $inScope),
                ];
            }

            usort($out, fn (array $a, array $b): int => [$b['tahun'], $a['luaran']] <=> [$a['tahun'], $b['luaran']]);

            return $out;
        });
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:luaran-mahasiswa:'.$this->slug().':years:v1', $this->cacheTtl(), function (): array {
            $query = DB::table($this->sourceTable())->selectRaw('distinct substr(tanggal, 1, 4) as thn');
            $this->filterJenis($query);

            return collect($query->get())
                ->map(fn (object $r): int => (int) $r->thn)
                ->filter(fn (int $y): bool => $y >= 2000 && $y <= 2100)
                ->sortDesc()
                ->values()
                ->all();
        });
    }
}
