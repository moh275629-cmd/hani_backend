<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TermsAndConditions;
use App\Models\User;

class TermsAndConditionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default admin user if none exists
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::create([
                'name' => 'System Admin',
                'email' => 'admin@hani.com',
                'phone' => '+1234567890',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'is_active' => true,
            ]);
        }

        // Create default terms and conditions
        $terms = TermsAndConditions::create([
            'publisher_id' => $admin->id,
            'version' => '1.0',
            'content_en' => 'By registering as a store on Hani App, you agree to comply with all applicable laws and regulations. You must provide accurate information and maintain the security of your account. Hani App reserves the right to suspend or terminate accounts that violate these terms.',
            'content_fr' => 'En vous inscrivant en tant que magasin sur Hani App, vous acceptez de vous conformer à toutes les lois et réglementations applicables. Vous devez fournir des informations exactes et maintenir la sécurité de votre compte. Hani App se réserve le droit de suspendre ou de résilier les comptes qui violent ces conditions.',
            'content_ar' => 'بالتسجيل كمتجر في تطبيق هاني، فإنك توافق على الامتثال لجميع القوانين واللوائح المعمول بها. يجب عليك تقديم معلومات دقيقة والحفاظ على أمان حسابك. يحتفظ تطبيق هاني بالحق في تعليق أو إنهاء الحسابات التي تنتهك هذه الشروط.',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
            'notes' => 'Default terms and conditions for store registration',
        ]);

        $this->command->info('Terms and conditions seeded successfully!');
    }
}
