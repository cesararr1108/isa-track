<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zonas de cobertura con tarifa de domicilio, y la zona/tarifa de cada pedido. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 7)->default('#00C896');
            // [[lat, lng], ...]. Con PostGIS se puede migrar a geography(Polygon).
            $table->json('polygon');
            $table->unsignedBigInteger('delivery_fee')->comment('Pesos, entero');
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('zone_id')->nullable()->after('carrier_id')->constrained('zones')->nullOnDelete();
            $table->unsignedBigInteger('delivery_fee')->nullable()->after('cash_on_delivery')->comment('Tarifa del domicilio en pesos');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn('delivery_fee');
        });
        Schema::dropIfExists('zones');
    }
};
