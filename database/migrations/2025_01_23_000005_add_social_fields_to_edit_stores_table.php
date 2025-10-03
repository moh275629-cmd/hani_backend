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
            if (!Schema::hasColumn('edit_stores', 'facebook')) {
                $table->string('facebook', 512)->nullable()->after('website');
            }
            if (!Schema::hasColumn('edit_stores', 'instagram')) {
                $table->string('instagram', 512)->nullable()->after('facebook');
            }
            if (!Schema::hasColumn('edit_stores', 'tiktok')) {
                $table->string('tiktok', 512)->nullable()->after('instagram');
            }
            if (!Schema::hasColumn('edit_stores', 'business_gmail')) {
                $table->string('business_gmail', 512)->nullable()->after('tiktok');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_stores', function (Blueprint $table) {
            $table->dropColumn(['facebook', 'instagram', 'tiktok', 'business_gmail']);
        });
    }
};
