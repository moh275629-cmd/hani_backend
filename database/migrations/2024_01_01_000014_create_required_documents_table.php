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
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_fr');
            $table->string('name_en');
            $table->text('description_ar');
            $table->text('description_fr');
            $table->text('description_en');
            $table->enum('document_type', ['identity', 'business', 'financial', 'legal', 'other']);
            $table->enum('user_category', ['client', 'store', 'admin']);
            $table->json('file_types');
            $table->integer('max_file_size'); // in KB
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_category', 'is_active']);
            $table->index(['document_type', 'is_active']);
            $table->index(['is_required', 'is_active']);
            $table->index(['display_order', 'user_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('required_documents');
    }
};
