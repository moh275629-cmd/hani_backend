<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;
use App\Models\Wilaya;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Load the JSON data
        $jsonPath = base_path('database/seeders/algeria_wilayas_baladiyahs.json');
        $jsonData = json_decode(file_get_contents($jsonPath), true);

        foreach ($jsonData as $wilayaData) {
            $wilayaCode = $wilayaData['code'];
            $baladiyahs = $wilayaData['baladiyahs'] ?? [];

            foreach ($baladiyahs as $index => $baladiyahName) {
                // Create a unique code for each city
                $cityCode = $wilayaCode . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                
                City::create([
                    'code' => $cityCode,
                    'name_en' => $baladiyahName,
                    'name_fr' => $baladiyahName, // Same as English for now
                    'name_ar' => $baladiyahName, // Same as English for now - will be updated with Arabic data
                    'wilaya_code' => $wilayaCode,
                    'is_active' => true,
                ]);
            }
        }

        // Now update with Arabic names from the Arabic JSON file
        $this->updateArabicNames();
    }

    private function updateArabicNames()
    {
        $jsonPathAr = base_path('database/seeders/algeria_wilayas_baladiyahs_ar.json');
        $jsonDataAr = json_decode(file_get_contents($jsonPathAr), true);
        
        $wilayas = $jsonDataAr['ولايات'] ?? [];

        foreach ($wilayas as $wilayaData) {
            $wilayaCode = $wilayaData['code'];
            $baladiyahs = $wilayaData['baladiyahs'] ?? [];

            foreach ($baladiyahs as $index => $baladiyahNameAr) {
                $cityCode = $wilayaCode . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                
                $city = City::where('code', $cityCode)->first();
                if ($city) {
                    $city->update([
                        'name_ar' => $baladiyahNameAr,
                    ]);
                }
            }
        }
    }
}
