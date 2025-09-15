<?php

namespace App\Services;

use App\Models\BusinessType;

class BusinessTypeService
{
    /**
     * Get business type by key
     */
    public static function getBusinessType(string $key): string
    {
        // Returns an associative array: ['key' => 'name']
        $types = BusinessType::getActiveTypes()->pluck('name', 'key')->toArray();

        return $types[$key] ?? $key; // fallback to the given key
    }

    /**
     * Get business type key by name
     */
    public static function getBusinessTypeKey(string $name): string
    {
        $types = BusinessType::getActiveTypes()->pluck('name', 'key')->toArray();

        // Search the key by name, fallback to the name itself if not found
        return array_search($name, $types) ?: $name;
    }

    /**
     * Get business types for dropdown (raw data)
     * Controller can wrap this in a JSON response
     */
    public static function getBusinessTypesForDropdown()
    {
        $businessTypes = BusinessType::getActiveTypes();

        return $businessTypes->map(function ($type) {
            return [
                'value' => $type->key,
                'label' => $type->name,
                'is_system_defined' => $type->is_system_defined,
                'usage_count' => $type->usage_count,
            ];
        });
    }
}
