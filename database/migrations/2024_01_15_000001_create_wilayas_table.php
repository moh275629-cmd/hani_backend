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
        if (!Schema::hasTable('wilayas')) {
            Schema::create('wilayas', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->text('name_en');
                $table->text('name_fr');
                $table->text('name_ar');
                $table->boolean('is_active')->default(true);
                
                // Admin office details
                 
                // Admin assignment
                $table->unsignedBigInteger('admin_user_id')->nullable();
                
                // Audit fields
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('admin_user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                
                // Indexes
                $table->index(['is_active', 'code']);
                $table->index('admin_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wilayas');
    }
};
