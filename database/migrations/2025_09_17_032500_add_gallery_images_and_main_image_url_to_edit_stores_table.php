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
            $table->json('gallery_images')->nullable()->after('banner');
            $table->string('main_image_url')->nullable()->after('gallery_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_stores', function (Blueprint $table) {
            $table->dropColumn(['gallery_images', 'main_image_url']);
        });
    }
};
