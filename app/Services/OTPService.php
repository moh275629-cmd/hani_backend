<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OTPService
{
    /**
     * Generate and send OTP to user
     */
    public function generateAndSendOTP(User $user, string $type = 'email'): array
    {
        // Delete any existing OTPs for this user and type
        Otp::where('user_id', $user->id)
            ->where('type', $type)
            ->delete();

        // Generate new OTP
        $otpCode = $this->generateOTPCode();
        $expiresAt = now()->addMinutes(10);

        $otp = Otp::create([
            'user_id' => $user->id,
            'type' => $type,
            'code' => $otpCode,
            'expires_at' => $expiresAt,
            'is_used' => false,
        ]);

        // Send OTP
        $sent = $this->sendOTP($user, $otpCode, $type);

        if (!$sent) {
            $otp->delete();
            throw new \Exception('Failed to send OTP');
        }

        return [
            'otp_id' => $otp->id,
            'expires_at' => $expiresAt,
            'type' => $type,
        ];
    }

    /**
     * Generate a 6-digit OTP code
     */
    private function generateOTPCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP via email or SMS
     */
    private function sendOTP(User $user, string $otpCode, string $type): bool
    {
        try {
            if ($type === 'email') {
                return $this->sendEmailOTP($user, $otpCode);
            } elseif ($type === 'phone') {
                return $this->sendSMSOTP($user, $otpCode);
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send OTP via email
     */
    private function sendEmailOTP(User $user, string $otpCode): bool
    {
        try {
            Mail::send('emails.otp', [
                'user' => $user,
                'otp_code' => $otpCode,
                'expires_in' => '10 minutes',
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your OTP Code - Hani Loyalty App');
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email OTP', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send OTP via SMS (placeholder for future implementation)
     */
    private function sendSMSOTP(User $user, string $otpCode): bool
    {
        // This would integrate with SMS service providers like Twilio, Vonage, etc.
        Log::info("SMS OTP sent to user {$user->id}: {$otpCode}");
        
        // For now, return true as placeholder
        return true;
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP(int $userId, string $otpCode, string $type = 'email'): bool
    {
        $otp = Otp::where('user_id', $userId)
            ->where('type', $type)
            ->where('code', $otpCode)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return false;
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        return true;
    }

    /**
     * Resend OTP if expired
     */
    public function resendOTP(User $user, string $type = 'email'): array
    {
        // Check if there's a recent OTP request (rate limiting)
        $recentOTP = Otp::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>', now()->subMinutes(1))
            ->first();

        if ($recentOTP) {
            throw new \Exception('Please wait before requesting another OTP');
        }

        return $this->generateAndSendOTP($user, $type);
    }

    /**
     * Check if OTP is valid without marking it as used
     */
    public function checkOTP(int $userId, string $otpCode, string $type = 'email'): bool
    {
        $otp = Otp::where('user_id', $userId)
            ->where('type', $type)
            ->where('code', $otpCode)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        return $otp !== null;
    }

    /**
     * Get remaining attempts for OTP verification
     */
    public function getRemainingAttempts(int $userId, string $type = 'email'): int
    {
        $maxAttempts = 5;
        $usedAttempts = Otp::where('user_id', $userId)
            ->where('type', $type)
            ->where('created_at', '>', now()->subHours(1))
            ->count();

        return max(0, $maxAttempts - $usedAttempts);
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpiredOTPs(): int
    {
        return Otp::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(1))
            ->delete();
    }

    /**
     * Generate OTP for password reset
     */
    public function generatePasswordResetOTP(User $user): array
    {
        return $this->generateAndSendOTP($user, 'password_reset');
    }

    /**
     * Generate OTP for phone verification
     */
    public function generatePhoneVerificationOTP(User $user): array
    {
        return $this->generateAndSendOTP($user, 'phone_verification');
    }

    /**
     * Generate OTP for email verification
     */
    public function generateEmailVerificationOTP(User $user): array
    {
        return $this->generateAndSendOTP($user, 'email_verification');
    }

    /**
     * Validate OTP format
     */
    public function validateOTPFormat(string $otpCode): bool
    {
        return preg_match('/^\d{6}$/', $otpCode) === 1;
    }

    /**
     * Get OTP expiration time
     */
    public function getOTPExpirationTime(int $otpId): ?string
    {
        $otp = Otp::find($otpId);
        return $otp ? $otp->expires_at : null;
    }
}
