<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ActivationController extends Controller
{
    /**
     * Get expired accounts for admin
     */
    public function getExpiredAccounts(Request $request)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Activation::with(['user'])
            ->expired();

        // Filter by wilaya for regular admins
        if ($admin->isAdmin()) {
            $query->whereHas('user', function ($q) use ($admin) {
                $q->where('state', $admin->state);
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        $expiredAccounts = $query->orderBy('deactivate_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $expiredAccounts
        ]);
    }

    /**
     * Get accounts expiring soon
     */
    public function getExpiringSoon(Request $request)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $days = $request->get('days', 7);
        $query = Activation::with(['user'])
            ->expiringSoon($days);

        // Filter by wilaya for regular admins
        if ($admin->isAdmin()) {
            $query->whereHas('user', function ($q) use ($admin) {
                $q->where('state', $admin->state);
            });
        }

        $expiringAccounts = $query->orderBy('deactivate_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $expiringAccounts
        ]);
    }

    /**
     * Reactivate expired account
     */
    public function reactivateAccount(Request $request, $userId)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::findOrFail($userId);
        
        // Check if admin can access this user (wilaya restriction)
        if ($admin->isAdmin() && $user->state !== $admin->state) {
            return response()->json([
                'success' => false,
                'message' => 'You can only manage users from your wilaya'
            ], 403);
        }

        $days = $request->get('days', 365);
        $user->reactivate($days);

        // Send reactivation email
        $this->sendReactivationEmail($user);

        return response()->json([
            'success' => true,
            'message' => 'Account reactivated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Extend account activation
     */
    public function extendActivation(Request $request, $userId)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::findOrFail($userId);
        
        // Check if admin can access this user (wilaya restriction)
        if ($admin->isAdmin() && $user->state !== $admin->state) {
            return response()->json([
                'success' => false,
                'message' => 'You can only manage users from your wilaya'
            ], 403);
        }

        $days = $request->get('days', 365);
        
        if ($user->activation) {
            $user->activation->extendActivation($days);
        } else {
            $user->activation()->create([
                'approved_at' => now(),
                'deactivate_at' => now()->addDays($days),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account activation extended successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Deactivate account immediately
     */
    public function deactivateAccount(Request $request, $userId)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $user = User::findOrFail($userId);
        
        // Check if admin can access this user (wilaya restriction)
        if ($admin->isAdmin() && $user->state !== $admin->state) {
            return response()->json([
                'success' => false,
                'message' => 'You can only manage users from your wilaya'
            ], 403);
        }

        $user->deactivate();

        // Send deactivation email
        $this->sendDeactivationEmail($user);

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Get activation statistics for admin dashboard
     */
    public function getActivationStats()
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Activation::query();

        // Filter by wilaya for regular admins
        if ($admin->isAdmin()) {
            $query->whereHas('user', function ($q) use ($admin) {
                $q->where('state', $admin->state);
            });
        }

        $stats = [
            'total_active' => (clone $query)->active()->count(),
            'total_expired' => (clone $query)->expired()->count(),
            'expiring_this_week' => (clone $query)->expiringSoon(7)->count(),
            'expiring_this_month' => (clone $query)->expiringSoon(30)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Send reactivation email
     */
    private function sendReactivationEmail($user)
    {
        try {
            Mail::send('emails.client-approved', ['user' => $user], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Your Hani Account Has Been Reactivated!');
            });
            
            Log::info("Reactivation email sent to user: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send reactivation email to user {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Send deactivation email
     */
    private function sendDeactivationEmail($user)
    {
        try {
            Mail::send('emails.account-expired', ['user' => $user], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Your Hani Account Has Expired');
            });
            
            Log::info("Deactivation email sent to user: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send deactivation email to user {$user->email}: " . $e->getMessage());
        }
    }
}
