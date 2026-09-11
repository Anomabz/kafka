<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 1 Kerjasama Tridharma - Pendidikan. Menutupi kasus nyata yang
 * ditemukan di data produksi: salah ketik "Pendidian" pada MoU, PKS tanpa
 * kata kunci pendidikan, status_sikerma yang menandai dokumen tak valid, dan
 * klasifikasi Tingkat dari teks jenis_mitra/nama lembaga.
 */
class SaptoKerjasamaPendidikanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);

        DB::table('program_studi')->insert([
            'id_prodi' => 'P1',
            'kode_program_studi' => '55201',
            'nama_program_studi' => 'Teknik Informatika',
            'status' => 'AKTIF',
            'id_jenjang_pendidikan' => 1,
            'nama_jenjang_pendidikan' => 'S1',
        ]);

        // Kerjasama bersifat institusi (lihat docblock repository), jadi prodi
        // di sini hanya perlu ada untuk melewati resolusi korprodi — tidak
        // menyaring baris hasil.
        \App\Models\Penugasan::create([
            'peran' => 'korprodi',
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'lingkup' => '55201',
        ]);
    }

    public function test_summary_filters_and_classifies_rows(): void
    {
        DB::table('daftar_mou')->insert([
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'perguruan_tinggi_lembaga' => 'Universiti Teknikal Malaysia Melaka',
                'no_kerjasama' => '0001/UN9/MoU/2018',
                'mulai' => '2018-02-05',
                'berakhir' => '2030-02-04',
                'kegiatan_kerjasama' => 'Pendidikan, Penelitian dan Pengabdian Kepada Masyarakat',
                'jenis_mitra' => 'Perguruan Tinggi LN',
                'tahun' => 2018,
            ],
            [
                // Salah ketik nyata di data sumber: "Pendidian" tanpa huruf k.
                'id' => '22222222-2222-2222-2222-222222222222',
                'perguruan_tinggi_lembaga' => 'Politeknik Negeri Sriwijaya',
                'no_kerjasama' => '0099/UN9/MoU/2019',
                'mulai' => '2019-01-01',
                'berakhir' => '2030-01-01',
                'kegiatan_kerjasama' => 'Pendidian, penelitian,pengabdian masyarakat dan pengembangan sumber daya',
                'jenis_mitra' => 'Perguruan Tinggi DN',
                'tahun' => 2019,
            ],
            [
                // Tidak menyebut pendidikan sama sekali -> tak masuk Tabel 1 Pendidikan.
                'id' => '33333333-3333-3333-3333-333333333333',
                'perguruan_tinggi_lembaga' => 'Dinas Kesehatan Kabupaten Ogan Ilir',
                'no_kerjasama' => '0050/UN9/MoU/2019',
                'mulai' => '2019-01-01',
                'berakhir' => '2030-01-01',
                'kegiatan_kerjasama' => 'pengembangan pada masyarakat',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2019,
            ],
            [
                // Kerjasama sudah berakhir sebelum TS terpilih -> tak ikut terhitung.
                'id' => '44444444-4444-4444-4444-444444444444',
                'perguruan_tinggi_lembaga' => 'Mitra Kadaluarsa',
                'no_kerjasama' => '0077/UN9/MoU/2015',
                'mulai' => '2015-01-01',
                'berakhir' => '2019-12-31',
                'kegiatan_kerjasama' => 'Pendidikan, Penelitian dan Pengabdian Kepada Masyarakat',
                'jenis_mitra' => 'Perguruan Tinggi DN',
                'tahun' => 2015,
            ],
        ]);

        DB::table('daftar_pks')->insert([
            [
                'id' => '55555555-5555-5555-5555-555555555555',
                'nama_perguruan_tinggi_lembaga' => 'Persatuan Pranata Laboratorium Pendidikan Indonesia',
                'no_kerjasama' => '0370/UN9.SB2.BPU/2022',
                'periode_mulai' => '2022-08-18',
                'periode_berakhir' => '2030-09-18',
                'kegiatan_kerjasama' => 'Pelatihan Kalibrasi Alat dan Workshop Pranata Laboratorium Pendidikan',
                'jangka_waktu' => '1 Bulan',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2022,
                'status_sikerma' => 'Berhasil diinput',
            ],
            [
                // Kegiatan tanpa kata kunci pendidikan -> tak masuk (bukan false positive).
                'id' => '66666666-6666-6666-6666-666666666666',
                'nama_perguruan_tinggi_lembaga' => 'Dinas Kesehatan Kabupaten Bangka Selatan',
                'no_kerjasama' => 'KN.01.01/3/D-0057/2022',
                'periode_mulai' => '2022-03-29',
                'periode_berakhir' => '2030-11-29',
                'kegiatan_kerjasama' => 'Jasa Konsultasi Pendampingan Tata Kelola Program Kesehatan',
                'jangka_waktu' => '8 Bulan',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2022,
                'status_sikerma' => 'Berhasil diinput',
            ],
            [
                // Kata kunci pendidikan cocok, tapi status menandai dokumen tak valid -> tetap dibuang.
                'id' => '77777777-7777-7777-7777-777777777777',
                'nama_perguruan_tinggi_lembaga' => 'Pelatihan Tanpa MoU',
                'no_kerjasama' => null,
                'periode_mulai' => '2022-01-01',
                'periode_berakhir' => '2030-01-01',
                'kegiatan_kerjasama' => 'Pelatihan dan Workshop Fiktif',
                'jangka_waktu' => '1 Bulan',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2022,
                'status_sikerma' => 'MoU BLM KETEMU',
            ],
        ]);

        $response = $this->getJson('/api/sapto/tables/kerjasama-pendidikan/summary?ts=2025')
            ->assertOk()
            ->assertJsonPath('table_code', '1');

        $rows = collect($response->json('rows'))->keyBy('lembaga_mitra');

        // Hanya 3 baris valid: MoU LN, MoU typo "Pendidian", PKS pelatihan.
        // Dikecualikan: MoU tanpa kata kunci pendidikan, MoU kadaluarsa,
        // PKS tanpa kata kunci pendidikan, PKS berstatus dokumen tak valid.
        $this->assertSame(3, $rows->count());

        $ln = $rows->get('Universiti Teknikal Malaysia Melaka');
        $this->assertNotNull($ln, 'MoU LN dengan kata kunci "Pendidikan" harus ikut.');
        $this->assertSame('✓', $ln['tingkat_internasional']);
        $this->assertSame('', $ln['tingkat_nasional']);
        $this->assertSame('No. 0001/UN9/MoU/2018', $ln['bukti_kerjasama']);
        $this->assertSame(2030, $ln['tahun_berakhir']);
        $this->assertNull($ln['manfaat_ps']);

        $typo = $rows->get('Politeknik Negeri Sriwijaya');
        $this->assertNotNull($typo, 'MoU dengan salah ketik "Pendidian" tetap harus ikut.');
        $this->assertSame('✓', $typo['tingkat_nasional']);

        $pks = $rows->get('Persatuan Pranata Laboratorium Pendidikan Indonesia');
        $this->assertNotNull($pks, 'PKS dengan kata kunci "Pelatihan"/"Workshop" harus ikut.');

        $this->assertFalse($rows->has('Dinas Kesehatan Kabupaten Ogan Ilir'));
        $this->assertFalse($rows->has('Mitra Kadaluarsa'));
        $this->assertFalse($rows->has('Dinas Kesehatan Kabupaten Bangka Selatan'));
        $this->assertFalse($rows->has('Pelatihan Tanpa MoU'));
    }

    public function test_local_government_partner_is_classified_as_wilayah(): void
    {
        DB::table('daftar_pks')->insert([
            'id' => '88888888-8888-8888-8888-888888888888',
            'nama_perguruan_tinggi_lembaga' => 'Dinas Kesehatan Kabupaten Musi Banyuasin',
            'no_kerjasama' => 'KN.01.01/3/D-0056/2022',
            'periode_mulai' => '2022-01-01',
            'periode_berakhir' => '2030-01-01',
            'kegiatan_kerjasama' => 'Pelatihan Tenaga Kesehatan',
            'jangka_waktu' => '8 Bulan',
            'jenis_mitra' => 'Instansi Pemerintah',
            'tahun' => 2022,
            'status_sikerma' => 'Berhasil diinput',
        ]);

        $row = collect($this->getJson('/api/sapto/tables/kerjasama-pendidikan/summary?ts=2025')
            ->assertOk()
            ->json('rows'))->first();

        // Nama lembaga menyebut "Dinas"/"Kabupaten" walau jenis_mitra generik
        // "Instansi Pemerintah" -> diklasifikasi Wilayah/Lokal, bukan Nasional.
        $this->assertSame('✓', $row['tingkat_wilayah']);
        $this->assertSame('', $row['tingkat_nasional']);
        $this->assertSame('', $row['tingkat_internasional']);
    }

    public function test_ts_defaults_to_current_year_and_available_years_include_it(): void
    {
        DB::table('daftar_mou')->insert([
            'id' => '99999999-9999-9999-9999-999999999999',
            'perguruan_tinggi_lembaga' => 'Mitra Aktif',
            'no_kerjasama' => '0001/UN9/MoU/2018',
            'mulai' => '2018-01-01',
            'berakhir' => null,
            'kegiatan_kerjasama' => 'Pendidikan, Penelitian dan Pengabdian Kepada Masyarakat',
            'jenis_mitra' => 'Perguruan Tinggi DN',
            'tahun' => 2018,
        ]);

        $response = $this->getJson('/api/sapto/tables/kerjasama-pendidikan/summary')
            ->assertOk();

        // Dibatasi ke maksimum 2025 selama data sinkronisasi tabel lain belum
        // sampai tahun ajaran 2026 (lihat SaptoKerjasamaRepository::latestYear()).
        $expectedYear = min((int) now()->year, 2025);
        $this->assertSame($expectedYear, $response->json('ts'));
        $this->assertContains($expectedYear, $response->json('available_years'));
        $this->assertContains(2018, $response->json('available_years'));
    }
}
