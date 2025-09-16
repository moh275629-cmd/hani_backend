<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'title',
        'description',
        'image',
        'image_blob',
        'main_media_url',
        'gallery_media',
        'valid_from',
        'valid_until',
        'discount_type',
        'discount_value',
        'old_price',
        'minimum_purchase',
        'max_usage_per_user',
        'total_usage_limit',
        'current_usage_count',
        'is_active',
        'is_featured',
        'multi_check_enabled',
        'terms',
        'applicable_products',
        'excluded_products',
    ];

    // Hide binary fields from JSON responses to prevent UTF-8 encoding issues
    protected $hidden = [
        'image_blob',
    ];

    // Plain storage; remove per-field encryption to avoid exposing ciphertext in API

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'discount_value' => 'decimal:2',
        'old_price' => 'decimal:2',
        'minimum_purchase' => 'decimal:2',
        'max_usage_per_user' => 'integer',
        'total_usage_limit' => 'integer',
        'current_usage_count' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'multi_check_enabled' => 'boolean',
        'terms' => 'array',
        'applicable_products' => 'array',
        'excluded_products' => 'array',
        'gallery_media' => 'array',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'redeemed_offer_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function ratings()
    {
        return $this->hasMany(RatingOffer::class, 'rated_offer_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $now = now();
        return $query->where('valid_from', '<=', $now)
                    ->where('valid_until', '>=', $now)
                    ->where('is_active', true);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('offer_type', $type);
    }

    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('valid_from', '>', now());
    }

    // Methods
    public function isActive()
    {
        $now = now();
        return $this->is_active && 
               $this->valid_from <= $now && 
               $this->valid_until >= $now;
    }

    public function isExpired()
    {
        return $this->valid_until < now();
    }

    public function isUpcoming()
    {
        return $this->valid_from > now();
    }

    public function canBeUsed()
    {
        return $this->isActive() && 
               ($this->total_usage_limit === null || $this->current_usage_count < $this->total_usage_limit);
    }

    public function incrementUsage()
    {
        $this->increment('current_usage_count');
    }

    public function calculateDiscount($purchaseAmount)
    {
        if ($this->discount_type === 'percentage') {
            $discount = ($purchaseAmount * $this->discount_value) / 100;
            if ($this->total_usage_limit) { // Assuming total_usage_limit is the max discount
                $discount = min($discount, $this->total_usage_limit);
            }
            return $discount;
        } elseif ($this->discount_type === 'fixed') {
            return $this->discount_value;
        }
        
        return 0;
    }

    public function getRemainingDays()
    {
        return max(0, now()->diffInDays($this->valid_until, false));
    }

    public function getUsagePercentage()
    {
        if ($this->total_usage_limit === null) {
            return 0;
        }
        return ($this->current_usage_count / $this->total_usage_limit) * 100;
    }

    // Image handling methods
    public function setImageBlob($imageData)
    {
        $this->image_blob = $imageData;
        $this->save();
    }

    public function getImageBlob()
    {
        return $this->image_blob;
    }

    public function hasImageBlob()
    {
        return !empty($this->image_blob);
    }

    // Cloudinary media methods
    public function setMainMedia($cloudinaryUrl)
    {
        $this->main_media_url = $cloudinaryUrl;
        $this->save();
    }

    public function addGalleryMedia($cloudinaryUrl, $mediaType = 'image')
    {
        $gallery = $this->gallery_media ?? [];
        $gallery[] = [
            'url' => $cloudinaryUrl,
            'type' => $mediaType,
            'created_at' => now()->toISOString(),
        ];
        $this->gallery_media = $gallery;
        $this->save();
    }

    public function removeGalleryMedia($cloudinaryUrl)
    {
        $gallery = $this->gallery_media ?? [];
        $gallery = array_filter($gallery, function($media) use ($cloudinaryUrl) {
            return $media['url'] !== $cloudinaryUrl;
        });
        $this->gallery_media = array_values($gallery);
        $this->save();
    }

    public function getMainMediaUrl()
    {
        return $this->main_media_url;
    }

    public function getGalleryMedia()
    {
        return $this->gallery_media ?? [];
    }

    public function hasMainMedia()
    {
        return !empty($this->main_media_url);
    }

    public function hasGalleryMedia()
    {
        return !empty($this->gallery_media) && count($this->gallery_media) > 0;
    }

    public function getImages()
    {
        $gallery = $this->getGalleryMedia();
        return array_filter($gallery, function($media) {
            return $media['type'] === 'image';
        });
    }

    public function getVideos()
    {
        $gallery = $this->getGalleryMedia();
        return array_filter($gallery, function($media) {
            return $media['type'] === 'video';
        });
    }

    public function getAverageRating()
    {
        return $this->ratings()->avg('stars') ?? 0;
    }

    public function getTotalRatings()
    {
        return $this->ratings()->count();
    }

    public function getRatingDistribution()
    {
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $this->ratings()->where('stars', $i)->count();
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $this->getTotalRatings() > 0 ? ($count / $this->getTotalRatings()) * 100 : 0
            ];
        }
        return $distribution;
    }
}
