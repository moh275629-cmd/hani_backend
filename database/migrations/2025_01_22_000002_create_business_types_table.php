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
        if (!Schema::hasTable('business_types')) {
            Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_defined')->default(false); // System vs user-defined
            $table->integer('usage_count')->default(0); // How many stores use this type
            $table->timestamps();
            
            $table->index(['is_active', 'is_system_defined']);
        });

        // Insert default business types
        DB::table('business_types')->insert([
            [
                'name' => 'Electronics',
                'key' => 'electronics',
                'description' => 'Electronics and technology stores',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Food & Beverage',
                'key' => 'food_beverage',
                'description' => 'Food and beverage establishments',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fashion',
                'key' => 'fashion',
                'description' => 'Fashion and clothing stores',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Health & Beauty',
                'key' => 'health_beauty',
                'description' => 'Health and beauty services',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Automotive',
                'key' => 'automotive',
                'description' => 'Automotive services and parts',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Home & Garden',
                'key' => 'home_garden',
                'description' => 'Home and garden supplies',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sports & Recreation',
                'key' => 'sports',
                'description' => 'Sports and recreation services',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Entertainment',
                'key' => 'entertainment',
                'description' => 'Entertainment venues and services',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Education',
                'key' => 'education',
                'description' => 'Educational services and institutions',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional Services',
                'key' => 'professional_services',
                'description' => 'Professional and business services',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Restaurant',
                'key' => 'restaurant',
                'description' => 'Restaurants and dining establishments',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Retail',
                'key' => 'retail',
                'description' => 'General retail stores',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Service',
                'key' => 'service',
                'description' => 'General service providers',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'General',
                'key' => 'general',
                'description' => 'General business category',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Other',
                'key' => 'other',
                'description' => 'Other business types not listed',
                'is_active' => true,
                'is_system_defined' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ]);

            // Add custom business type field to stores table
            Schema::table('stores', function (Blueprint $table) {
                if (!Schema::hasColumn('stores', 'custom_business_type')) {
                    $table->string('custom_business_type')->nullable()->after('business_type');
                }
                if (!Schema::hasColumn('stores', 'has_custom_business_type')) {
                    $table->boolean('has_custom_business_type')->default(false)->after('custom_business_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['custom_business_type', 'has_custom_business_type']);
        });
        Schema::dropIfExists('business_types');
    }
};
