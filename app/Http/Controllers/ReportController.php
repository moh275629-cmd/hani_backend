<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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

        // Check if user already reported this user
        $existingReport = Report::where('reporter_id', Auth::id())
            ->where('reported_user_id', $request->reported_user_id)
            ->whereIn('status', ['pending', 'under_review'])
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this user'
            ], 400);
        }

        $report = Report::create([
            'reporter_id' => Auth::id(),
            'reported_user_id' => $request->reported_user_id,
            'description' => $request->description,
            'status' => 'pending'
        ]);

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
        
        if (!$admin->isAdmin()) {
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

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

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

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

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
            'status' => 'required|in:under_review,resolved,dismissed',
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
        if ($admin->isAdmin() && $report->reportedUser->state !== $admin->state) {
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
        switch ($action) {
            case 'close_account':
                $reportedUser->deactivate();
                break;
            case 'warning':
                // Send warning email
                $this->sendWarningEmail($reportedUser, $request->notes);
                break;
            case 'let_go':
                // No action needed, just resolve the report
                break;
        }

        // Send email notification to reported user
        $this->sendReportActionEmail($reportedUser, $action, $request->notes);

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully',
            'data' => $report
        ]);
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
            Mail::send('emails.report-action', [
                'user' => $user, 
                'action' => 'warning', 
                'notes' => $notes
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Warning: Report Action Taken - Hani');
            });
            
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
            Mail::send('emails.report-action', [
                'user' => $user, 
                'action' => $action, 
                'notes' => $notes
            ], function ($message) use ($user, $action) {
                $subject = match($action) {
                    'close_account' => 'Account Deactivated Due to Report - Hani',
                    'warning' => 'Warning: Report Action Taken - Hani',
                    'let_go' => 'Report Resolved - No Action Taken - Hani',
                    default => 'Report Action Update - Hani'
                };
                
                $message->to($user->email, $user->name)
                        ->subject($subject);
            });
            
            Log::info("Report action email sent to user: {$user->email} for action: {$action}");
        } catch (\Exception $e) {
            Log::error("Failed to send report action email to user {$user->email}: " . $e->getMessage());
        }
    }
}
