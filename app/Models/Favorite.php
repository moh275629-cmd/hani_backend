<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'favorable_type', // App\Models\Offer, App\Models\Store
        'favorable_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favorable()
    {
        return $this->morphTo();
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'favorable_id')->where('favorable_type', Offer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'favorable_id')->where('favorable_type', Store::class);
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('favorable_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOffers($query)
    {
        return $query->where('favorable_type', Offer::class);
    }

    public function scopeStores($query)
    {
        return $query->where('favorable_type', Store::class);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function isActive()
    {
        return $this->is_active;
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

    public function isOffer()
    {
        return $this->favorable_type === Offer::class;
    }

    public function isStore()
    {
        return $this->favorable_type === Store::class;
    }

    public function getFavorableTypeText()
    {
        $types = [
            Offer::class => 'عرض',
            Store::class => 'متجر',
        ];
        
        return $types[$this->favorable_type] ?? 'غير محدد';
    }

    public function getFavorableName()
    {
        if ($this->favorable) {
            if ($this->isOffer()) {
                return $this->favorable->title;
            } elseif ($this->isStore()) {
                return $this->favorable->store_name;
            }
        }
        return 'غير محدد';
    }

    // Static methods for managing favorites
    public static function toggleOffer($userId, $offerId)
    {
        $existing = self::where('user_id', $userId)
                       ->where('favorable_type', Offer::class)
                       ->where('favorable_id', $offerId)
                       ->first();

        if ($existing) {
            $existing->delete();
            return ['action' => 'removed', 'message' => 'تم إزالة العرض من المفضلة'];
        } else {
            self::create([
                'user_id' => $userId,
                'favorable_type' => Offer::class,
                'favorable_id' => $offerId,
                'is_active' => true,
            ]);
            return ['action' => 'added', 'message' => 'تم إضافة العرض إلى المفضلة'];
        }
    }

    public static function toggleStore($userId, $storeId)
    {
        $existing = self::where('user_id', $userId)
                       ->where('favorable_type', Store::class)
                       ->where('favorable_id', $storeId)
                       ->first();

        if ($existing) {
            $existing->delete();
            return ['action' => 'removed', 'message' => 'تم إزالة المتجر من المفضلة'];
        } else {
            self::create([
                'user_id' => $userId,
                'favorable_type' => Store::class,
                'favorable_id' => $storeId,
                'is_active' => true,
            ]);
            return ['action' => 'added', 'message' => 'تم إضافة المتجر إلى المفضلة'];
        }
    }

    public static function isOfferFavorited($userId, $offerId)
    {
        return self::where('user_id', $userId)
                  ->where('favorable_type', Offer::class)
                  ->where('favorable_id', $offerId)
                  ->where('is_active', true)
                  ->exists();
    }

    public static function isStoreFavorited($userId, $storeId)
    {
        return self::where('user_id', $userId)
                  ->where('favorable_type', Store::class)
                  ->where('favorable_id', $storeId)
                  ->where('is_active', true)
                  ->exists();
    }

    public static function getUserFavorites($userId, $type = null)
    {
        $query = self::where('user_id', $userId)->where('is_active', true);
        
        if ($type) {
            $query->where('favorable_type', $type);
        }
        
        return $query->with('favorable')->orderBy('created_at', 'desc')->get();
    }

    public static function getFavoriteCount($userId, $type = null)
    {
        $query = self::where('user_id', $userId)->where('is_active', true);
        
        if ($type) {
            $query->where('favorable_type', $type);
        }
        
        return $query->count();
    }
}
