<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Create a new report
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reported_user_id' => 'required|exists:users,id',
            'description' => 'required|string|min:10|max:1000',
            'context' => 'nullable|string|max:50',
            'context_id' => 'nullable|integer',
            'is_auto_generated' => 'sometimes|boolean',
            'profanity_score' => 'nullable|numeric|min:0|max:100',
            'detected_words' => 'nullable|array',
            'original_text_hash' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is trying to report themselves
        if (Auth::id() == $request->reported_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report yourself'
            ], 400);
        }

       

        // Prevent duplicate auto-report (last hour by text hash)
        if ($request->boolean('is_auto_generated') && $request->filled('original_text_hash')) {
            $dup = Report::where('is_auto_generated', true)
                ->where('reported_user_id', $request->reported_user_id)
                ->where('original_text_hash', $request->original_text_hash)
                ->where('created_at', '>=', now()->subHour())
                ->first();
            if ($dup) {
                return response()->json([
                    'success' => true,
                    'message' => 'Duplicate auto-report ignored',
                    'data' => $dup
                ], 200);
            }
        }
 if ($request->boolean('is_auto_generated') && $request->filled('original_text_hash')) {
            $report = Report::create([
            'reporter_id' => null,
            'reported_user_id' => Auth::id(),
            'description' => $request->description,
            'status' => 'pending',
            'is_auto_generated' => (bool)$request->input('is_auto_generated', false),
            'profanity_score' => $request->input('profanity_score'),
            'detected_words' => $request->input('detected_words'),
            'context' => $request->input('context'),
            'context_id' => $request->input('context_id'),
            'original_text_hash' => $request->input('original_text_hash'),
        ]);
        }else{
             $report = Report::create([
            'reporter_id' => Auth::id(),
            'reported_user_id' => $request->reported_user_id,
            'description' => $request->description,
            'status' => 'pending',
            'is_auto_generated' => (bool)$request->input('is_auto_generated', false),
            'profanity_score' => $request->input('profanity_score'),
            'detected_words' => $request->input('detected_words'),
            'context' => $request->input('context'),
            'context_id' => $request->input('context_id'),
            'original_text_hash' => $request->input('original_text_hash'),
        ]);
        }
       

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully',
            'data' => $report
        ], 201);
    }

    /**
     * Get reports for admin (filtered by wilaya)
     */
    public function getReportsForAdmin(Request $request)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Report::with(['reporter', 'reportedUser'])
            ->whereHas('reportedUser', function ($q) use ($admin) {
                $q->where('state', $admin->state);
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Get all reports for global admin
     */
    public function getAllReports(Request $request)
    {
        $admin = Auth::user();
        
        if (!$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Report::with(['reporter', 'reportedUser']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by wilaya
        if ($request->has('wilaya')) {
            $query->whereHas('reportedUser', function ($q) use ($request) {
                $q->where('state', $request->wilaya);
            });
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Update report status
     */
    public function updateStatus(Request $request, $id)
    {
        $admin = Auth::user();
        
        if (!$admin->isAdmin() && !$admin->isGlobalAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:warning,close_account,dismissed',
            'action' => 'required|in:let_go,close_account,warning',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $report = Report::findOrFail($id);
        
        // Check if admin can access this report (wilaya restriction)
        if ($admin->isAdmin() && !$admin->isGlobalAdmin() && $report->reportedUser->state !== $admin->state) {
            return response()->json([
                'success' => false,
                'message' => 'You can only manage reports from your wilaya'
            ], 403);
        }

        $report->status = $request->status;
        $report->save();

        $reportedUser = $report->reportedUser;
        $action = $request->action;

        // Take action based on admin decision
        try {
            switch ($action) {
                case 'close_account':
                    $reportedUser->delete();
                    // Send email notification for account closure
                    $this->sendReportActionEmail($reportedUser, $action, $request->notes);
                    break;
                case 'warning':
                    // Send warning email
                    $this->sendWarningEmail($reportedUser, $request->notes);
                    break;
                case 'let_go':
                    // No action needed, no email sent for dismiss
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Error taking action on report {$id}: " . $e->getMessage());
            // Continue with the response even if action fails
        }

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully',
            'data' => $report
        ]);
    }

    public function warnUser(Request $request, int $reportId)
    {
        $admin = Auth::user();
        if (!$admin || (!$admin->isAdmin() && !$admin->isGlobalAdmin())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $report = Report::with('reportedUser')->findOrFail($reportId);
        $user = $report->reportedUser;
        $user->warning_count = ($user->warning_count ?? 0) + 1;
        $user->save();

        if ($user->warning_count >= 10) {
            $user->status = 'suspended';
            $user->save();
        }
        if ($user->warning_count >= 5) {
            \DB::table('blacklist_emails')->updateOrInsert(
                ['email' => $user->email],
                ['reason' => 'Reached 5 warnings', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->sendWarningEmail($user, $request->input('notes'));

        return response()->json([
            'success' => true,
            'message' => 'User warned',
            'warning_count' => $user->warning_count,
            'blacklisted' => $user->warning_count >= 5,
        ]);
    }

    public function deleteUser(Request $request, int $reportId)
    {
        $admin = Auth::user();
        if (!$admin || (!$admin->isAdmin() && !$admin->isGlobalAdmin())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $report = Report::with('reportedUser')->findOrFail($reportId);
        $user = $report->reportedUser;
        $user->status = 'suspended';
        $user->save();

        \DB::table('blacklist_emails')->updateOrInsert(
            ['email' => $user->email],
            ['reason' => 'Account deleted by admin due to reports', 'updated_at' => now(), 'created_at' => now()]
        );

        $this->sendReportActionEmail($user, 'close_account', $request->input('notes'));

        return response()->json(['success' => true, 'message' => 'User suspended and blacklisted']);
    }

    /**
     * Get user's own reports
     */
    public function getUserReports()
    {
        $reports = Report::where('reporter_id', Auth::id())
            ->with('reportedUser')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Send warning email to user
     */
    private function sendWarningEmail($user, $notes)
    {
        try {
            $leftBeforeSuspend = max(0, 10 - (int)($user->warning_count ?? 0));
            $leftBeforeBlacklist = max(0, 5 - (int)($user->warning_count ?? 0));
            Mail::raw(
                "Warning issued for policy violation.\n" .
                "Warnings so far: {$user->warning_count}\n" .
                "Warnings before suspension: {$leftBeforeSuspend}\n" .
                "Warnings before blacklist: {$leftBeforeBlacklist}\n" .
                "Notes: {$notes}",
                function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                        ->subject('Warning: Policy Violation - Hani');
                }
            );
            Log::info("Warning email sent to user: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send warning email to user {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Send report action email to user
     */
    private function sendReportActionEmail($user, $action, $notes)
    {
        try {
            $subject = match($action) {
                'close_account' => 'Account Deactivated Due to Violations - Hani',
                'warning' => 'Warning: Report Action Taken - Hani',
                'let_go' => 'Report Resolved - No Action Taken - Hani',
                default => 'Report Action Update - Hani'
            };
            Mail::raw("Action: {$action}\nNotes: {$notes}", function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)->subject($subject);
            });
            Log::info("Report action email sent to user: {$user->email} for action: {$action}");
        } catch (\Exception $e) {
            Log::error("Failed to send report action email to user {$user->email}: " . $e->getMessage());
        }
    }
}
