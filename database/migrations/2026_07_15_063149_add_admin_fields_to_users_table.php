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
        Schema::table('users', function (Blueprint $table) {
            // Login lokal khusus developer SAPTO (bukan akun SSO Keycloak) —
            // lihat SsoController::authenticate() dan SaptoUserContext::fromRequest().
            $table->string('nomor_identitas', 32)->nullable()->unique()->after('email');
            $table->boolean('is_admin')->default(false)->after('nomor_identitas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nomor_identitas', 'is_admin']);
        });
    }
};
