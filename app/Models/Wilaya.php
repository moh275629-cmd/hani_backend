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
      
        'created_by',
        'updated_by',
    ];

   

    protected $casts = [
        'is_active' => 'boolean',
        'admin_office_latitude' => 'decimal:8',
        'admin_office_longitude' => 'decimal:8',
    ];

    // Relationships
    public function admin()
    {
        return $this->hasOne(Admin::class, 'wilaya_code', 'code');
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
        return $this->admin()->exists();
    }

    public function assignAdmin($userId)
    {
        // This method is now handled by the Admin model creation
        // The GlobalAdminController creates Admin records with wilaya_code
    }

    public function removeAdmin()
    {
        // This method is now handled by deleting the Admin record
        $this->admin()?->delete();
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
        return self::active()->orderByRaw('CAST(code AS UNSIGNED)')->get();
    }

    public static function getWilayasWithoutAdmin()
    {
        return self::active()->withoutAdmin()->orderByRaw('CAST(code AS UNSIGNED)')->get();
    }

    public static function createWilaya($data)
    {
        return self::create([
            'code' => $data['code'],
            'name_en' => $data['name_en'],
            'name_fr' => $data['name_fr'],
            'name_ar' => $data['name_ar'],
            'is_active' => $data['is_active'] ?? true,
               'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
