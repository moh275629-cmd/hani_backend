<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = AppNotification::where('user_id', $user->id);

        // Filter by read status if provided
        if ($request->has('read')) {
            $query->where('is_read', $request->boolean('read'));
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications
        ]);
    }

    /**
     * Display the specified notification
     */
    public function show(AppNotification $notification): JsonResponse
    {
        $user = Auth::user();
        
        // Ensure user can only view their own notifications
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access',
                'data' => null
            ], 403);
        }

        // Mark as read if not already read
        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'message' => 'Notification retrieved successfully',
            'data' => $notification
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(AppNotification $notification): JsonResponse
    {
        $user = Auth::user();
        
        // Ensure user can only mark their own notifications as read
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access',
                'data' => null
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        
        $updatedCount = AppNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => "Marked {$updatedCount} notifications as read",
            'data' => [
                'updated_count' => $updatedCount
            ]
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();
        
        $count = AppNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Unread count retrieved successfully',
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(AppNotification $notification): JsonResponse
    {
        $user = Auth::user();
        
        // Ensure user can only delete their own notifications
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access',
                'data' => null
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get notification statistics
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        $stats = AppNotification::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                COUNT(CASE WHEN type = "offer" THEN 1 END) as offer_notifications,
                COUNT(CASE WHEN type = "points" THEN 1 END) as points_notifications,
                COUNT(CASE WHEN type = "system" THEN 1 END) as system_notifications,
                MAX(created_at) as last_notification
            ')
            ->first();

        // Get notifications by type
        $byType = AppNotification::where('user_id', $user->id)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        return response()->json([
            'message' => 'Notification statistics retrieved successfully',
            'data' => [
                'overview' => $stats,
                'by_type' => $byType,
            ]
        ]);
    }
}
