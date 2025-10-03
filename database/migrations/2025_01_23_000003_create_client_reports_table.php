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
        if (!Schema::hasTable('client_reports')) {
            Schema::create('client_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_store_id')->constrained('stores')->onDelete('cascade');
                $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
                $table->string('reason');
                $table->text('details')->nullable();
                $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
                $table->timestamps();
                
                $table->index(['reporter_store_id', 'reported_user_id']);
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_reports');
    }
};
