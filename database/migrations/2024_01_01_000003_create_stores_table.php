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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_name');
            $table->text('description');
            $table->string('business_type')->nullable();
            $table->string('phone')->nullable();
            $table->string('email');
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->longBlob('logo_blob')->nullable();
            $table->string('banner')->nullable();
            $table->longBlob('banner_blob')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('state_code')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->json('business_hours')->nullable();
            $table->json('services')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->string('payment_methods')->nullable();
            $table->string('google_place_id')->nullable();
            $table->json('user_data')->nullable();
            
            $table->index(['state', 'business_type']);
            $table->index(['latitude', 'longitude']);
            $table->index(['is_approved', 'is_active']);
            $table->index(['state_code', 'business_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
