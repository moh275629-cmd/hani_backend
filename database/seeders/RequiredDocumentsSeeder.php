<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RequiredDocuments;

class RequiredDocumentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documents = [
            [
                'name_en' => 'Business License',
                'name_fr' => 'Licence Commerciale',
                'name_ar' => 'رخصة تجارية',
                'description_en' => 'Valid business license or commercial registration certificate',
                'description_fr' => 'Licence commerciale valide ou certificat d\'enregistrement commercial',
                'description_ar' => 'رخصة تجارية سارية أو شهادة تسجيل تجاري',
                'document_type' => 'business',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048, // 2MB in KB
                'is_required' => true,
                'is_active' => true,
                'display_order' => 1,
                'notes' => 'Required for all store registrations',
            ],
            [
                'name_en' => 'Tax Certificate',
                'name_fr' => 'Certificat Fiscal',
                'name_ar' => 'شهادة ضريبية',
                'description_en' => 'Tax identification number or tax certificate',
                'description_fr' => 'Numéro d\'identification fiscale ou certificat fiscal',
                'description_ar' => 'رقم التعريف الضريبي أو الشهادة الضريبية',
                'document_type' => 'financial',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048,
                'is_required' => true,
                'is_active' => true,
                'display_order' => 2,
                'notes' => 'Required for tax compliance',
            ],
            [
                'name_en' => 'Bank Statement',
                'name_fr' => 'Relevé Bancaire',
                'name_ar' => 'كشف حساب بنكي',
                'description_en' => 'Recent bank statement showing business account activity',
                'description_fr' => 'Relevé bancaire récent montrant l\'activité du compte commercial',
                'description_ar' => 'كشف حساب بنكي حديث يوضح نشاط الحساب التجاري',
                'document_type' => 'financial',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048,
                'is_required' => true,
                'is_active' => true,
                'display_order' => 3,
                'notes' => 'Required for financial verification',
            ],
            [
                'name_en' => 'Utility Bill',
                'name_fr' => 'Facture de Services',
                'name_ar' => 'فاتورة خدمات',
                'description_en' => 'Recent utility bill (electricity, water, gas) for business address',
                'description_fr' => 'Facture de services récente (électricité, eau, gaz) pour l\'adresse commerciale',
                'description_ar' => 'فاتورة خدمات حديثة (كهرباء، ماء، غاز) للعنوان التجاري',
                'document_type' => 'business',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048,
                'is_required' => true,
                'is_active' => true,
                'display_order' => 4,
                'notes' => 'Required for address verification',
            ],
            [
                'name_en' => 'Lease Agreement',
                'name_fr' => 'Contrat de Location',
                'name_ar' => 'عقد إيجار',
                'description_en' => 'Lease agreement or property ownership documents for business location',
                'description_fr' => 'Contrat de location ou documents de propriété pour l\'emplacement commercial',
                'description_ar' => 'عقد إيجار أو مستندات ملكية العقار للموقع التجاري',
                'document_type' => 'legal',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048,
                'is_required' => true,
                'is_active' => true,
                'display_order' => 5,
                'notes' => 'Required for location verification',
            ],
            [
                'name_en' => 'Store Photos',
                'name_fr' => 'Photos du Magasin',
                'name_ar' => 'صور المتجر',
                'description_en' => 'Clear photos of the store front, interior, and business operations',
                'description_fr' => 'Photos claires de la façade du magasin, de l\'intérieur et des opérations commerciales',
                'description_ar' => 'صور واضحة لواجهة المتجر والداخلية والعمليات التجارية',
                'document_type' => 'other',
                'user_category' => 'store',
                'file_types' => ['jpg', 'jpeg', 'png'],
                'max_file_size' => 5120, // 5MB in KB
                'is_required' => true,
                'is_active' => true,
                'display_order' => 6,
                'notes' => 'Required for visual verification',
            ],
            [
                'name_en' => 'Insurance Certificate',
                'name_fr' => 'Certificat d\'Assurance',
                'name_ar' => 'شهادة تأمين',
                'description_en' => 'Business insurance certificate or liability insurance',
                'description_fr' => 'Certificat d\'assurance commerciale ou assurance responsabilité civile',
                'description_ar' => 'شهادة تأمين تجاري أو تأمين المسؤولية',
                'document_type' => 'legal',
                'user_category' => 'store',
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_file_size' => 2048,
                'is_required' => false,
                'is_active' => true,
                'display_order' => 7,
                'notes' => 'Optional but recommended',
            ],
        ];

        foreach ($documents as $document) {
            RequiredDocuments::create($document);
        }

        $this->command->info('Required documents seeded successfully!');
    }
}
