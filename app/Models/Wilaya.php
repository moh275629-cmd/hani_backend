<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class Wilaya extends Model
{
    use HasFactory, Encryptable;

    protected $table = 'wilayas';

    protected $fillable = [
        'code',
        'name_en',
        'name_fr', 
        'name_ar',
        'is_active',
        'admin_office_address',
        'admin_office_latitude',
        'admin_office_longitude',
        'admin_office_phone',
        'admin_office_email',
        'admin_user_id',
        'created_by',
        'updated_by',
    ];

    protected $encryptable = [
        'name_en',
        'name_fr',
        'name_ar',
        'admin_office_address',
        'admin_office_phone',
        'admin_office_email',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'admin_office_latitude' => 'decimal:8',
        'admin_office_longitude' => 'decimal:8',
    ];

    // Relationships
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'wilaya_code', 'code');
    }

    public function stores()
    {
        return $this->hasMany(Store::class, 'state_code', 'code');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'state', 'code');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithAdmin($query)
    {
        return $query->whereNotNull('admin_user_id');
    }

    public function scopeWithoutAdmin($query)
    {
        return $query->whereNull('admin_user_id');
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

    public function hasAdmin()
    {
        return !is_null($this->admin_user_id);
    }

    public function assignAdmin($userId)
    {
        $this->admin_user_id = $userId;
        $this->save();
    }

    public function removeAdmin()
    {
        $this->admin_user_id = null;
        $this->save();
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

    public static function getActiveWilayas()
    {
        return self::active()->orderBy('code')->get();
    }

    public static function getWilayasWithoutAdmin()
    {
        return self::active()->withoutAdmin()->orderBy('code')->get();
    }

    public static function createWilaya($data)
    {
        return self::create([
            'code' => $data['code'],
            'name_en' => $data['name_en'],
            'name_fr' => $data['name_fr'],
            'name_ar' => $data['name_ar'],
            'is_active' => $data['is_active'] ?? true,
            'admin_office_address' => $data['admin_office_address'] ?? null,
            'admin_office_latitude' => $data['admin_office_latitude'] ?? null,
            'admin_office_longitude' => $data['admin_office_longitude'] ?? null,
            'admin_office_phone' => $data['admin_office_phone'] ?? null,
            'admin_office_email' => $data['admin_office_email'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
