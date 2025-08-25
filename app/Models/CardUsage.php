<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class CardUsage extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'loyalty_card_id',
        'branch_id',
        'used_at',
        'details',
        'scan_type', // manual, qr_scan, nfc
        'location_data', // JSON data for location tracking
        'device_info', // JSON data for device information
        'is_valid',
        'validation_notes',
    ];

    protected $encryptable = [
        'details',
        'validation_notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'location_data' => 'array',
        'device_info' => 'array',
        'is_valid' => 'boolean',
    ];

    // Relationships
    public function loyaltyCard()
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->hasOneThrough(User::class, LoyaltyCard::class, 'id', 'id', 'loyalty_card_id', 'user_id');
    }

    public function store()
    {
        return $this->hasOneThrough(Store::class, LoyaltyCard::class, 'id', 'id', 'loyalty_card_id', 'store_id');
    }

    // Scopes
    public function scopeByCard($query, $cardId)
    {
        return $query->where('loyalty_card_id', $cardId);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->whereHas('loyaltyCard', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->whereHas('loyaltyCard', function($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
    }

    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    public function scopeInvalid($query)
    {
        return $query->where('is_valid', false);
    }

    public function scopeByScanType($query, $type)
    {
        return $query->where('scan_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('used_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('used_at', '>=', now()->subDays($days));
    }

    // Methods
    public function isValid()
    {
        return $this->is_valid;
    }

    public function markAsValid()
    {
        $this->is_valid = true;
        $this->save();
    }

    public function markAsInvalid($notes = null)
    {
        $this->is_valid = false;
        $this->validation_notes = $notes;
        $this->save();
    }

    public function getScanTypeText()
    {
        $types = [
            'manual' => 'يدوي',
            'qr_scan' => 'مسح QR',
            'nfc' => 'NFC',
        ];
        
        return $types[$this->scan_type] ?? $this->scan_type;
    }

    public function getFormattedUsageDate()
    {
        return $this->used_at ? $this->used_at->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getLocationInfo()
    {
        if (!$this->location_data) {
            return null;
        }

        $location = $this->location_data;
        $info = [];

        if (isset($location['latitude']) && isset($location['longitude'])) {
            $info[] = "إحداثيات: {$location['latitude']}, {$location['longitude']}";
        }

        if (isset($location['address'])) {
            $info[] = "العنوان: {$location['address']}";
        }

        if (isset($location['city'])) {
            $info[] = "المدينة: {$location['city']}";
        }

        return implode(' | ', $info);
    }

    public function getDeviceInfo()
    {
        if (!$this->device_info) {
            return null;
        }

        $device = $this->device_info;
        $info = [];

        if (isset($device['platform'])) {
            $info[] = "المنصة: {$device['platform']}";
        }

        if (isset($device['version'])) {
            $info[] = "الإصدار: {$device['version']}";
        }

        if (isset($device['model'])) {
            $info[] = "النموذج: {$device['model']}";
        }

        return implode(' | ', $info);
    }

    // Static methods for creating usage records
    public static function createQrScan($loyaltyCardId, $branchId, $details = null, $locationData = null, $deviceInfo = null)
    {
        return self::create([
            'loyalty_card_id' => $loyaltyCardId,
            'branch_id' => $branchId,
            'used_at' => now(),
            'details' => $details,
            'scan_type' => 'qr_scan',
            'location_data' => $locationData,
            'device_info' => $deviceInfo,
            'is_valid' => true,
        ]);
    }

    public static function createManualEntry($loyaltyCardId, $branchId, $details = null)
    {
        return self::create([
            'loyalty_card_id' => $loyaltyCardId,
            'branch_id' => $branchId,
            'used_at' => now(),
            'details' => $details,
            'scan_type' => 'manual',
            'is_valid' => true,
        ]);
    }

    public static function createNfcScan($loyaltyCardId, $branchId, $details = null, $deviceInfo = null)
    {
        return self::create([
            'loyalty_card_id' => $loyaltyCardId,
            'branch_id' => $branchId,
            'used_at' => now(),
            'details' => $details,
            'scan_type' => 'nfc',
            'device_info' => $deviceInfo,
            'is_valid' => true,
        ]);
    }
}
