<?php

namespace App\Services;

class BusinessTypeService
{
    /**
     * Get all available business types
     */
    public static function getAllBusinessTypes()
    {
        return [
            'electronics' => 'Electronics',
            'food_beverage' => 'Food & Beverage',
            'fashion' => 'Fashion',
            'health_beauty' => 'Health & Beauty',
            'automotive' => 'Automotive',
            'home_garden' => 'Home & Garden',
            'sports' => 'Sports & Recreation',
            'entertainment' => 'Entertainment',
            'education' => 'Education',
            'professional_services' => 'Professional Services',
            'restaurant' => 'Restaurant',
            'retail' => 'Retail',
            'service' => 'Service',
            'general' => 'General',
        ];
    }

    /**
     * Get business type by key
     */
    public static function getBusinessType($key)
    {
        $types = self::getAllBusinessTypes();
        return $types[$key] ?? $key;
    }

    /**
     * Get business type key by name
     */
    public static function getBusinessTypeKey($name)
    {
        $types = self::getAllBusinessTypes();
        return array_search($name, $types) ?: $name;
    }

    /**
     * Get business types for dropdown
     */
    public static function getBusinessTypesForDropdown()
    {
        $types = self::getAllBusinessTypes();
        $dropdown = [];
        
        foreach ($types as $key => $name) {
            $dropdown[] = [
                'value' => $key,
                'label' => $name,
            ];
        }
        
        return $dropdown;
    }
}
