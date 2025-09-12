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
        Schema::table('cities', function (Blueprint $table) {
            // Drop existing FK if present, then recreate with ON UPDATE CASCADE
            try {
                $table->dropForeign(['wilaya_code']);
            } catch (\Throwable $e) {
                // FK might not exist on some environments; ignore
            }

            $table->foreign('wilaya_code')
                ->references('code')
                ->on('wilayas')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            try {
                $table->dropForeign(['wilaya_code']);
            } catch (\Throwable $e) {
                // ignore
            }

            // Recreate without ON UPDATE CASCADE (previous default)
            $table->foreign('wilaya_code')
                ->references('code')
                ->on('wilayas')
                ->onDelete('cascade');
        });
    }
};


