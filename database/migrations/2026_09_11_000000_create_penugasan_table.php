<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tabel untuk seluruh hak akses SAPTO: siapa boleh masuk, sebagai peran
 * apa, dan sebatas mana. Menggantikan tabel `korprodi` yang cuma bisa
 * menyatakan satu peran.
 *
 * `nip` DAN `email` dua-duanya boleh diisi dan pencocokan cukup salah satu:
 * realm Keycloak terbukti kadang tidak mengirim klaim `nomor_identitas` sama
 * sekali (log 2026-09-10), jadi kunci tunggal berbasis NIP akan diam-diam
 * mengunci sebagian orang di luar tanpa jejak yang bisa dibaca dari layar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan', function (Blueprint $table) {
            $table->id();
            // korprodi | dekan | rektor. Sengaja string, bukan enum: menambah
            // peran baru nanti tidak perlu mengubah skema.
            $table->string('peran', 16);
            $table->string('nip', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('nama')->default('');
            // kode_program_studi (korprodi) | fakultas_id (dekan) | null (rektor)
            $table->string('lingkup', 64)->nullable();
            $table->string('dibuat_oleh', 191)->default('');
            $table->timestamps();

            $table->index('nip');
            $table->index('email');
            $table->index(['peran', 'lingkup']);
        });

        // Roster korprodi yang sudah ada disalin apa adanya supaya tidak ada
        // satu pun akun yang kehilangan akses saat kode beralih ke tabel ini.
        // Kolom `confidence` tidak ikut: tidak pernah dibaca kode mana pun dan
        // sumbernya masih utuh di database/data/korprodi.csv.
        //
        // Tabel `korprodi` sengaja TIDAK dihapus di sini. Migrasi ini dibuat
        // aditif supaya bisa dijalankan di produksi SEBELUM kode dialihkan --
        // kode lama tetap membaca korprodi, jadi tidak ada jeda di mana login
        // korprodi gagal. Tabelnya tinggal jadi cadangan yang tidak dibaca
        // siapa pun; penghapusannya keputusan terpisah.
        if (Schema::hasTable('korprodi')) {
            DB::table('korprodi')->orderBy('id')->chunk(200, function ($rows): void {
                DB::table('penugasan')->insert($rows->map(fn ($row): array => [
                    'peran' => 'korprodi',
                    'nip' => $row->nip,
                    'email' => null,
                    'nama' => $row->nama,
                    'lingkup' => $row->kode_program_studi,
                    'dibuat_oleh' => 'migrasi korprodi',
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])->all());
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan');
    }
};
