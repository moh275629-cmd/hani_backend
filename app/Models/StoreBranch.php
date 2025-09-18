<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'wilaya_code',
        'city',
        'address',
        'phone',
        'latitude',
        'longitude',
        'gps_address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'offer_branches', 'branch_id', 'offer_id')->withTimestamps();
    }
}
