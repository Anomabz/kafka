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
        Schema::create('simak_transkrips', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('id_prodi')->nullable();
            $table->string('nm_prodi_simak')->nullable();
            $table->string('id_simak')->nullable();
            $table->string('FNIM')->nullable();
            $table->string('FCOD')->nullable();
            $table->string('VCOD')->nullable();
            $table->string('FDOS')->nullable();
            $table->string('FNIL')->nullable();
            $table->string('FNIL_AKHIR')->nullable();
            $table->string('FSMS')->nullable();
            $table->string('FTAK')->nullable();
            $table->string('FCDN')->nullable();
            $table->string('FCDM')->nullable();
            $table->string('FKLS')->nullable();
            $table->string('nutr')->nullable();
            $table->string('nuts')->nullable();
            $table->string('nuas')->nullable();
            $table->string('persen_nutr')->nullable();
            $table->string('persen_nuts')->nullable();
            $table->string('persen_nuas')->nullable();
            $table->string('rule_a_min')->nullable();
            $table->string('rule_a_max')->nullable();
            $table->string('rule_b_min')->nullable();
            $table->string('rule_b_max')->nullable();
            $table->string('rule_c_min')->nullable();
            $table->string('rule_c_max')->nullable();
            $table->string('rule_d_min')->nullable();
            $table->string('rule_d_max')->nullable();
            $table->string('rule_e_min')->nullable();
            $table->string('rule_e_max')->nullable();
            $table->string('locked')->nullable();
            $table->string('date_locked')->nullable();
            $table->string('approved')->nullable();
            $table->string('approval_locked')->nullable();
            $table->string('de_approval')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simak_transkrips');
    }
};
