<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'redeemed_offer_id',
        'purchase_number',
        'products',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'points_earned',
        'points_spent',
        'status',
        'payment_method',
        'notes',
        'purchase_date',
    ];

    protected $casts = [
        'products' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'points_earned' => 'integer',
        'points_spent' => 'integer',
        'purchase_date' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'redeemed_offer_id');
    }
    // Custom accessor to handle double-encoded JSON
    public function getProductsAttribute($value)
    {
        if (is_string($value)) {
            // Try to decode as JSON first
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // If that fails, try to decode the outer quotes
            $trimmed = trim($value, '"');
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return $value;
    }

    



    public function store()
    {
        return $this->belongsTo(Store::class);
    }

   

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'ratee_id');
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }



    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('purchase_date', [$startDate, $endDate]);
    }

    // Methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded()
    {
        return $this->status === 'refunded';
    }

    public function canBeRefunded()
    {
        return $this->isCompleted() && !$this->isRefunded();
    }

    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->purchase_date = now();
        $this->save();
    }

    public function markAsCancelled()
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public function markAsRefunded()
    {
        $this->status = 'refunded';
        $this->save();
    }

    public function calculatePointsEarned()
    {
        // Basic points calculation: 1 point per 1 currency unit
        $this->points_earned = (int) $this->total_amount;
        $this->save();
        return $this->points_earned;
    }

    public function getDiscountAmount()
    {
        if ($this->redeemedOffer) {
            return $this->redeemedOffer->calculateDiscount($this->total_amount);
        }
        return 0;
    }

    public function getFinalAmount()
    {
        return $this->total_amount - $this->getDiscountAmount();
    }

    public function getFormattedPurchaseDate()
    {
        return $this->purchase_date ? $this->purchase_date->format('Y-m-d H:i:s') : 'N/A';
    }
}
