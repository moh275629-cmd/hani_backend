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
        Schema::table('loyalty_cards', function (Blueprint $table) {
            // Change card_number column to TEXT to accommodate encrypted data
            $table->text('card_number')->change();
            // Change qr_code column to TEXT to accommodate encrypted data
            $table->text('qr_code')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_cards', function (Blueprint $table) {
            // Revert back to string if needed
            $table->string('card_number')->change();
            $table->string('qr_code')->change();
        });
    }
};
