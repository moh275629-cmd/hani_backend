<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class Refund extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'purchase_id',
        'refund_type', // refund, exchange
        'amount',
        'points_adjusted',
        'reason',
        'note',
        'processed_by',
        'refund_date',
        'status', // pending, approved, rejected, completed
    ];

    protected $encryptable = [
        'reason',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'points_adjusted' => 'integer',
        'refund_date' => 'datetime',
    ];

    // Relationships
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('refund_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('refund_date', [$startDate, $endDate]);
    }

    // Methods
    public function isRefund()
    {
        return $this->refund_type === 'refund';
    }

    public function isExchange()
    {
        return $this->refund_type === 'exchange';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function approve()
    {
        $this->status = 'approved';
        $this->save();
    }

    public function reject()
    {
        $this->status = 'rejected';
        $this->save();
    }

    public function complete()
    {
        $this->status = 'completed';
        $this->refund_date = now();
        $this->save();
    }

    public function getFormattedRefundDate()
    {
        return $this->refund_date ? $this->refund_date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getRefundTypeText()
    {
        return $this->refund_type === 'refund' ? 'استرجاع' : 'تبديل';
    }

    public function getStatusText()
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
        ];
        
        return $statuses[$this->status] ?? $this->status;
    }
}
