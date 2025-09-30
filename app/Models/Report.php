<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'description',
        'status',
        'is_auto_generated',
        'profanity_score',
        'detected_words',
        'context',
        'context_id',
        'original_text_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'detected_words' => 'array',
        'profanity_score' => 'float',
    ];

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    // Methods
    public function markAsUnderReview()
    {
        $this->status = 'under_review';
        $this->save();
    }

    public function markAsResolved()
    {
        $this->status = 'resolved';
        $this->save();
    }

    public function markAsDismissed()
    {
        $this->status = 'dismissed';
        $this->save();
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isUnderReview()
    {
        return $this->status === 'under_review';
    }

    public function isResolved()
    {
        return $this->status === 'resolved';
    }

    public function isDismissed()
    {
        return $this->status === 'dismissed';
    }
}
