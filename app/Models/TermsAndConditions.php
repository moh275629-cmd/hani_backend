<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class TermsAndConditions extends Model
{
    use HasFactory, Encryptable;

    protected $table = 'terms_and_conditions';

    protected $fillable = [
        'content_en',
        'content_fr',
        'content_ar',
      
        'is_active',
        'is_published',
        'publisher_id',
        'published_at',
        'notes',
    ];

    protected $encryptable = [
        'content_en',
        'content_fr',
        'content_ar',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByVersion($query, $version)
    {
        return $query->where('version', $version);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeUnpublished($query)
    {
        return $query->whereNull('published_at');
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // Methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function isPublished()
    {
        return (bool) $this->is_published;
    }

    public function activate()
    {
        // Deactivate all other versions first
        self::where('id', '!=', $this->id)->update(['is_active' => false]);
        
        $this->is_active = true;
        $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    public function publish($publishedBy = null)
    {
        $this->published_at = now();
        $this->is_published = true;
        if ($publishedBy) {
            $this->publisher_id = $publishedBy;
        }
        $this->save();
    }

    public function unpublish()
    {
        $this->published_at = null;
        $this->is_published = false;
        $this->save();
    }

    public function getContent($language = 'ar')
    {
        $field = "content_{$language}";
        return $this->$field ?? $this->content_ar; // Fallback to Arabic
    }

    public function getLanguageContent()
    {
        return [
            'en' => $this->content_en,
            'fr' => $this->content_fr,
            'ar' => $this->content_ar,
        ];
    }

    public function getFormattedPublishedDate()
    {
        return $this->published_at ? $this->published_at->format('Y-m-d H:i:s') : 'غير منشور';
    }

    public function getVersionDisplay()
    {
        return "v{$this->version}";
    }

    public function getStatusText()
    {
        if (!$this->isPublished()) {
            return 'مسودة';
        }
        
        if ($this->isActive()) {
            return 'نشط';
        }
        
        return 'غير نشط';
    }

    // Static methods
    public static function getCurrentActive()
    {
        return self::where('is_active', true)->first();
    }

    public static function getLatestVersion()
    {
        return self::orderBy('version', 'desc')->first();
    }

    public static function createNewVersion($contentEn, $contentFr, $contentAr, $notes = null)
    {
        $latest = self::getLatestVersion();
        $newVersion = $latest ? (float)$latest->version + 0.1 : 1.0;

        return self::create([
            'content_en' => $contentEn,
            'content_fr' => $contentFr,
            'content_ar' => $contentAr,
            'version' => number_format($newVersion, 1),
            'is_active' => false,
            'notes' => $notes,
        ]);
    }

    public static function publishNewVersion($versionId, $publishedBy = null)
    {
        $version = self::find($versionId);
        if (!$version) {
            throw new \Exception('Version not found');
        }

        // Deactivate current active version
        $currentActive = self::getCurrentActive();
        if ($currentActive) {
            $currentActive->deactivate();
        }

        // Activate and publish new version
        $version->activate();
        $version->publish($publishedBy);

        return $version;
    }

    public static function getVersionHistory()
    {
        return self::orderBy('version', 'desc')->get();
    }

    public static function validateVersion($version)
    {
        // Check if version format is valid (e.g., 1.0, 1.1, 2.0)
        return preg_match('/^\d+\.\d+$/', $version);
    }

    public static function getNextVersion()
    {
        $latest = self::getLatestVersion();
        if (!$latest) {
            return '1.0';
        }

        $currentVersion = (float)$latest->version;
        $nextVersion = $currentVersion + 0.1;
        return number_format($nextVersion, 1);
    }
}
