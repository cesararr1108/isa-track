<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('role', 20)->default('viewer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('role');
        });
    }
};
