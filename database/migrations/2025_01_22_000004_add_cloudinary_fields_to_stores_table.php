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
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'main_image_url')) {
                $table->string('main_image_url')->nullable()->after('banner_blob');
            }
            if (!Schema::hasColumn('stores', 'gallery_images')) {
                $table->json('gallery_images')->nullable()->after('main_image_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['main_image_url', 'gallery_images']);
        });
    }
};
