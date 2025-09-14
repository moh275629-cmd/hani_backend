<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequiredDocuments extends Model
{
    use HasFactory;

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

    // Methods
    public function getDocumentName($language = 'en')
    {
        return $this->{"name_$language"} ?? $this->name_en;
    }

    public function getDescription($language = 'en')
    {
        return $this->{"description_$language"} ?? $this->description_en;
    }

    public function getFileTypesText()
    {
        if (!$this->file_types || !is_array($this->file_types)) {
            return 'All types';
        }
        return implode(', ', $this->file_types);
    }

    public function getMaxFileSizeText()
    {
        $size = $this->max_file_size ?? 0;
        if ($size >= 1024) {
            return round($size / 1024, 1) . ' MB';
        }
        return $size . ' KB';
    }

    public function isRequired()
    {
        return $this->is_required ?? false;
    }

    public function isActive()
    {
        return $this->is_active ?? false;
    }

    public function getDisplayOrder()
    {
        return $this->display_order ?? 0;
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
            'identity' => 'Identity Document',
            'business' => 'Business Document', 
            'financial' => 'Financial Document',
            'legal' => 'Legal Document',
            'other' => 'Other Document',
        ];
    }

    public static function getUserCategories()
    {
        return [
            'client' => 'Client',
            'store' => 'Store',
            'admin' => 'Admin',
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
}