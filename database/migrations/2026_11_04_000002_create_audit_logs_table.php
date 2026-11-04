<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bitácora de auditoría: quién hizo qué (usuarios, permisos, caja, inicios de sesión). Solo inserción. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at');

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
