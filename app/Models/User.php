<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Encryptable;
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, Encryptable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'profile_image',
        'profile_image_blob',
        'state',
        'state_code',
        'is_active',
        'email_verified_at',
        'phone_verified_at',
        'id_verification_data',
        'id_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'profile_image_blob',
    ];
    protected $encryptable = [
        'name',
        'email',
        'phone',
        'state',
        'id_verification_data',
        'id_verified_at',
     
        'role',
        'state_code',
      
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'id_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'id_verification_data' => 'array',
    ];

    // Relationships
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function loyaltyCards()
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function receivedRatings()
    {
        return $this->hasMany(Rating::class, 'ratee_id');
    }

    // Scopes
    public function scopeClients($query)
    {
        return $query->where('role', 'client');
    }

    public function scopeStores($query)
    {
        return $query->where('role', 'store');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeGlobalAdmins($query)
    {
        return $query->where('role', 'global_admin');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function isClient()
    {
        return $this->role === 'client';
    }

    public function isStore()
    {
        return $this->role === 'store';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isGlobalAdmin()
    {
        return $this->role === 'global_admin';
    }

    public function hasVerifiedPhone()
    {
        return !is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified()
    {
        $this->phone_verified_at = now();
        $this->save();
    }

    public function markEmailAsVerified()
    {
        $this->email_verified_at = now();
        $this->save();
    }

    /**
     * Set profile image blob
     */
    public function setProfileImageBlob($imageData)
    {
        $this->profile_image_blob = base64_encode($imageData);
    }

    /**
     * Get profile image blob
     */
    public function getProfileImageBlob()
    {
        if (empty($this->profile_image_blob)) {
            return null;
        }
        return base64_decode($this->profile_image_blob);
    }

    /**
     * Check if user has profile image blob
     */
    public function hasProfileImageBlob()
    {
        return !empty($this->profile_image_blob);
    }
    
    /**
     * Get the raw email value for authentication (bypasses accessor)
     */
    public function getRawEmailAttribute()
    {
        return $this->attributes['email'];
    }
    
    /**
     * Get the raw phone value for authentication (bypasses accessor)
     */
    public function getRawPhoneAttribute()
    {
        return $this->attributes['phone'];
    }

    /**
     * Find user by email (handles encrypted emails)
     */
    public static function findByEmail($email)
    {
        // Get all users and check their decrypted emails
        $users = self::all();
        
        foreach ($users as $user) {
            try {
                $decryptedEmail = $user->email; // This will be decrypted by the accessor
                if ($decryptedEmail === $email) {
                    return $user;
                }
            } catch (\Exception $e) {
                // If decryption fails, skip this user
                continue;
            }
        }
        
        return null;
    }

    /**
     * Find user by phone (handles encrypted phones)
     */
    public static function findByPhone($phone)
    {
        // Get all users and check their decrypted phones
        $users = self::all();
        
        foreach ($users as $user) {
            try {
                $decryptedPhone = $user->phone; // This will be decrypted by the accessor
                if ($decryptedPhone === $phone) {
                    return $user;
                }
            } catch (\Exception $e) {
                // If decryption fails, skip this user
                continue;
            }
        }
        
        return null;
    }

    /**
     * Scope to search users by encrypted fields
     */
    public function scopeSearchByEncryptedFields($query, $search)
    {
        // This is a fallback for admin search - not very efficient but handles encrypted fields
        $users = self::all();
        $matchingIds = [];
        
        foreach ($users as $user) {
            try {
                $name = $user->name;
                $email = $user->email;
                $phone = $user->phone;
                
                if (stripos($name, $search) !== false || 
                    stripos($email, $search) !== false || 
                    stripos($phone, $search) !== false) {
                    $matchingIds[] = $user->id;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $query->whereIn('id', $matchingIds);
    }
}
