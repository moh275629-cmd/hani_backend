<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('discount_type');
            $table->integer('discount_value')->nullable();
            $table->integer('minimum_purchase')->nullable();
            $table->integer('max_usage_per_user')->default(1);
            $table->integer('total_usage_limit')->nullable();
            $table->integer('current_usage_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('terms')->nullable();
            $table->json('applicable_products')->nullable();
            $table->json('excluded_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('image')->nullable();
            $table->binary('image_blob')->nullable();
            $table->timestamps();
            
            $table->index(['valid_from', 'valid_until']);
            $table->index(['discount_type', 'is_active']);
            $table->index(['is_featured', 'is_active']);
        });
        Schema::create('offers_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('discount_type');
            $table->integer('discount_value')->nullable();
            $table->integer('minimum_purchase')->nullable();
            $table->integer('max_usage_per_user')->default(1);
            $table->integer('total_usage_limit')->nullable();
            $table->integer('current_usage_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('terms')->nullable();
            $table->json('applicable_products')->nullable();
            $table->json('excluded_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('image')->nullable();
            // temporary placeholder, will override with raw SQL
            // $table->binary('image_blob')->nullable();
            $table->timestamps();
        
            $table->index(['valid_from', 'valid_until']);
            $table->index(['discount_type', 'is_active']);
            $table->index(['is_featured', 'is_active']);
        });
        
        // Add real LONGBLOB column after table is created
        DB::statement('ALTER TABLE offers_temp ADD image_blob LONGBLOB NULL');
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('offers_temp');
    }
};
