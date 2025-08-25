<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class RatingOffer extends Model
{
    use HasFactory;

    protected $table = 'rating_offers';

    protected $fillable = [
        'rater_id',
        'rated_offer_id',
        'stars',
        'comment',
    ];

    

    protected $casts = [
        'stars' => 'integer',
    ];

    // Relationships
    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratedOffer()
    {
        return $this->belongsTo(Offer::class, 'rated_offer_id');
    }

    // Scopes
    public function scopeByRater($query, $raterId)
    {
        return $query->where('rater_id', $raterId);
    }

    public function scopeByRatedOffer($query, $offerId)
    {
        return $query->where('rated_offer_id', $offerId);
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

    // Validation rules
    public static function rules()
    {
        return [
            'stars' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ];
    }
}
