<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rutas programadas por mensajero y día, con sus paradas en orden. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->date('route_date');
            $table->string('name', 120);
            $table->string('status', 20)->comment('planned | in_progress | completed | cancelled');
            $table->decimal('start_latitude', 10, 7);
            $table->decimal('start_longitude', 10, 7);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'route_date']);
            $table->index(['company_id', 'carrier_id', 'route_date']);
        });

        Schema::create('delivery_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('weight_kg', 8, 3)->default(0);
            $table->unsignedSmallInteger('packages')->default(1);

            $table->unique(['route_id', 'order_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_stops');
        Schema::dropIfExists('delivery_routes');
    }
};
