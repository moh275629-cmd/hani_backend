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
        if (!Schema::hasTable('loyalty_cards')) {
            Schema::create('loyalty_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('card_number')->unique();
                $table->string('qr_code')->unique();
                $table->timestamps();
                
                $table->index(['user_id', 'store_id']);
                $table->index(['card_number']);
                $table->index(['qr_code']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_cards');
    }
};
