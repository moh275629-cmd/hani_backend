<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'state_code')) {
                $table->string('state_code')->nullable()->after('state');
                $table->index(['state_code', 'is_active']);
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'state_code')) {
                $table->string('state_code')->nullable()->after('state');
                $table->index(['state_code', 'business_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'state_code')) {
                $table->dropIndex(['state_code', 'is_active']);
                $table->dropColumn('state_code');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'state_code')) {
                $table->dropIndex(['state_code', 'business_type']);
                $table->dropColumn('state_code');
            }
        });
    }
};


