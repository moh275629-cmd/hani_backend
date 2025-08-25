<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoyaltyCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'card_number',
        'qr_code',
    ];

   

 

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

  

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeValid($query)
    {
        // Note: is_active and expires_at fields don't exist in current database schema
        // For now, return all cards as valid
        return $query;
    }

    public function scopeForClients($query)
    {
        return $query->whereHas('user', function($q) {
            $q->where('role', 'client');
        });
    }

    // Methods
    public static function generateQrCode($userId = null, $cardNumber = null, $storeId = null)
    {
        // Generate proper QR code data structure
        $qrData = [
            'card_number' => $cardNumber ?? 'TEMP_' . strtoupper(Str::random(6)),
            'user_id' => $userId ?? 0,
            'timestamp' => time()
        ];
        
        // Only include store_id if it's not null
        if ($storeId !== null) {
            $qrData['store_id'] = $storeId;
        }
        
        return json_encode($qrData);
    }

    public static function generateCardNumber()
    {
        do {
            $cardNumber = 'CARD_' . strtoupper(Str::random(6)) . '_' . time();
        } while (self::where('card_number', $cardNumber)->exists());
        
        return $cardNumber;
    }

     public function getQrCodeData()
    {
        return [
            'card_id' => $this->id,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'qr_code' => $this->qr_code,
            'card_number' => $this->card_number,
            
        ];
    }
}
