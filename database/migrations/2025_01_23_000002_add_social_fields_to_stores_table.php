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
            if (!Schema::hasColumn('stores', 'facebook')) {
                $table->string('facebook')->nullable()->after('website');
            }
            if (!Schema::hasColumn('stores', 'instagram')) {
                $table->string('instagram')->nullable()->after('facebook');
            }
            if (!Schema::hasColumn('stores', 'tiktok')) {
                $table->string('tiktok')->nullable()->after('instagram');
            }
            if (!Schema::hasColumn('stores', 'business_gmail')) {
                $table->string('business_gmail')->nullable()->after('tiktok');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['facebook', 'instagram', 'tiktok', 'business_gmail']);
        });
    }
};
