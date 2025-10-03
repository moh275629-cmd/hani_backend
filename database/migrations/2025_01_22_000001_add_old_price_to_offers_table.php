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
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'old_price')) {
                $table->decimal('old_price', 10, 2)->nullable()->after('discount_value');
            }
            if (!Schema::hasColumn('offers', 'multi_check_enabled')) {
                $table->boolean('multi_check_enabled')->default(false)->after('is_featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['old_price', 'multi_check_enabled']);
        });
    }
};
