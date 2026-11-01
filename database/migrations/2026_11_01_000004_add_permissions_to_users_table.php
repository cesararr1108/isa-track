<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permisos por usuario (además de los de su rol) y usuarios activos/inactivos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->comment('Permisos extra, además de los del rol');
            $table->json('revoked_permissions')->nullable()->comment('Permisos del rol que se le quitan a este usuario');
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['permissions', 'revoked_permissions', 'phone', 'active']);
        });
    }
};
