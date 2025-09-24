<?php

namespace App\Services;

class WilayaService
{
    /**
     * Get all Algerian wilayas (states)
     */
    public static function getAllWilayas()
    {
        return \App\Models\Wilaya::orderBy('code')->get();
    }

    /**
     * Get wilaya by ID
     */
    public static function getWilayaById($id)
    {
        $wilayas = self::getAllWilayas();
        foreach ($wilayas as $wilaya) {
            if ($wilaya['code'] == $id) {
                return $wilaya;
            }
        }
        return null;
    }

    /**
     * Get wilaya by name
     */
    public static function getWilayaByName($name)
    {
        $wilayas = self::getAllWilayas();
        foreach ($wilayas as $wilaya) {
            if (strtolower($wilaya['name_en']) === strtolower($name) ||
                strtolower($wilaya['name_ar']) === strtolower($name) ||
                strtolower($wilaya['name_fr']) === strtolower($name)) {
                return $wilaya;
            }
        }
        return null;
    }

    /**
     * Search wilayas by name
     */
    public static function searchWilayas($query)
    {
        $wilayas = self::getAllWilayas();
        $results = [];
        
        foreach ($wilayas as $wilaya) {
            if (stripos($wilaya['name_en'], $query) !== false ||
                stripos($wilaya['name_ar'], $query) !== false ||
                stripos($wilaya['name_fr'], $query) !== false) {
                $results[] = $wilaya;
            }
        }
        
        return $results;
    }

    /**
     * Get wilaya names in specific language
     */
    public static function getWilayaNames($language = 'en')
    {
        $wilayas = self::getAllWilayas();
        $names = [];
        
        foreach ($wilayas as $wilaya) {
            $names[$wilaya['code']] = $wilaya["name_$language"] ?? $wilaya['name_en'];
        }
        
        return $names;
    }
}
