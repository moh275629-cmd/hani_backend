<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;


class Rating extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'rater_id',
        'ratee_id',
        'rater_role', // user, store
        'ratee_role', // user, store
        'stars',
        'comment',
        'rating_type', // purchase, service, overall
        'is_verified',
    ];

    protected $encryptable = [
        'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
        'is_verified' => 'boolean',
    ];

    // Relationships
    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee()
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    // Scopes
    public function scopeByRater($query, $raterId)
    {
        return $query->where('rater_id', $raterId);
    }

    public function scopeByRatee($query, $rateeId)
    {
        return $query->where('ratee_id', $rateeId);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('rater_role', $role);
    }

    public function scopeByRatingType($query, $type)
    {
        return $query->where('rating_type', $type);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByStars($query, $stars)
    {
        return $query->where('stars', $stars);
    }

    public function scopeHighRating($query, $minStars = 4)
    {
        return $query->where('stars', '>=', $minStars);
    }

    public function scopeLowRating($query, $maxStars = 2)
    {
        return $query->where('stars', '<=', $maxStars);
    }

    // Methods
    public function isVerified()
    {
        return $this->is_verified;
    }

    public function verify()
    {
        $this->is_verified = true;
        $this->save();
    }

    public function unverify()
    {
        $this->is_verified = false;
        $this->save();
    }

    public function getStarsText()
    {
        $stars = $this->stars;
        if ($stars == 5) return 'ممتاز';
        if ($stars == 4) return 'جيد جداً';
        if ($stars == 3) return 'جيد';
        if ($stars == 2) return 'مقبول';
        if ($stars == 1) return 'ضعيف';
        return 'غير محدد';
    }

    public function getRatingTypeText()
    {
        $types = [
            'purchase' => 'الشراء',
            'service' => 'الخدمة',
            'overall' => 'عام',
        ];
        
        return $types[$this->rating_type] ?? $this->rating_type;
    }

    public function getRaterRoleText()
    {
        $roles = [
            'user' => 'عميل',
            'store' => 'متجر',
        ];
        
        return $roles[$this->rater_role] ?? $this->rater_role;
    }

    public function getRateeRoleText()
    {
        $roles = [
            'user' => 'عميل',
            'store' => 'متجر',
        ];
        
        return $roles[$this->ratee_role] ?? $this->ratee_role;
    }

    // Validation rules
    public static function rules()
    {
        return [
            'stars' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
            'rating_type' => 'required|in:purchase,service,overall',
            'rater_role' => 'required|in:user,store',
            'ratee_role' => 'required|in:user,store',
        ];
    }
}
