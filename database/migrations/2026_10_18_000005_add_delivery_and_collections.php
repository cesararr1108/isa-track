<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Pesos colombianos como entero. null = el pedido ya está pago.
            $table->unsignedBigInteger('cash_on_delivery')->nullable()->after('status');

            $table->timestampTz('delivered_at')->nullable();
            $table->string('receiver_name', 150)->nullable();
            $table->string('receiver_document', 30)->nullable();
            $table->string('delivery_photo_path')->nullable();
            $table->string('delivery_signature_path')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            // Copiados del pedido para que el cuadre de caja sea una consulta simple.
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers');
            $table->string('method', 20)->comment('cash | nequi | daviplata | card_terminal | bank_transfer');
            $table->unsignedBigInteger('amount');
            $table->string('reference', 60)->nullable()->comment('Número de aprobación o comprobante');
            $table->timestampTz('collected_at');
            $table->timestampsTz();

            $table->index(['company_id', 'carrier_id', 'collected_at']);
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers');
            $table->date('business_date');
            $table->json('expected_by_method');
            $table->unsignedInteger('orders_count');
            $table->unsignedBigInteger('cash_received');
            $table->bigInteger('difference')->comment('positivo = sobrante, negativo = faltante');
            $table->string('notes', 500)->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at');
            $table->timestampsTz();

            // Un solo cierre por conductor y día.
            $table->unique(['company_id', 'carrier_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('order_payments');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cash_on_delivery', 'delivered_at', 'receiver_name', 'receiver_document',
                'delivery_photo_path', 'delivery_signature_path', 'delivery_latitude', 'delivery_longitude',
            ]);
        });
    }
};
