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
        Schema::create('card_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('scan_type', ['qr_code', 'manual_entry', 'nfc', 'barcode']);
            $table->json('details');
            $table->json('location_data')->nullable();
            $table->json('device_info')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->text('validation_notes')->nullable();
            $table->timestamp('usage_date');
            $table->timestamps();
            
            $table->index(['loyalty_card_id', 'usage_date']);
            $table->index(['branch_id', 'usage_date']);
            $table->index(['scan_type', 'is_valid']);
            $table->index(['usage_date', 'is_valid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_usages');
    }
};
