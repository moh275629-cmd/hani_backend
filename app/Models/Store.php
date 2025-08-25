<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class Store extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'user_id',
        'store_name',
        'description',
        'business_type',
        'phone',
        'email',
        'website',
        'logo',
        'logo_blob',
        'banner',
        'banner_blob',
        'address',
        'city',
        'state',
        'state_code',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'google_place_id',
        'business_hours',
        'payment_methods',
        'services',
        'is_approved',
        'is_active',
        'approved_at',
        'approved_by',
        'approval_notes',
    ];

    // Hide binary fields from JSON responses to prevent UTF-8 encoding issues
    protected $hidden = [
        'logo_blob',
        'banner_blob',
    ];
    protected $encryptable = [
        'store_name',
        'description',
        'business_type',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'state',
        'state_code',
        'postal_code',
        'country',
        // Arrays (business_hours, payment_methods, services) are NOT encrypted
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'business_hours' => 'array',
        'payment_methods' => 'array',
        'services' => 'array',
        // Keep coordinates as strings to avoid decimal cast with encrypted inputs
        'latitude' => 'string',
        'longitude' => 'string',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function loyaltyCards()
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'ratee_id');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }

    public function scopeByBusinessType($query, $businessType)
    {
        return $query->where('business_type', $businessType);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('is_approved', false);
    }

    // Methods
    public function approve()
    {
        $this->is_approved = true;
        $this->approved_at = now();
        $this->save();
    }

    public function reject()
    {
        $this->is_approved = false;
        $this->save();
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

    public function getAverageRating()
    {
        return $this->ratings()->avg('stars') ?? 0;
    }

    public function getTotalRatings()
    {
        return $this->ratings()->count();
    }

    // Image handling methods
    public function setLogoBlob($imageData)
    {
        $this->logo_blob = $imageData;
        $this->save();
    }

    public function setBannerBlob($imageData)
    {
        $this->banner_blob = $imageData;
        $this->save();
    }

    public function getLogoBlob()
    {
        return $this->logo_blob;
    }

    public function getBannerBlob()
    {
        return $this->banner_blob;
    }

    public function hasLogoBlob()
    {
        return !empty($this->logo_blob);
    }

    public function hasBannerBlob()
    {
        return !empty($this->banner_blob);
    }

    /**
     * Scope to search stores by encrypted fields
     */
    public function scopeSearchByEncryptedFields($query, $search)
    {
        // This is a fallback for admin search - not very efficient but handles encrypted fields
        $stores = self::all();
        $matchingIds = [];
        
        foreach ($stores as $store) {
            try {
                $storeName = $store->store_name;
                $description = $store->description;
                $businessType = $store->business_type;
                $city = $store->city;
                $state = $store->state;
                
                if (stripos($storeName, $search) !== false || 
                    stripos($description, $search) !== false || 
                    stripos($businessType, $search) !== false ||
                    stripos($city, $search) !== false ||
                    stripos($state, $search) !== false) {
                    $matchingIds[] = $store->id;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $query->whereIn('id', $matchingIds);
    }
}
