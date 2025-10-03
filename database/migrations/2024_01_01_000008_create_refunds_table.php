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
        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
                $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('cascade');
                $table->enum('refund_type', ['refund', 'exchange'])->nullable();
                $table->decimal('refund_amount', 10, 2)->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->nullable();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->json('refunded_products')->nullable();
                $table->enum('refund_method', ['original_payment', 'store_credit', 'loyalty_points', 'exchange'])->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->string('client_id')->nullable();
                $table->string('offer_id')->nullable();
                
                $table->index(['purchase_id', 'status']);
                $table->index(['processed_by', 'status']);
                $table->index(['refund_type', 'status']);
                $table->index(['processed_at', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
