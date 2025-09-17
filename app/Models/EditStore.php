<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class EditStore extends Model
{
    use HasFactory, Encryptable;

    protected $fillable = [
        'store_id',
        'user_id',
        'store_name',
        'description',
        'business_type',
        'phone',
        'email',
        'website',
        'logo',
        'banner',
        'gallery_images',
        'main_image_url',
        'address',
        'city',
        'state',
        'state_code',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'google_place_id',
        'business_hours',
        'payment_methods',
        'services',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'user_name',
        'user_phone',
        'user_state',
        'is_wilaya_change',
        'current_wilaya_code',
        'target_wilaya_code',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'payment_methods' => 'array',
        'services' => 'array',
        'gallery_images' => 'array',
        'reviewed_at' => 'datetime',
        'is_wilaya_change' => 'boolean',
    ];

    protected $encryptable = [
        'store_name',
        'description',
        'business_type',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'state',
        'state_code',
        'postal_code',
        'country',
        'user_name',
        'user_phone',
        'user_state',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByState($query, $stateCode)
    {
        return $query->where('state_code', $stateCode);
    }

    // Methods
    public function approve($reviewerId)
    {
        $this->status = 'approved';
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->save();

        // Apply the changes to the actual store
        $this->applyChanges();
    }

    public function reject($reviewerId, $reason = null)
    {
        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->save();
    }

    private function applyChanges()
{
    // Load the store and user with fresh data
    $store = Store::find($this->store_id);
    $user = User::find($this->user_id);
    
    if (!$store) {
        \Log::error("Store not found for edit request ID: {$this->id}, store_id: {$this->store_id}");
        throw new \Exception("Store not found");
    }
    
    if (!$user) {
        \Log::error("User not found for edit request ID: {$this->id}, user_id: {$this->user_id}");
        throw new \Exception("User not found");
    }
    
    \Log::info("Applying changes to store ID: {$store->id}");
    
    // Build update data for store
    $storeUpdateData = [];
    $changes = [];
    
    // Store fields
    $storeFields = [
        'store_name', 'description', 'business_type', 'phone', 'email', 'website',
        'logo', 'banner', 'address', 'city', 'state', 'state_code', 'postal_code',
        'country', 'google_place_id', 'business_hours', 'payment_methods', 'services',
    ];
    
    foreach ($storeFields as $field) {
        if ($this->{$field} !== null) {
            $storeUpdateData[$field] = $this->{$field}; // Pass plain value
            $changes[$field] = $this->{$field};
        }
    }
    
    // Handle latitude and longitude separately to ensure they are decrypted
    if ($this->latitude !== null) {
        // Get the raw encrypted value from the database and decrypt it manually
        $rawLatitude = $this->getRawOriginal('latitude');
        if ($rawLatitude !== null) {
            try {
                $decryptedLatitude = \Illuminate\Support\Facades\Crypt::decryptString($rawLatitude);
                if (is_numeric($decryptedLatitude)) {
                    $storeUpdateData['latitude'] = (float) $decryptedLatitude;
                    $changes['latitude'] = $decryptedLatitude;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to decrypt latitude for edit request ID: {$this->id}");
            }
        }
    }
    
    if ($this->longitude !== null) {
        // Get the raw encrypted value from the database and decrypt it manually
        $rawLongitude = $this->getRawOriginal('longitude');
        if ($rawLongitude !== null) {
            try {
                $decryptedLongitude = \Illuminate\Support\Facades\Crypt::decryptString($rawLongitude);
                if (is_numeric($decryptedLongitude)) {
                    $storeUpdateData['longitude'] = (float) $decryptedLongitude;
                    $changes['longitude'] = $decryptedLongitude;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to decrypt longitude for edit request ID: {$this->id}");
            }
        }
    }
    
    // Update store
    $storeSaved = !empty($storeUpdateData) ? $store->update($storeUpdateData) : true;
    
    // User fields
    $userUpdateData = [];
    $userFields = ['user_name', 'user_phone', 'user_state'];
    foreach ($userFields as $field) {
        if ($this->{$field} !== null) {
            $userFieldName = str_replace('user_', '', $field);
            $userUpdateData[$userFieldName] = $this->{$field};
            $changes[$field] = $this->{$field};
        }
    }
    
    // Update user
    $userSaved = !empty($userUpdateData) ? $user->update($userUpdateData) : true;
    
    \Log::info("Changes applied:", $changes);
    \Log::info("Store save result: " . ($storeSaved ? 'success' : 'failed'));
    \Log::info("User save result: " . ($userSaved ? 'success' : 'failed'));
    
    if (!$storeSaved || !$userSaved) {
        throw new \Exception("Failed to save changes");
    }
}

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isWilayaChange()
    {
        return $this->is_wilaya_change === true;
    }

    public function getCurrentWilayaCode()
    {
        return $this->current_wilaya_code;
    }

    public function getTargetWilayaCode()
    {
        return $this->target_wilaya_code;
    }

    // Scope for wilaya change requests
    public function scopeWilayaChanges($query)
    {
        return $query->where('is_wilaya_change', true);
    }

    // Scope for regular edit requests
    public function scopeRegularEdits($query)
    {
        return $query->where('is_wilaya_change', false);
    }
}