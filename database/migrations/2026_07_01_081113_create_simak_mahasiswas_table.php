<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simak_mahasiswas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_prodi')->nullable();
            $table->string('nm_fakultas')->nullable();
            $table->string('nm_prodi_simak')->nullable();
            $table->string('FMNOM')->nullable();
            $table->string('FMNIM')->nullable();
            $table->string('FMNIR')->nullable();
            $table->string('FMNAM')->nullable();
            $table->text('FMADD')->nullable();
            $table->string('FMTLP')->nullable();
            $table->string('FMTMP')->nullable();
            $table->string('FMTGL')->nullable();
            $table->string('FMJNS')->nullable();
            $table->string('FMAGA')->nullable();
            $table->string('FMWNI')->nullable();
            $table->string('FMSTA')->nullable();
            $table->string('FMDAR')->nullable();
            $table->string('FMALK')->nullable();
            $table->string('FMAPR')->nullable();
            $table->string('FMKLK')->nullable();
            $table->string('FMKPR')->nullable();
            $table->string('FMKER')->nullable();
            $table->string('FMNPT')->nullable();
            $table->string('FMADK')->nullable();
            $table->string('FMTLK')->nullable();
            $table->string('FANAM')->nullable();
            $table->string('FAPDD')->nullable();
            $table->string('FAUMU')->nullable();
            $table->string('FAAGA')->nullable();
            $table->string('FAKER')->nullable();
            $table->string('FAHSL')->nullable();
            $table->string('FINAM')->nullable();
            $table->string('FIPDD')->nullable();
            $table->string('FIUMU')->nullable();
            $table->string('FIAGA')->nullable();
            $table->string('FIKER')->nullable();
            $table->string('FIHSL')->nullable();
            $table->string('FMADT')->nullable();
            $table->string('FMASL')->nullable();
            $table->string('FMNIJ')->nullable();
            $table->string('FMTIJ')->nullable();
            $table->string('FMNIL')->nullable();
            $table->string('FMNEM')->nullable();
            $table->string('FMHOB')->nullable();
            $table->string('FMKET')->nullable();
            $table->string('FMTGI')->nullable();
            $table->string('FTHN')->nullable();
            $table->string('FKLS')->nullable();
            $table->string('FAKT')->nullable();
            $table->string('FDOSPA')->nullable();
            $table->string('FPASSWORD')->nullable();
            $table->string('FSTAT')->nullable();
            $table->string('FOB')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simak_mahasiswas');
    }
};
