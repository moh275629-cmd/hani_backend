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
        if (!Schema::hasTable('rating_offers')) {
            Schema::create('rating_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rater_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('rated_offer_id')->constrained('offers')->onDelete('cascade');
                $table->integer('stars');
                $table->text('comment')->nullable();
                $table->timestamps();
                
                $table->unique(['rater_id', 'rated_offer_id']);
                $table->index(['rated_offer_id']);
                $table->index(['stars']);
                
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
