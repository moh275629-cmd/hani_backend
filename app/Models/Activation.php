<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Activation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'approved_at',
        'deactivate_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'deactivate_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeExpired($query)
    {
        return $query->where('deactivate_at', '<=', now());
    }

    public function scopeActive($query)
    {
        return $query->where('deactivate_at', '>', now())->orWhereNull('deactivate_at');
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('deactivate_at', '<=', now()->addDays($days))
                    ->where('deactivate_at', '>', now());
    }

    // Methods
    public function isExpired()
    {
        return $this->deactivate_at && $this->deactivate_at->isPast();
    }

    public function isActive()
    {
        return !$this->isExpired();
    }

    public function isExpiringSoon($days = 7)
    {
        return $this->deactivate_at && 
               $this->deactivate_at->isFuture() && 
               $this->deactivate_at->diffInDays(now()) <= $days;
    }

    public function daysUntilExpiration()
    {
        if (!$this->deactivate_at) {
            return 0;
        }
        
        if ($this->isExpired()) {
            return 0;
        }
        
        // Calculate days remaining (positive number)
        $now = now();
        if ($this->deactivate_at->isFuture()) {
            return $now->diffInDays($this->deactivate_at, false);
        }
        
        return 0;
    }

    public function extendActivation($days = 365)
    {
        $this->deactivate_at = now()->addDays($days);
        $this->save();
    }

    public function deactivateNow()
    {
        $this->deactivate_at = now();
        $this->save();
    }

    public function reactivate($days = 365)
    {
        $this->approved_at = now();
        $this->deactivate_at = now()->addDays($days);
        $this->save();
    }
}
