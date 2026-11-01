<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos del mensajero: cédula, correo, vehículo, capacidad y foto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->string('document_number', 15)->nullable()->after('type')->comment('Cédula, solo dígitos');
            $table->string('email')->nullable()->after('phone');
            $table->string('vehicle_type', 20)->nullable()->after('vehicle_plate')->comment('bicycle | motorcycle | car | van | truck | on_foot');
            $table->decimal('capacity_kg', 8, 2)->nullable()->after('vehicle_type');
            $table->unsignedInteger('capacity_packages')->nullable()->after('capacity_kg');
            $table->string('photo_path')->nullable()->after('capacity_packages');

            $table->unique(['company_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'document_number']);
            $table->dropColumn(['document_number', 'email', 'vehicle_type', 'capacity_kg', 'capacity_packages', 'photo_path']);
        });
    }
};
