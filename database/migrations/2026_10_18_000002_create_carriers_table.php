<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 20)->comment('own_driver | external_company');
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_plate', 10)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};
