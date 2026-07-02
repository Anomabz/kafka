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
            $table->id();
            $table->string('id_prodi', 255)->nullable();
            $table->string('nm_prodi_simak', 255)->nullable();
            $table->string('id_simak', 255)->nullable();
            $table->string('FNIM', 255)->nullable();
            $table->string('FCOD', 255)->nullable();
            $table->string('VCOD', 255)->nullable();
            $table->string('FDOS', 255)->nullable();
            $table->string('FNIL', 255)->nullable();
            $table->string('FNIL_AKHIR', 255)->nullable();
            $table->string('FSMS', 255)->nullable();
            $table->string('FTAK', 255)->nullable();
            $table->string('FCDN', 255)->nullable();
            $table->string('FCDM', 255)->nullable();
            $table->string('FKLS', 255)->nullable();
            $table->string('nutr', 255)->nullable();
            $table->string('nuts', 255)->nullable();
            $table->string('nuas', 255)->nullable();
            $table->string('persen_nutr', 255)->nullable();
            $table->string('persen_nuts', 255)->nullable();
            $table->string('persen_nuas', 255)->nullable();
            $table->string('rule_a_min', 255)->nullable();
            $table->string('rule_a_max', 255)->nullable();
            $table->string('rule_b_min', 255)->nullable();
            $table->string('rule_b_max', 255)->nullable();
            $table->string('rule_c_min', 255)->nullable();
            $table->string('rule_c_max', 255)->nullable();
            $table->string('rule_d_min', 255)->nullable();
            $table->string('rule_d_max', 255)->nullable();
            $table->string('rule_e_min', 255)->nullable();
            $table->string('rule_e_max', 255)->nullable();
            $table->string('locked', 255)->nullable();
            $table->string('date_locked', 255)->nullable();
            $table->string('approved', 255)->nullable();
            $table->string('approval_locked', 255)->nullable();
            $table->string('de_approval', 255)->nullable();
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
