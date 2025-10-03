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
        if (!Schema::hasTable('terms_and_conditions')) {
            Schema::create('terms_and_conditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publisher_id')->constrained('users')->onDelete('cascade');
                $table->text('content_ar')->nullable();
                $table->text('content_fr')->nullable();
                $table->text('content_en')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(false);
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                
                $table->index(['is_active', 'is_published']);
                $table->index(['published_at', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms_and_conditions');
    }
};
