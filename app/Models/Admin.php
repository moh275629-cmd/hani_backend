<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wilaya_code',
        'office_address',
        'office_location_lat',
        'office_location_lng',
        'office_phone',
        'office_email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_code', 'code');
    }
}


