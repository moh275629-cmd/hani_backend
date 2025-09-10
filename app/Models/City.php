<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class City extends Model
{
    use HasFactory, Encryptable;

    protected $table = 'cities';

    protected $fillable = [
        'code',
        'name_en',
        'name_fr',
        'name_ar',
        'wilaya_code',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $encryptable = [
        'name_en',
        'name_fr',
        'name_ar',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_code', 'code');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function stores()
    {
        return $this->hasMany(Store::class, 'city', 'name_en');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'city', 'name_en');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByWilaya($query, $wilayaCode)
    {
        return $query->where('wilaya_code', $wilayaCode);
    }

    // Methods
    public function getName($language = 'en')
    {
        $field = "name_{$language}";
        return $this->$field ?? $this->name_en;
    }

    public function getLanguageNames()
    {
        return [
            'en' => $this->name_en,
            'fr' => $this->name_fr,
            'ar' => $this->name_ar,
        ];
    }

    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    // Static methods
    public static function getByCode($code)
    {
        return self::where('code', $code)->first();
    }

    public static function getByWilaya($wilayaCode)
    {
        return self::active()->byWilaya($wilayaCode)->orderBy('name_en')->get();
    }

    public static function getActiveCities()
    {
        return self::active()->orderBy('name_en')->get();
    }

    public static function createCity($data)
    {
        return self::create([
            'code' => $data['code'],
            'name_en' => $data['name_en'],
            'name_fr' => $data['name_fr'],
            'name_ar' => $data['name_ar'],
            'wilaya_code' => $data['wilaya_code'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
