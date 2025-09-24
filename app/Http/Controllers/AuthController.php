<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeClientMail;
use App\Mail\WelcomeStoreMail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;


class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:client,store,admin',
            'state' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed '.$validator->errors(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if email already exists (handles encrypted emails)
        $existingUser = User::findByEmail($request->email);
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Email already exists',
                'errors' => ['email' => ['The email has already been taken.']]
            ], 422);
        }

        // Check if phone already exists (handles encrypted phones)
        $existingUserByPhone = User::findByPhone($request->phone);
        if ($existingUserByPhone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already exists',
                'errors' => ['phone' => ['The phone number has already been taken.']]
            ], 422);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'state' => $request->state,
            'is_active' => false, // ALL users start as inactive (need OTP verification)
            'is_approved' => false, // Both store and client need approval
            'id_verified' => false, // Will be set to true after manual verification
        ]);
        
        

        // Create authentication token only for admin users (clients and stores need approval)
        $token = null;
        if ($request->role === 'admin') {
            $token = $user->createToken('auth-token')->plainTextToken;
        }

        return response()->json([
            'success' => true,
            'message' => $request->role === 'store' 
                ? 'Store registered successfully. Please verify your email and wait for admin approval.' 
                : 'Client registered successfully. Please wait for admin approval before logging in.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'state' => $user->state,
            ],
            'token' => $token,
            'requires_approval' => $request->role === 'store' || $request->role === 'client',
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Try to find user by email (handles encrypted emails)
        $user = User::findByEmail($request->email);
        
        if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                'message' => 'Invalid email or password'
                ], 401);
        }
        
        // Manually log in the user
        Auth::login($user);

        $user = Auth::user();
        
        // Check client approval FIRST - clients cannot login without approval
        if ($user->role === 'client' && !$user->is_approved) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Your client account is pending admin approval. Please wait for approval before logging in.',
                'requires_approval' => true
            ], 403);
        }
        
        // Don't allow login if user is not active - send OTP and redirect to verification
        if (!$user->is_active) {
            // Send OTP email automatically
            try {
                $this->_sendEmailOtp($user->email, $user->role);
            } catch (\Exception $e) {
                \Log::error("Failed to send OTP during login: " . $e->getMessage());
            }
            
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in. Check your email for the verification code.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 401);
        }
        
        // Check store approval only if user is active
        if ($user->role === 'store' && $user->is_active) {
            $store = \App\Models\Store::where('user_id', $user->id)->first();
            
            if ($store && !$store->is_approved) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your store account is pending admin approval. Please wait for approval before logging in.',
                    'requires_approval' => true
                ], 403);
            }
        }

        // Remove the duplicate client approval check since we already did it above
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'state' => $user->state,
                'state_code' => $user->state_code,
                'is_active' => $user->is_active,
            ],
            'token' => $token,
            'is_active' => $user->is_active, // Add this for easy access
        ];

        // Add store approval status for store users
        if ($user->role === 'store') {
            $store = \App\Models\Store::where('user_id', $user->id)->first();
            if ($store) {
                $response['store'] = [
                    'is_approved' => $store->is_approved,
                    'approved_at' => $store->approved_at,
                    'approval_notes' => $store->approval_notes,
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * Send OTP for email verification
     */
    public function sendEmailOtp(Request $request)
    {
        \Log::info("sendEmailOtp called with data: " . json_encode($request->all()));
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            \Log::error("sendEmailOtp validation failed: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        
        // Check if user exists (for resend requests)
        $user = User::findByEmail($email);
        
        // If user doesn't exist, this might be during registration
        // We'll still send the OTP but won't associate it with a user yet
        if (!$user) {
            \Log::info("User not found for email: $email, but proceeding with OTP creation");
            // This could be during registration, so we'll send OTP anyway
            // The OTP will be stored and can be verified later
        }
        
        try {
            $userRole = $user ? $user->role : null;
            $this->_sendEmailOtp($email, $userRole);
            
            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error("sendEmailOtp failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send OTP for phone verification
     */
    public function sendPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        $this->_sendPhoneOtp($phone);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your phone successfully'
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identifier' => 'required|string', // email or phone
                'otp' => 'required|string|size:6',
                'type' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Accept any valid, unused OTP matching the provided code for this identifier/type
            $otp = Otp::forIdentifier($request->identifier)
                ->ofType($request->type)
                ->where('otp', $request->otp)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();
            
            if (!$otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 400);
            }

            // Mark OTP as used
            $otp->update([
                'is_used' => true,
                'used_at' => now(),
            ]);

            // Update user verification status (handle encrypted fields via findByEmail/findByPhone)
            if ($request->type === 'email') {
                $user = User::findByEmail($request->identifier);
            } elseif ($request->type === 'phone') {
                $user = User::findByPhone($request->identifier);
            } else {
                $user = null;
            }
            $store = null;
            if ($user) {
                if ($request->type === 'email') {
                    $user->markEmailAsVerified();
                    $user->is_active = true;
                    $user->save();
                } else {
                    $user->markPhoneAsVerified();
                    $user->is_active = true;
                    $user->save();
                }
                
                // For store users, activate them after OTP verification
                if ($user->role === 'store') {
                    $user->is_active = true;
                    $user->save();
                    // Fetch the related store and send the store registration email
                    $store = \App\Models\Store::where('user_id', $user->id)->first();
                    if ($store) {
                        $this->_sendStoreRegistrationEmail($user, $store);
                    }
                    // Ensure store is correctly loaded
                    if (!$store) {
                        // Auto-create a minimal store record so store endpoints work
                        try {
                            $store = \App\Models\Store::create([
                                'user_id' => $user->id,
                                'store_name' => $user->name ?? 'My Store',
                                'state' => $user->state ?? null,
                                'is_active' => true,
                                'is_approved' => false,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Failed to auto-create store on OTP verify: ' . $e->getMessage());
                        }
                    }
                    // Reload store
                    $store = $store ?: \App\Models\Store::where('user_id', $user->id)->first();
                }
                
                // Send welcome email after OTP verification
                try {
                    $adminInfo = $this->_getAdminInfoForUser($user);
                    
                    if ($user->role === 'client') {
                        Mail::to($user->email)->send(new WelcomeClientMail($user, $adminInfo));
                    } elseif ($user->role === 'store' && $store) {
                        Mail::to($user->email)->send(new WelcomeStoreMail($user, $store, $adminInfo));
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to send welcome email: ' . $e->getMessage());
                    // Don't fail the OTP verification if email fails
                }
                
                // Create authentication token only for approved users or non-client users
                $token = null;
                if ($user->role !== 'client' || $user->is_approved) {
                    $token = $user->createToken('auth-token')->plainTextToken;
                }
                
                // Prepare response message based on user role and approval status
                $message = 'OTP verified successfully';
                if ($user->role === 'client' && !$user->is_approved) {
                    $message = 'Email verified successfully. Your account is pending admin approval. Please wait for approval before logging in.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'state' => $user->state,
                        'is_active' => $user->is_active,
                        'is_approved' => $user->is_approved,
                    ],
                    'store' => $store,
                    'token' => $token,
                    'requires_approval' => $user->role === 'client' && !$user->is_approved,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully'
            ]);
            $otp->delete();
        } catch (\Exception $e) {
            \Log::error('OTP verification error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        try {
            \Log::info('Profile endpoint called for user: ' . $request->user()->id);
            
            $user = $request->user()->load('activation');
            
            \Log::info('User retrieved successfully: ' . $user->email);
            \Log::info('User profile_image: ' . ($user->profile_image ?? 'null'));
            
            // Calculate activation information
            $activationInfo = null;
            if ($user->activation) {
                $activationInfo = [
                    'is_active' => $user->activation->isActive(),
                    'is_expired' => $user->activation->isExpired(),
                    'is_expiring_soon' => $user->activation->isExpiringSoon(7),
                    'days_remaining' => $user->activation->daysUntilExpiration(),
                    'approved_at' => $user->activation->approved_at,
                    'deactivate_at' => $user->activation->deactivate_at,
                ];
            }
            
            // Simple test response first
            $response = [
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'state' => $user->state,
                    'state_code' => $user->state_code,
                    'profile_image' => $user->profile_image,
                    'is_active' => $user->is_active,
                    'is_approved' => $user->is_approved,
                    'created_at' => $user->created_at,
                ],
                'loyalty_card' => null,
                'id_verification' => [
                    'is_verified' => !is_null($user->id_verified_at),
                    'verified_at' => $user->id_verified_at,
                    'extracted_data' => $user->id_verification_data,
                ],
                'activation' => $activationInfo,
            ];
            
            \Log::info('Profile response ready, returning JSON');
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('Profile endpoint error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email OTP
     */
    private function _sendEmailOtp($email, $userRole = null)
    {
        try {
            $otp = Otp::create([
                'identifier' => $email,
                'otp' => $this->generateOtp(),
                'type' => 'email',
                'expires_at' => now()->addMinutes(5),
            ]);

            // For debugging, log the OTP (remove this in production)
            \Log::info("OTP created for email: $email, OTP: {$otp->otp}");

          
            try {
                Mail::to($email)->send(new \App\Mail\OtpMail($otp->otp, $email, $userRole));
                \Log::info("OTP email sent successfully to: $email");
            } catch (\Exception $mailException) {
                \Log::error("Failed to send OTP email to: $email, Error: " . $mailException->getMessage());
                // Don't throw the exception, just log it for now
                // In production, you might want to handle this differently
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create OTP for email: $email, Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send phone OTP
     */
    private function _sendPhoneOtp($phone)
    {
        $otp = Otp::create([
            'identifier' => $phone,
            'otp' => $this->generateOtp(),
            'type' => 'phone',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send SMS with OTP
        // This would integrate with your SMS service
        // For now, we'll just create the OTP record
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send the store registration email with required documents after OTP verification
     */
    private function _sendStoreRegistrationEmail(\App\Models\User $user, \App\Models\Store $store)
    {
        try {
            \Mail::to($user->email)->send(new \App\Mail\StoreRegistrationMail($user, $store));
            \Log::info("Store registration email sent to: {$user->email}");
        } catch (\Exception $e) {
            \Log::error("Failed to send store registration email: " . $e->getMessage());
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
{
    $user = $request->user();

    // Normalize inputs
    if ($request->has('phone')) {
        $request->merge(['phone' => trim((string) $request->phone)]);
    }

    // Validate format only
    $validator = Validator::make($request->all(), [
        'name'          => 'sometimes|string|max:255',
        'phone'         => 'sometimes|string|max:20',
        'state'         => 'sometimes|string|max:100',
        'state_code'    => 'sometimes|string|max:10',
        'profile_image' => 'sometimes|string|max:1000',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422);
    }

    // ===== Manual uniqueness check for phone =====
    if ($request->has('phone')) {
        $normalizedPhone = $request->phone;

        $exists = User::where('id', '!=', $user->id)
            ->get()
            ->contains(fn($u) => $u->phone === $normalizedPhone); // $u->phone is auto-decrypted

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already exists',
                'errors'  => ['phone' => ['The phone number has already been taken.']]
            ], 409);
        }
    }

    // ===== Build update data =====
    $updateData = $request->only(['name', 'phone', 'state', 'state_code']);

    if ($request->has('profile_image') && $request->profile_image) {
        $updateData['profile_image'] = $request->profile_image;
    }

    // Apply update
    $user->update($updateData);

    return response()->json([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user'    => [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'role'          => $user->role,
            'state'         => $user->state,
            'state_code'    => $user->state_code,
            'profile_image' => $user->profile_image,
        ],
    ]);
}


    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        try {
            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            \Log::info("Password changed successfully for user: " . $user->email);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to change password: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit ID verification
     */
    public function submitIdVerification(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'id_image' => 'required|string', // Base64 encoded image
            'extracted_data' => 'required|array',
            'extracted_data.name' => 'required|string|max:255',
            'extracted_data.father_name' => 'nullable|string|max:255',
            'extracted_data.cnic' => 'nullable|string|max:20',
            'extracted_data.date_of_birth' => 'nullable|string|max:20',
            'extracted_data.date_of_issue' => 'nullable|string|max:20',
            'extracted_data.date_of_expiry' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update user with extracted data
            $user->update([
                'name' => $request->extracted_data['name'],
                'id_verification_data' => json_encode($request->extracted_data),
                'id_verified_at' => now(),
            ]);

            // Store ID image (you might want to save this to a file storage service)
            // For now, we'll store the base64 data in the database
            // In production, you should upload to cloud storage and store the URL
            
            return response()->json([
                'success' => true,
                'message' => 'ID verification submitted successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'state' => $user->state,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit ID verification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ID verification status
     */
    public function getIdVerificationStatus(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'is_verified' => !is_null($user->id_verified_at),
            'verified_at' => $user->id_verified_at,
            'extracted_data' => $user->id_verification_data ? json_decode($user->id_verification_data, true) : null,
        ]);
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $user = User::findByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        try {
            // Send OTP for password reset
            $this->_sendPasswordResetOtp($email, $user->role);
            
            return response()->json([
                'success' => true,
                'message' => 'Password reset OTP sent to your email successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send password reset OTP: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password with OTP
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp' => 'required|string|size:6',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $otp = $request->otp;
        $newPassword = $request->new_password;

        // Verify OTP - get all OTPs and filter manually to handle any potential encryption issues
        $otpRecords = Otp::where('type', 'password_reset')
            ->where('otp', $otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->get();

        $otpRecord = null;
        foreach ($otpRecords as $record) {
            if ($record->identifier === $email) {
                $otpRecord = $record;
                break;
            }
        }

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        // Update user password
        $user = User::findByEmail($email);
        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        // Mark OTP as used
        $otpRecord->update([
            'is_used' => true,
            'used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Send password reset OTP
     */
    private function _sendPasswordResetOtp($email, $userRole = null)
    {
        try {
            $otp = Otp::create([
                'identifier' => $email,
                'otp' => $this->generateOtp(),
                'type' => 'password_reset',
                'expires_at' => now()->addMinutes(10), // 10 minutes for password reset
            ]);

            // For debugging, log the OTP (remove this in production)
            \Log::info("Password reset OTP created for email: $email, OTP: {$otp->otp}");

            try {
                Mail::to($email)->send(new \App\Mail\PasswordResetMail($otp->otp, $email, $userRole));
                \Log::info("Password reset OTP email sent successfully to: $email");
            } catch (\Exception $mailException) {
                \Log::error("Failed to send password reset OTP email to: $email, Error: " . $mailException->getMessage());
                throw $mailException;
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create password reset OTP for email: $email, Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        \Log::info("User account deletion requested", [
            'user_id' => $user->id,
            'email' => $user->email,
            // Do not log the plaintext password for security reasons
            'reason' => $request->reason,
        ]);
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 400);
        }

        try {
            // Log the deletion reason for audit purposes
            \Log::info("User account deletion requested", [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $request->reason,
                'deleted_at' => now(),
            ]);

            // Revoke all tokens first
            $user->tokens()->delete();

            // Permanently delete the user from database
            $deleted = $user->delete();
            
            \Log::info("User deletion result: " . ($deleted ? 'success' : 'failed'));

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to delete account: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin information for a user based on their state/wilaya
     */
    private function _getAdminInfoForUser(\App\Models\User $user)
    {
        try {
            // Find admin for the user's state/wilaya
            $admin = \App\Models\Admin::where('wilaya_code', $user->state)->first();
            
            if ($admin) {
                $adminUser = \App\Models\User::find($admin->user_id);
                if ($adminUser) {
                    return [
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'phone' => $adminUser->phone,
                        'office_address' => $admin->office_address,
                        'office_location_lat' => $admin->office_location_lat,
                        'office_location_lng' => $admin->office_location_lng,
                    ];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to get admin info for user: ' . $e->getMessage());
            return null;
        }
    }
}
