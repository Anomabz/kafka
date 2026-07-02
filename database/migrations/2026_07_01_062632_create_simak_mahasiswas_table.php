<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simak_mahasiswas', function (Blueprint $table) {
            $table->id();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nm_fakultas', 255)->nullable();
            $table->string('nm_prodi_simak', 255)->nullable();
            $table->string('FMNOM', 255)->nullable();
            $table->string('FMNIM', 255)->nullable();
            $table->string('FMNIR', 255)->nullable();
            $table->string('FMNAM', 255)->nullable();
            $table->text('FMADD')->nullable(); // Alamat bertipe text
            
            // Kolom legasi data personal & wali mahasiswa
            $fields = [
                'FMTLP', 'FMTMP', 'FMTGL', 'FMJNS', 'FMAGA', 'FMWNI', 'FMSTA', 'FMDAR', 
                'FMALK', 'FMAPR', 'FMKLK', 'FMKPR', 'FMKER', 'FMNPT', 'FMADK', 'FMTLK', 
                'FANAM', 'FAPDD', 'FAUMU', 'FAAGA', 'FAKER', 'FAHSL', 'FINAM', 'FIPDD', 
                'FIUMU', 'FIAGA', 'FIKER', 'FIHSL', 'FMADT', 'FMASL', 'FMNIJ', 'FMTIJ', 
                'FMNIL', 'FMNEM', 'FMHOB', 'FMKET', 'FMTGI', 'FTHN', 'FKLS', 'FAKT', 
                'FDOSPA', 'FPASSWORD', 'FSTAT', 'FOB'
            ];

            foreach ($fields as $field) {
                $table->string($field, 255)->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simak_mahasiswas');
    }
};
