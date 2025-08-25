<?php

namespace App\Services;

class WilayaService
{
    /**
     * Get all Algerian wilayas (states)
     */
    public static function getAllWilayas()
    {
        return [
            ['id' => 1, 'name' => 'Adrar', 'name_ar' => 'أدرار', 'name_fr' => 'Adrar'],
            ['id' => 2, 'name' => 'Chlef', 'name_ar' => 'الشلف', 'name_fr' => 'Chlef'],
            ['id' => 3, 'name' => 'Laghouat', 'name_ar' => 'الأغواط', 'name_fr' => 'Laghouat'],
            ['id' => 4, 'name' => 'Oum El Bouaghi', 'name_ar' => 'أم البواقي', 'name_fr' => 'Oum El Bouaghi'],
            ['id' => 5, 'name' => 'Batna', 'name_ar' => 'باتنة', 'name_fr' => 'Batna'],
            ['id' => 6, 'name' => 'Béjaïa', 'name_ar' => 'بجاية', 'name_fr' => 'Béjaïa'],
            ['id' => 7, 'name' => 'Biskra', 'name_ar' => 'بسكرة', 'name_fr' => 'Biskra'],
            ['id' => 8, 'name' => 'Béchar', 'name_ar' => 'بشار', 'name_fr' => 'Béchar'],
            ['id' => 9, 'name' => 'Blida', 'name_ar' => 'البليدة', 'name_fr' => 'Blida'],
            ['id' => 10, 'name' => 'Bouira', 'name_ar' => 'البويرة', 'name_fr' => 'Bouira'],
            ['id' => 11, 'name' => 'Tamanrasset', 'name_ar' => 'تمنراست', 'name_fr' => 'Tamanrasset'],
            ['id' => 12, 'name' => 'Tébessa', 'name_ar' => 'تبسة', 'name_fr' => 'Tébessa'],
            ['id' => 13, 'name' => 'Tlemcen', 'name_ar' => 'تلمسان', 'name_fr' => 'Tlemcen'],
            ['id' => 14, 'name' => 'Tiaret', 'name_ar' => 'تيارت', 'name_fr' => 'Tiaret'],
            ['id' => 15, 'name' => 'Tizi Ouzou', 'name_ar' => 'تيزي وزو', 'name_fr' => 'Tizi Ouzou'],
            ['id' => 16, 'name' => 'Algiers', 'name_ar' => 'الجزائر', 'name_fr' => 'Alger'],
            ['id' => 17, 'name' => 'Djelfa', 'name_ar' => 'الجلفة', 'name_fr' => 'Djelfa'],
            ['id' => 18, 'name' => 'Jijel', 'name_ar' => 'جيجل', 'name_fr' => 'Jijel'],
            ['id' => 19, 'name' => 'Sétif', 'name_ar' => 'سطيف', 'name_fr' => 'Sétif'],
            ['id' => 20, 'name' => 'Saïda', 'name_ar' => 'سعيدة', 'name_fr' => 'Saïda'],
            ['id' => 21, 'name' => 'Skikda', 'name_ar' => 'سكيكدة', 'name_fr' => 'Skikda'],
            ['id' => 22, 'name' => 'Sidi Bel Abbès', 'name_ar' => 'سيدي بلعباس', 'name_fr' => 'Sidi Bel Abbès'],
            ['id' => 23, 'name' => 'Annaba', 'name_ar' => 'عنابة', 'name_fr' => 'Annaba'],
            ['id' => 24, 'name' => 'Guelma', 'name_ar' => 'قالمة', 'name_fr' => 'Guelma'],
            ['id' => 25, 'name' => 'Constantine', 'name_ar' => 'قسنطينة', 'name_fr' => 'Constantine'],
            ['id' => 26, 'name' => 'Médéa', 'name_ar' => 'المدية', 'name_fr' => 'Médéa'],
            ['id' => 27, 'name' => 'Mostaganem', 'name_ar' => 'مستغانم', 'name_fr' => 'Mostaganem'],
            ['id' => 28, 'name' => "M'Sila", 'name_ar' => 'المسيلة', 'name_fr' => "M'Sila"],
            ['id' => 29, 'name' => 'Mascara', 'name_ar' => 'معسكر', 'name_fr' => 'Mascara'],
            ['id' => 30, 'name' => 'Ouargla', 'name_ar' => 'ورقلة', 'name_fr' => 'Ouargla'],
            ['id' => 31, 'name' => 'Oran', 'name_ar' => 'وهران', 'name_fr' => 'Oran'],
            ['id' => 32, 'name' => 'El Bayadh', 'name_ar' => 'البيض', 'name_fr' => 'El Bayadh'],
            ['id' => 33, 'name' => 'Illizi', 'name_ar' => 'إليزي', 'name_fr' => 'Illizi'],
            ['id' => 34, 'name' => 'Bordj Bou Arréridj', 'name_ar' => 'برج بوعريريج', 'name_fr' => 'Bordj Bou Arréridj'],
            ['id' => 35, 'name' => 'Boumerdès', 'name_ar' => 'بومرداس', 'name_fr' => 'Boumerdès'],
            ['id' => 36, 'name' => 'El Tarf', 'name_ar' => 'الطارف', 'name_fr' => 'El Tarf'],
            ['id' => 37, 'name' => 'Tindouf', 'name_ar' => 'تندوف', 'name_fr' => 'Tindouf'],
            ['id' => 38, 'name' => 'Tissemsilt', 'name_ar' => 'تيسمسيلت', 'name_fr' => 'Tissemsilt'],
            ['id' => 39, 'name' => 'El Oued', 'name_ar' => 'الوادي', 'name_fr' => 'El Oued'],
            ['id' => 40, 'name' => 'Khenchela', 'name_ar' => 'خنشلة', 'name_fr' => 'Khenchela'],
            ['id' => 41, 'name' => 'Souk Ahras', 'name_ar' => 'سوق أهراس', 'name_fr' => 'Souk Ahras'],
            ['id' => 42, 'name' => 'Tipaza', 'name_ar' => 'تيبازة', 'name_fr' => 'Tipaza'],
            ['id' => 43, 'name' => 'Mila', 'name_ar' => 'ميلة', 'name_fr' => 'Mila'],
            ['id' => 44, 'name' => 'Aïn Defla', 'name_ar' => 'عين الدفلى', 'name_fr' => 'Aïn Defla'],
            ['id' => 45, 'name' => 'Naâma', 'name_ar' => 'النعامة', 'name_fr' => 'Naâma'],
            ['id' => 46, 'name' => 'Aïn Témouchent', 'name_ar' => 'عين تموشنت', 'name_fr' => 'Aïn Témouchent'],
            ['id' => 47, 'name' => 'Ghardaïa', 'name_ar' => 'غرداية', 'name_fr' => 'Ghardaïa'],
            ['id' => 48, 'name' => 'Relizane', 'name_ar' => 'غليزان', 'name_fr' => 'Relizane'],
            ['id' => 49, 'name' => 'El M\'Ghair', 'name_ar' => 'المغير', 'name_fr' => 'El M\'Ghair'],
            ['id' => 50, 'name' => 'El Meniaa', 'name_ar' => 'المنيعة', 'name_fr' => 'El Meniaa'],
            ['id' => 51, 'name' => 'Ouled Djellal', 'name_ar' => 'أولاد جلال', 'name_fr' => 'Ouled Djellal'],
            ['id' => 52, 'name' => 'Bordj Baji Mokhtar', 'name_ar' => 'برج باجي مختار', 'name_fr' => 'Bordj Baji Mokhtar'],
            ['id' => 53, 'name' => 'Béni Abbès', 'name_ar' => 'بني عباس', 'name_fr' => 'Béni Abbès'],
            ['id' => 54, 'name' => 'Timimoun', 'name_ar' => 'تيميمون', 'name_fr' => 'Timimoun'],
            ['id' => 55, 'name' => 'Touggourt', 'name_ar' => 'تقرت', 'name_fr' => 'Touggourt'],
            ['id' => 56, 'name' => 'Djanet', 'name_ar' => 'جانت', 'name_fr' => 'Djanet'],
            ['id' => 57, 'name' => "M'Sila", 'name_ar' => 'المسيلة', 'name_fr' => "M'Sila"],
            ['id' => 58, 'name' => 'In Salah', 'name_ar' => 'عين صالح', 'name_fr' => 'In Salah'],
            ['id' => 59, 'name' => 'In Guezzam', 'name_ar' => 'عين قزام', 'name_fr' => 'In Guezzam'],
        ];
    }

    /**
     * Get wilaya by ID
     */
    public static function getWilayaById($id)
    {
        $wilayas = self::getAllWilayas();
        foreach ($wilayas as $wilaya) {
            if ($wilaya['id'] == $id) {
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
            if (strtolower($wilaya['name']) === strtolower($name) ||
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
            if (stripos($wilaya['name'], $query) !== false ||
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
            $names[$wilaya['id']] = $wilaya["name_$language"] ?? $wilaya['name'];
        }
        
        return $names;
    }
}
