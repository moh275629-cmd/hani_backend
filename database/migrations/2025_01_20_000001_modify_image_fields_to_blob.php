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
        // Modify stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->binary('logo_blob')->nullable()->after('logo');
            $table->binary('banner_blob')->nullable()->after('banner');
        });

        // Modify offers table
        Schema::table('offers', function (Blueprint $table) {
            $table->binary('image_blob')->nullable()->after('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove BLOB columns from stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['logo_blob', 'banner_blob']);
        });

        // Remove BLOB column from offers table
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('image_blob');
        });
    }
};
