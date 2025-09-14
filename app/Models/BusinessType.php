<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'description',
        'is_active',
        'is_system_defined',
        'usage_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system_defined' => 'boolean',
        'usage_count' => 'integer',
    ];

    // Relationships
    public function stores()
    {
        return $this->hasMany(Store::class, 'business_type', 'key');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystemDefined($query)
    {
        return $query->where('is_system_defined', true);
    }

    public function scopeUserDefined($query)
    {
        return $query->where('is_system_defined', false);
    }

    // Methods
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    public function decrementUsage()
    {
        $this->decrement('usage_count');
    }

    public static function getActiveTypes()
    {
        return self::active()->orderBy('is_system_defined', 'desc')->orderBy('name')->get();
    }

    public static function getSystemTypes()
    {
        return self::active()->systemDefined()->orderBy('name')->get();
    }

    public static function getUserDefinedTypes()
    {
        return self::active()->userDefined()->orderBy('name')->get();
    }
}
