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
        Schema::table('edit_stores', function (Blueprint $table) {
            if (!Schema::hasColumn('edit_stores', 'is_wilaya_change')) {
                $table->boolean('is_wilaya_change')->default(false)->after('user_state');
            }
            if (!Schema::hasColumn('edit_stores', 'current_wilaya_code')) {
                $table->string('current_wilaya_code', 10)->nullable()->after('is_wilaya_change');
            }
            if (!Schema::hasColumn('edit_stores', 'target_wilaya_code')) {
                $table->string('target_wilaya_code', 10)->nullable()->after('current_wilaya_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_stores', function (Blueprint $table) {
            $table->dropColumn(['is_wilaya_change', 'current_wilaya_code', 'target_wilaya_code']);
        });
    }
};
