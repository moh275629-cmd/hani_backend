<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'favorable_type',
        'favorable_id',
    ];

    protected $casts = [
        'favorable_id' => 'integer',
    ];

    /**
     * Get the parent favorable model (store or offer).
     */
    public function favorable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that owns the favorite.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store if this is a store favorite.
     */
    public function store()
    {
        return $this->belongsTo(Store::class, 'favorable_id')->where('favorable_type', 'store');
    }

    /**
     * Get the offer if this is an offer favorite.
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class, 'favorable_id')->where('favorable_type', 'offer');
    }
}