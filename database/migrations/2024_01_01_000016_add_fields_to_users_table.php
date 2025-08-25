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
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['client', 'store', 'admin', 'global_admin'])->default('client')->after('phone');
            $table->string('profile_image')->nullable()->after('role');
            $table->string('state')->nullable()->after('profile_image');
            $table->boolean('is_active')->default(true)->after('state');
            $table->timestamp('phone_verified_at')->nullable()->after('is_active');
            
            $table->index(['role', 'is_active']);
            $table->index(['state', 'is_active']);
            $table->index(['phone', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['state', 'is_active']);
            $table->dropIndex(['phone', 'is_active']);
            
            $table->dropColumn([
                'phone',
                'role',
                'profile_image',
                'state',
                'is_active',
                'phone_verified_at'
            ]);
        });
    }
};
