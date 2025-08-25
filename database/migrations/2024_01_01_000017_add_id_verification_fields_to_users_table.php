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
        Schema::table('users', function (Blueprint $table) {
            $table->json('id_verification_data')->nullable()->after('phone_verified_at');
            $table->timestamp('id_verified_at')->nullable()->after('id_verification_data');
            
            $table->index(['id_verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['id_verified_at']);
            
            $table->dropColumn([
                'id_verification_data',
                'id_verified_at'
            ]);
        });
    }
};
