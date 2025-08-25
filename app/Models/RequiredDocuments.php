<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class RequiredDocuments extends Model
{
    use HasFactory, Encryptable;

    protected $table = 'required_documents';

    protected $fillable = [
        'name_ar',
        'name_fr',
        'name_en',
        'description_ar',
        'description_fr',
        'description_en',
        'document_type',
        'user_category',
        'file_types',
        'max_file_size',
        'is_required',
        'is_active',
        'display_order',
        'notes',
    ];

  

    protected $casts = [
        'file_types' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'max_file_size' => 'integer',
        'display_order' => 'integer',
    ];

    protected $encryptable = [
        'name_ar',
        'name_fr',
        'name_en',
        'description_ar',
        'description_fr',
        'description_en',
        'notes',
    ];
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeByUserCategory($query, $category)
    {
        return $query->where('user_category', $category);
    }

    public function scopeByDocumentType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('user_category', $category);
    }

    // Methods
    /**
     * Get document name in specific language
     */
    public function getDocumentName($language = 'en')
    {
        return $this->{"name_$language"} ?? $this->name_en;
    }

    /**
     * Get description in specific language
     */
    public function getDescription($language = 'en')
    {
        return $this->{"description_$language"} ?? $this->description_en;
    }

    /**
     * Get file types as text
     */
    public function getFileTypesText()
    {
        if (!$this->file_types || !is_array($this->file_types)) {
            return 'All types';
        }
        return implode(', ', $this->file_types);
    }

    /**
     * Get max file size as text
     */
    public function getMaxFileSizeText()
    {
        $size = $this->max_file_size ?? 0;
        if ($size >= 1024) {
            return round($size / 1024, 1) . ' MB';
        }
        return $size . ' KB';
    }

    /**
     * Check if document is required
     */
    public function isRequired()
    {
        return $this->is_required ?? false;
    }

    /**
     * Check if document is active
     */
    public function isActive()
    {
        return $this->is_active ?? false;
    }

    /**
     * Get display order
     */
    public function getDisplayOrder()
    {
        return $this->display_order ?? 0;
    }

    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    public function makeRequired()
    {
        $this->is_required = true;
        $this->save();
    }

    public function makeOptional()
    {
        $this->is_required = false;
        $this->save();
    }

    public function getLanguageNames()
    {
        return [
            'en' => $this->document_name_en,
            'fr' => $this->document_name_fr,
            'ar' => $this->document_name_ar,
        ];
    }

    public function getLanguageDescriptions()
    {
        return [
            'en' => $this->description_en,
            'fr' => $this->description_fr,
            'ar' => $this->description_ar,
        ];
    }

    public function getDocumentTypeText()
    {
        $types = [
            'identity' => 'هوية',
            'business' => 'أعمال',
            'financial' => 'مالية',
            'legal' => 'قانونية',
            'other' => 'أخرى',
        ];
        
        return $types[$this->document_type] ?? $this->document_type;
    }

    public function getUserCategoryText()
    {
        $categories = [
            'client' => 'عميل',
            'store' => 'متجر',
            'admin' => 'مشرف',
        ];
        
        return $categories[$this->user_category] ?? $this->user_category;
    }

    public function isFileTypeAllowed($fileType)
    {
        if (!$this->file_types || empty($this->file_types)) {
            return true; // All types allowed if none specified
        }

        $extension = strtolower(pathinfo($fileType, PATHINFO_EXTENSION));
        return in_array($extension, $this->file_types);
    }

    public function isFileSizeValid($fileSizeInBytes)
    {
        $maxSizeInBytes = $this->max_file_size * 1024; // KB to bytes per migration
        return $fileSizeInBytes <= $maxSizeInBytes;
    }

    // Static methods
    public static function getForUserCategory($category)
    {
        return self::where('user_category', $category)
                  ->where('is_active', true)
                  ->ordered()
                  ->get();
    }

    public static function getRequiredForUserCategory($category)
    {
        return self::where('user_category', $category)
                  ->where('is_active', true)
                  ->where('is_required', true)
                  ->ordered()
                  ->get();
    }

    public static function getDocumentTypes()
    {
        return [
            'business_license' => 'رخصة تجارية',
            'tax_certificate' => 'شهادة ضريبية',
            'bank_statement' => 'كشف حساب بنكي',
            'utility_bill' => 'فاتورة خدمات',
            'lease_agreement' => 'عقد إيجار',
            'insurance_certificate' => 'شهادة تأمين',
            'store_photos' => 'صور المتجر',
            'other' => 'أخرى',
        ];
    }

    public static function getUserCategories()
    {
        return [
            'store' => 'متجر',
        ];
    }

    public static function getFileTypes()
    {
        return [
            'pdf' => 'PDF',
            'jpg' => 'JPG',
            'jpeg' => 'JPEG',
            'png' => 'PNG',
            'doc' => 'DOC',
            'docx' => 'DOCX',
        ];
    }

    public static function createDocument($data)
    {
        // Set order if not provided
        if (!isset($data['display_order'])) {
            $maxOrder = self::where('user_category', $data['user_category'])->max('display_order') ?? 0;
            $data['display_order'] = $maxOrder + 1;
        }

        return self::create($data);
    }

    public static function reorderDocuments($userCategory, $documentIds)
    {
        foreach ($documentIds as $index => $documentId) {
            self::where('id', $documentId)
                ->where('user_category', $userCategory)
                ->update(['display_order' => $index + 1]);
        }
    }
}
