<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class Notification extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type', // offer, purchase, refund, system, etc.
        'data', // JSON data for additional information
        'is_read',
        'read_at',
        'action_url',
        'priority', // low, normal, high, urgent
    ];

    protected $encryptable = [
        'title',
        'message',
        'action_url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function markAsRead()
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }

    public function markAsUnread()
    {
        $this->is_read = false;
        $this->read_at = null;
        $this->save();
    }

    public function isUnread()
    {
        return !$this->is_read;
    }

    public function isHighPriority()
    {
        return in_array($this->priority, ['high', 'urgent']);
    }

    public function getPriorityText()
    {
        $priorities = [
            'low' => 'منخفض',
            'normal' => 'عادي',
            'high' => 'عالي',
            'urgent' => 'عاجل',
        ];
        
        return $priorities[$this->priority] ?? $this->priority;
    }

    public function getTypeText()
    {
        $types = [
            'offer' => 'عرض',
            'purchase' => 'شراء',
            'refund' => 'استرجاع',
            'system' => 'نظام',
            'reminder' => 'تذكير',
        ];
        
        return $types[$this->type] ?? $this->type;
    }

    public function getFormattedReadDate()
    {
        return $this->read_at ? $this->read_at->format('Y-m-d H:i:s') : 'غير مقروء';
    }

    public function getDataValue($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    // Static methods for creating notifications
    public static function createOfferNotification($userId, $offerTitle, $offerId)
    {
        return self::create([
            'user_id' => $userId,
            'title' => 'عرض جديد متاح',
            'message' => "عرض جديد: {$offerTitle}",
            'type' => 'offer',
            'data' => ['offer_id' => $offerId],
            'priority' => 'normal',
            'action_url' => "/offers/{$offerId}",
        ]);
    }

    public static function createPurchaseNotification($userId, $amount, $purchaseId)
    {
        return self::create([
            'user_id' => $userId,
            'title' => 'تم تسجيل عملية شراء',
            'message' => "تم تسجيل عملية شراء بقيمة {$amount}",
            'type' => 'purchase',
            'data' => ['purchase_id' => $purchaseId, 'amount' => $amount],
            'priority' => 'normal',
            'action_url' => "/purchases/{$purchaseId}",
        ]);
    }

    public static function createRefundNotification($userId, $refundType, $refundId)
    {
        $typeText = $refundType === 'refund' ? 'استرجاع' : 'تبديل';
        return self::create([
            'user_id' => $userId,
            'title' => "طلب {$typeText}",
            'message' => "تم إنشاء طلب {$typeText} جديد",
            'type' => 'refund',
            'data' => ['refund_id' => $refundId, 'refund_type' => $refundType],
            'priority' => 'high',
            'action_url' => "/refunds/{$refundId}",
        ]);
    }

    public static function createSystemNotification($userId, $title, $message, $priority = 'normal')
    {
        return self::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'system',
            'priority' => $priority,
        ]);
    }
}
