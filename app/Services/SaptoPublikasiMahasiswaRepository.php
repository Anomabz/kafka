<?php

namespace App\Services;

use App\Services\Concerns\ResolvesSaptoScope;
use App\Services\Concerns\ResolvesSaptoTs;
use App\Services\Concerns\ResolvesStudentAuthors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Basis dua varian Tabel 8.f.1 BAN-PT (publikasi ilmiah mahasiswa), padanan
 * mahasiswa dari Tabel 3.b.4 (publikasi DTPS): baris dan pemetaan jenis
 * publikasinya sama, bedanya yang dihitung adalah judul dengan minimal satu
 * PENULIS MAHASISWA dari program studi di lingkup (ResolvesStudentAuthors).
 *
 * - {@see SaptoPublikasiIlmiahMahasiswaRepository} program akademik (S/M/D).
 * - {@see SaptoPagelaranMahasiswaRepository} program terapan (STr/MTr/DTr).
 *
 * Sumber: sister_detail_publikasi, satu-satunya tabel publikasi PD UNSRI yang
 * memuat daftar penulis. Isinya hanya sebagian kecil dari seluruh publikasi
 * (sister_list_publikasi), jadi angka di sini adalah BATAS BAWAH -- notice
 * menyebut cakupannya apa adanya.
 */
abstract class SaptoPublikasiMahasiswaRepository
{
    use ResolvesSaptoScope;
    use ResolvesSaptoTs;
    use ResolvesStudentAuthors;

    public function __construct(
        private readonly SaptoProgramStudiRepository $client,
    ) {}

    abstract protected function tableName(): string;

    /** @return array<string, string> kategori => label baris */
    abstract protected function categories(): array;

    /** @return array<string, string> jenis_publikasi SISTER => kategori */
    abstract protected function jenisMap(): array;

    public function summary(?int $ts, ?string $fakultasId, ?string $programStudiId): array
    {
        $programStudis = $this->client->programStudis();
        [$programStudi, $fakultas] = $this->validateScope($programStudis, $fakultasId, $programStudiId);
        $scopeProdiIds = $this->scopeProdiIds($programStudis, $fakultasId, $programStudiId);

        $ts ??= $this->ts();
        $years = ['ts_2' => $ts - 2, 'ts_1' => $ts - 1, 'ts' => $ts];
        $categories = $this->categories();
        $jenisMap = $this->jenisMap();

        $counts = array_fill_keys(array_keys($categories), array_fill_keys(array_keys($years), 0));
        $byYearKey = array_flip($years);

        foreach ($this->publikasi(array_values($years), $scopeProdiIds) as $pub) {
            $category = $jenisMap[$pub['jenis']] ?? null;
            $yearKey = $byYearKey[$pub['tahun']] ?? null;

            if ($category !== null && $yearKey !== null) {
                $counts[$category][$yearKey]++;
            }
        }

        $rows = [];
        $no = 0;
        foreach ($categories as $key => $label) {
            $rows[] = ['no' => ++$no, 'jenis_publikasi' => $label] + $counts[$key] + ['jumlah' => array_sum($counts[$key])];
        }

        $total = [
            'ts_2' => array_sum(array_column($counts, 'ts_2')),
            'ts_1' => array_sum(array_column($counts, 'ts_1')),
            'ts' => array_sum(array_column($counts, 'ts')),
        ];
        $rows[] = ['no' => null, 'jenis_publikasi' => 'Jumlah'] + $total + ['jumlah' => array_sum($total)];

        [$detail, $list] = $this->coverage(array_values($years));

        return [
            'table_code' => '8.f.1',
            'table_name' => $this->tableName(),
            'ts' => $ts,
            'available_years' => $this->availableYears(),
            'fakultas' => $fakultas,
            'program_studi' => $programStudi,
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'left'],
                ['key' => 'jenis_publikasi', 'label' => 'Jenis Publikasi', 'align' => 'left'],
                ['key' => 'ts_2', 'label' => "Jumlah Judul TS-2 ({$years['ts_2']})", 'align' => 'num'],
                ['key' => 'ts_1', 'label' => "Jumlah Judul TS-1 ({$years['ts_1']})", 'align' => 'num'],
                ['key' => 'ts', 'label' => "Jumlah Judul TS ({$years['ts']})", 'align' => 'num'],
                ['key' => 'jumlah', 'label' => 'Jumlah', 'align' => 'num'],
            ],
            'rows' => $rows,
            'metrics' => [
                ['label' => 'Judul publikasi mahasiswa (TS-2..TS)', 'value' => array_sum($total)],
                ['label' => 'Judul publikasi mahasiswa (TS)', 'value' => $total['ts']],
                ['label' => 'Tahun ajaran (TS)', 'value' => $ts],
            ],
            'notice' => sprintf(
                'Angka minimal. Dihitung dari publikasi yang mencantumkan mahasiswa program studi ini sebagai penulis. Untuk TS-2..TS, daftar penulis baru tersedia di PD UNSRI untuk %s dari %s judul publikasi SISTER, jadi publikasi lain belum bisa diperiksa. Lengkapi secara manual.',
                number_format($detail, 0, ',', '.'),
                number_format($list, 0, ',', '.'),
            ),
            'source' => ['name' => 'sister_detail_publikasi + list_mahasiswa (sinkronisasi PD UNSRI)', 'partial' => true, 'template' => array_sum($total) === 0],
        ];
    }

    /**
     * Publikasi (sekali per id) dengan penulis mahasiswa di lingkup.
     *
     * @param  list<int>  $years
     * @param  array<int, string>|null  $scopeProdiIds
     * @return list<array{jenis: string, tahun: int}>
     */
    private function publikasi(array $years, ?array $scopeProdiIds): array
    {
        $key = 'sapto:publikasi-mahasiswa:v1:'.md5(json_encode([$years, $scopeProdiIds]));

        return Cache::remember($key, $this->cacheTtl(), function () use ($years, $scopeProdiIds): array {
            $yearStrings = array_map('strval', $years);
            $byId = [];

            foreach (DB::table('sister_detail_publikasi')
                ->select('id', 'jenis_publikasi', 'tanggal', 'penulis')
                ->whereRaw('substr(tanggal, 1, 4) in ('.implode(',', array_fill(0, count($yearStrings), '?')).')', $yearStrings)
                ->get() as $row) {
                $byId[(string) $row->id] ??= $row;
            }

            $candidates = [];
            $allStudents = [];
            foreach ($byId as $row) {
                $students = $this->studentAuthors($row->penulis);

                if ($students !== []) {
                    $candidates[] = [$row, $students];
                    array_push($allStudents, ...$students);
                }
            }

            $prodiMap = $scopeProdiIds === null ? [] : $this->studentProdiMap($allStudents);
            $out = [];

            foreach ($candidates as [$row, $students]) {
                if ($this->studentsInScope($students, $prodiMap, $scopeProdiIds) !== []) {
                    $out[] = ['jenis' => trim((string) $row->jenis_publikasi), 'tahun' => (int) substr((string) $row->tanggal, 0, 4)];
                }
            }

            return $out;
        });
    }

    /**
     * Cakupan sumber untuk notice: judul yang punya rincian penulis vs
     * seluruh judul publikasi SISTER pada jendela TS-2..TS.
     *
     * @param  list<int>  $years
     * @return array{0: int, 1: int}
     */
    private function coverage(array $years): array
    {
        return Cache::remember('sapto:publikasi-mahasiswa:coverage:v1:'.implode('-', $years), $this->cacheTtl(), function () use ($years): array {
            $yearStrings = array_map('strval', $years);
            $in = implode(',', array_fill(0, count($yearStrings), '?'));

            return [
                (int) DB::table('sister_detail_publikasi')->whereRaw("substr(tanggal, 1, 4) in ({$in})", $yearStrings)->distinct()->count('id'),
                (int) DB::table('sister_list_publikasi')->whereRaw("substr(tanggal, 1, 4) in ({$in})", $yearStrings)->distinct()->count('id'),
            ];
        });
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return Cache::remember('sapto:publikasi-mahasiswa:years:v1', $this->cacheTtl(), fn (): array => collect(
            DB::table('sister_detail_publikasi')->selectRaw('distinct substr(tanggal, 1, 4) as thn')->get()
        )->map(fn (object $r): int => (int) $r->thn)->filter(fn (int $y): bool => $y >= 2000 && $y <= 2100)->sortDesc()->values()->all());
    }
}
