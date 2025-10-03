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
            if (!Schema::hasColumn('offers', 'main_media_url')) {
                $table->string('main_media_url')->nullable()->after('image_blob');
            }
            if (!Schema::hasColumn('offers', 'gallery_media')) {
                $table->json('gallery_media')->nullable()->after('main_media_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['main_media_url', 'gallery_media']);
        });
    }
};
