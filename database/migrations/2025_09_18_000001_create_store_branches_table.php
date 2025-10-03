<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_branches')) {
            Schema::create('store_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id');
                $table->string('wilaya_code', 10);
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('offer_branches')) {
            Schema::create('offer_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('offer_id');
                $table->unsignedBigInteger('branch_id');
                $table->timestamps();

                $table->foreign('offer_id')->references('id')->on('offers')->onDelete('cascade');
                $table->foreign('branch_id')->references('id')->on('store_branches')->onDelete('cascade');
                $table->unique(['offer_id', 'branch_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_branches');
        Schema::dropIfExists('store_branches');
    }
};
