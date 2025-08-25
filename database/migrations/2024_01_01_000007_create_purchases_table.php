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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('client_id')->nullable();
            $table->foreignId('redeemed_offer_id')->nullable()->constrained('offers')->onDelete('set null');
            $table->string('purchase_number')->unique()->nullable();
            $table->json('products')->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->integer('points_earned')->nullable();
            $table->integer('points_spent')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->nullable();
            $table->enum('payment_method', ['cash', 'card', 'mobile_payment', 'loyalty_points'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('purchase_date')->useCurrent()->useCurrentOnUpdate();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['purchase_date', 'status']);
            $table->index(['redeemed_offer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
