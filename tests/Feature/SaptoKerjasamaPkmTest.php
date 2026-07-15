<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tabel 1 Kerjasama Tridharma - Pengabdian kepada Masyarakat. Sama seperti
 * tabel Pendidikan/Penelitian tapi memverifikasi kata kunci bidang PkM, dan
 * bahwa PKS pendidikan/penelitian murni tidak ikut bocor ke sini.
 */
class SaptoKerjasamaPkmTest extends TestCase
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
        \App\Models\Korprodi::create([
            'nip' => '199999999999999001',
            'nama' => 'Test Korprodi',
            'kode_program_studi' => '55201',
        ]);
    }

    public function test_summary_filters_and_classifies_rows(): void
    {
        DB::table('daftar_mou')->insert([
            [
                // MoU tridharma umum -> ikut semua bidang termasuk PkM.
                'id' => '11111111-1111-1111-1111-111111111111',
                'perguruan_tinggi_lembaga' => 'National Cheng Kung University, Taiwan',
                'no_kerjasama' => '0002/UN9/MoU/2018',
                'mulai' => '2018-03-08',
                'berakhir' => '2030-03-07',
                'kegiatan_kerjasama' => 'Pendidikan, Penelitian dan Pengabdian Kepada Masyarakat',
                'jenis_mitra' => 'Perguruan Tinggi LN',
                'tahun' => 2018,
            ],
            [
                // Tidak menyebut pengabdian/dharma -> tak masuk Tabel 1 PkM.
                'id' => '22222222-2222-2222-2222-222222222222',
                'perguruan_tinggi_lembaga' => 'Mitra Non PkM',
                'no_kerjasama' => '0050/UN9/MoU/2019',
                'mulai' => '2019-01-01',
                'berakhir' => '2030-01-01',
                'kegiatan_kerjasama' => 'Penelitian dan Pertukaran Data Riset',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2019,
            ],
        ]);

        DB::table('daftar_pks')->insert([
            [
                // Kata kunci PkM eksplisit -> ikut.
                'id' => '33333333-3333-3333-3333-333333333333',
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
                // Murni pendidikan (pelatihan/workshop) -> tak masuk di sini.
                'id' => '44444444-4444-4444-4444-444444444444',
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
                // Kata kunci PkM cocok, tapi status menandai dokumen tak valid -> tetap dibuang.
                'id' => '55555555-5555-5555-5555-555555555555',
                'nama_perguruan_tinggi_lembaga' => 'Pengabdian Tanpa MoU',
                'no_kerjasama' => null,
                'periode_mulai' => '2022-01-01',
                'periode_berakhir' => '2030-01-01',
                'kegiatan_kerjasama' => 'Sosialisasi dan Penyuluhan Fiktif',
                'jangka_waktu' => '1 Tahun',
                'jenis_mitra' => 'Instansi Pemerintah',
                'tahun' => 2022,
                'status_sikerma' => 'Gagal',
            ],
        ]);

        $response = $this->getJson('/api/sapto/tables/kerjasama-pkm/summary?ts=2025')
            ->assertOk()
            ->assertJsonPath('table_code', '1')
            ->assertJsonPath('table_name', 'Kerjasama Tridharma - Pengabdian kepada Masyarakat');

        $rows = collect($response->json('rows'))->keyBy('lembaga_mitra');

        // Hanya 2 baris valid: MoU tridharma umum (LN) dan PKS pendampingan/konsultasi.
        $this->assertSame(2, $rows->count());

        $mou = $rows->get('National Cheng Kung University, Taiwan');
        $this->assertNotNull($mou, 'MoU tridharma umum harus ikut Tabel 1 PkM juga.');
        $this->assertSame('✓', $mou['tingkat_internasional']);

        $pks = $rows->get('Dinas Kesehatan Kabupaten Bangka Selatan');
        $this->assertNotNull($pks, 'PKS dengan kata kunci "Pendampingan"/"Konsultasi" harus ikut.');
        $this->assertSame('✓', $pks['tingkat_wilayah'], 'Nama lembaga menyebut Dinas/Kabupaten -> Wilayah/Lokal.');
        $this->assertNull($pks['manfaat_ps']);

        $this->assertFalse($rows->has('Mitra Non PkM'));
        $this->assertFalse($rows->has('Persatuan Pranata Laboratorium Pendidikan Indonesia'), 'PKS pendidikan murni tidak boleh bocor ke tabel PkM.');
        $this->assertFalse($rows->has('Pengabdian Tanpa MoU'));

        $metrics = collect($response->json('metrics'))->pluck('value', 'label');
        $this->assertSame(2, $metrics['Total kerjasama PkM']);
    }
}
