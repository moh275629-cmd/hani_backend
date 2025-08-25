<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Store;
use App\Models\Otp;
use App\Models\TermsAndConditions;
use App\Models\RequiredDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Mail\StoreRegistrationMail;

class StoreRegistrationController extends Controller
{
    /**
     * Register a new store
     */
    public function register(Request $request)
    {
        // Debug: Log the validation rules being used
        \Log::info('StoreRegistrationController::register called', [
            'request_data' => $request->all(),
            'controller_class' => get_class($this),
            'file_path' => __FILE__,
            'line' => __LINE__
        ]);

        // Minimal validation - only required fields
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string',
            'email'           => 'required|email',
            'phone'           => 'required|string',
            'password'        => 'required|confirmed',
            'state'           => 'required|string',
            'postal_code'     => 'nullable|string', // or nullable|string (choose one)
            'store_name'      => 'required|string',
            'description'     => 'required|string',
            'business_type'   => 'required|string',
            'address'         => 'required|string',
            'city'            => 'required|string',
            'country'         => 'nullable|string',
            'business_hours'  => 'nullable|array',
            'payment_methods' => 'required|array',
            'services'        => 'nullable|array',
            'google_place_id' => 'nullable|string',
            'latitude'        => 'nullable|string',
            'longitude'       => 'nullable|string',
            'website'         => 'nullable|string',
            'accept_terms'    => 'required|accepted',
        ]);
        

        if ($validator->fails()) {
            \Log::error('Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed ' . $validator->errors(),
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

        // Custom validation for business hours time format
        if (isset($request->business_hours) && is_array($request->business_hours)) {
            \Log::info('Validating business hours', [
                'business_hours' => $request->business_hours,
                'count' => count($request->business_hours)
            ]);
            
            foreach ($request->business_hours as $index => $hours) {
                \Log::info("Validating hours for day: {$hours['day']}", [
                    'index' => $index,
                    'hours' => $hours,
                    'is_closed' => $hours['is_closed'] ?? 'not set'
                ]);
                
                if (!$hours['is_closed']) {
                    // Validate time format for open hours
                    if (!empty($hours['open']) && !$this->isValidTimeFormat($hours['open'])) {
                        \Log::error('Invalid open time format', [
                            'day' => $hours['day'],
                            'open' => $hours['open'],
                            'index' => $index
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid time format for opening hours on ' . $hours['day'],
                            'errors' => [
                                'business_hours.' . $index . '.open' => ['Invalid time format. Use HH:MM format (e.g., 09:00)']
                            ]
                        ], 422);
                    }
                    
                    // Validate time format for close hours
                    if (!empty($hours['close']) && !$this->isValidTimeFormat($hours['close'])) {
                        \Log::error('Invalid close time format', [
                            'day' => $hours['day'],
                            'close' => $hours['close'],
                            'index' => $index
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid time format for closing hours on ' . $hours['day'],
                            'errors' => [
                                'business_hours.' . $index . '.close' => ['Invalid time format. Use HH:MM format (e.g., 17:00)']
                            ]
                        ], 422);
                    }
                }
            }
        }

        try {
            // Log the incoming request data for debugging
            \Log::info('Store registration attempt', [
                'email' => $request->email,
                'store_name' => $request->store_name,
                'state' => $request->state,
                'city' => $request->city,
                'business_hours_count' => count($request->business_hours),
                'payment_methods_count' => count($request->payment_methods),
            ]);

            // Create user (inactive until approved)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'store',
                'state' => $request->state,
                'postal_code' => $request->postal_code,
                'is_active' => false, // Store users start as inactive
                'id_verified' => false,
            ]);

            \Log::info('User created successfully', ['user_id' => $user->id]);

            // Create store
            $store = Store::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'description' => $request->description,
                'business_type' => $request->business_type,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
                'postal_code' => $request->postal_code ?: null,
                'country' => $request->country ?: null,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'business_hours' => $request->business_hours,
                'payment_methods' => $request->payment_methods,
                'services' => $request->services,
                'is_approved' => false,
                'is_active' => false,
            ]);

            \Log::info('Store created successfully', ['store_id' => $store->id]);

            // Send OTP email
            $this->_sendEmailOtp($user->email, 'store');

            // Send store registration email with required documents
          

            \Log::info('Store registration completed successfully', [
                'user_id' => $user->id,
                'store_id' => $store->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store registered successfully. Please verify your email and wait for admin approval.',
                'user' => $user->only(['id', 'name', 'email', 'phone', 'role', 'state', 'postal_code']),
                'store' => $store->only(['id', 'store_name', 'business_type', 'city', 'state']),
                'requires_approval' => true,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Store registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register store: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terms and conditions
     */
    public function getTermsAndConditions()
    {
        $terms = TermsAndConditions::getCurrentActive();
        
        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'No active terms and conditions found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $terms->version,
                'content' => [
                    'en' => $terms->content_en,
                    'fr' => $terms->content_fr,
                    'ar' => $terms->content_ar,
                ],
                'published_at' => $terms->published_at,
            ]
        ]);
    }

    /**
     * Get required documents for store registration
     */
    public function getRequiredDocuments()
    {
        $documents = RequiredDocuments::getForUserCategory('store');
        
        return response()->json([
            'success' => true,
            'data' => $documents
        ]);
    }

    /**
     * Send OTP email
     */
    private function _sendEmailOtp($email, $userRole = null)
    {
        try {
            $otp = Otp::create([
                'identifier' => $email,
                'otp' => $this->generateOtp(),
                'type' => 'email',
                'expires_at' => now()->addMinutes(10),
            ]);

            \Log::info("OTP created for store email: $email, OTP: {$otp->otp}");

            try {
                Mail::to($email)->send(new \App\Mail\OtpMail($otp->otp, $email, $userRole));
                \Log::info("OTP email sent successfully to: $email");
            } catch (\Exception $mailException) {
                \Log::error("Failed to send OTP email to: $email, Error: " . $mailException->getMessage());
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create OTP for email: $email, Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send store registration email with required documents
     */
    private function _sendStoreRegistrationEmail($user, $store)
    {
        try {
            Mail::to($user->email)->send(new StoreRegistrationMail($user, $store));
            \Log::info("Store registration email sent successfully to: {$user->email}");
        } catch (\Exception $e) {
            \Log::error("Failed to send store registration email to: {$user->email}, Error: " . $e->getMessage());
        }
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate time format (HH:MM)
     */
    private function isValidTimeFormat($time)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }
}
