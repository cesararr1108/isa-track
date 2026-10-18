<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('tracking_code', 24)->unique();
            $table->string('external_reference', 60)->nullable()->comment('Número de pedido en la tienda');
            $table->string('status', 20);
            $table->foreignUuid('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();

            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 20);
            $table->string('recipient_email')->nullable();

            $table->string('address_line', 200);
            $table->string('city', 80);
            $table->string('neighborhood', 80)->nullable();
            $table->string('address_reference', 200)->nullable();
            // Con PostGIS se puede migrar a una columna geography(Point) para consultas por zona.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestampTz('promised_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'carrier_id']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('order_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('code', 30)->unique()->comment('Va en la etiqueta QR / código de barras');
            $table->string('description', 150);
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->timestampsTz();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestampTz('occurred_at');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('incident_reason', 30)->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_packages');
        Schema::dropIfExists('orders');
    }
};
