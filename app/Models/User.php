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
        'is_approved',
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
        'is_approved' => 'boolean',
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

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function reportedReports()
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function activation()
    {
        return $this->hasOne(Activation::class);
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

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('is_approved', false);
    }

    // Methods
    public function isClient()
    {
        // Since role is encrypted, we need to decrypt it first
        try {
            $decryptedRole = $this->role;
            return $decryptedRole === 'client';
        } catch (\Exception $e) {
            // If decryption fails, return false
            return false;
        }
    }

    public function isStore()
    {
        // Since role is encrypted, we need to decrypt it first
        try {
            $decryptedRole = $this->role;
            return $decryptedRole === 'store';
        } catch (\Exception $e) {
            // If decryption fails, return false
            return false;
        }
    }

    public function isAdmin()
    {
        // Since role is encrypted, we need to decrypt it first
        try {
            $decryptedRole = $this->role;
            return $decryptedRole === 'admin';
        } catch (\Exception $e) {
            // If decryption fails, return false
            return false;
        }
    }

    public function isGlobalAdmin()
    {
        // Since role is encrypted, we need to decrypt it first
        try {
            $decryptedRole = $this->role;
            return $decryptedRole === 'global_admin';
        } catch (\Exception $e) {
            // If decryption fails, return false
            return false;
        }
    }

    public function hasVerifiedPhone()
    {
        return !is_null($this->phone_verified_at);
    }

    public function isApproved()
    {
        return $this->is_approved;
    }

    public function approve()
    {
        $this->is_approved = true;
        $this->save();
        
        // Create or update activation record
        $this->activation()->updateOrCreate(
            ['user_id' => $this->id],
            [
                'approved_at' => now(),
                'deactivate_at' => now()->addYear(),
            ]
        );
    }

    public function reject()
    {
        $this->is_approved = false;
        $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;
        $this->is_approved = false;
        $this->save();
        
        // Update activation record
        if ($this->activation) {
            $this->activation->deactivateNow();
        }
    }

    public function reactivate($days = 365)
    {
        $this->is_active = true;
        $this->is_approved = true;
        $this->save();
        
        // Update activation record
        $this->activation()->updateOrCreate(
            ['user_id' => $this->id],
            [
                'approved_at' => now(),
                'deactivate_at' => now()->addDays($days),
            ]
        );
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
