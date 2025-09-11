<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('wilaya_code')->nullable();
            $table->string('office_address')->nullable();
            $table->decimal('office_location_lat', 10, 7)->nullable();
            $table->decimal('office_location_lng', 10, 7)->nullable();
            $table->string('office_phone')->nullable();
            $table->string('office_email')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('wilaya_code')->references('code')->on('wilayas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};


