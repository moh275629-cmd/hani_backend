<?php

namespace App\Http\Middleware;

use App\Models\Report;
use App\Services\ProfanityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfanityCheck
{
    public function __construct(private ProfanityService $profanity) {}

    public function handle(Request $request, Closure $next)
    {
        $fieldsToCheck = ['comment', 'review', 'description', 'message'];
        $text = null;
        $context = null;

        foreach ($fieldsToCheck as $field) {
            if ($request->filled($field)) {
                $text = (string)$request->input($field);
                $context = $field;
                break;
            }
        }

        if (!$text) {
            return $next($request);
        }

        $result = $this->profanity->validate($text);

        if ($result['contains']) {
            try {
                $reportedUserId = $request->input('reported_user_id') ?? $request->input('user_id');

                $report = Report::create([
                    'reporter_id' => Auth::id(),
                    'reported_user_id' => $reportedUserId,
                    'description' => 'Automatic profanity detection',
                    'status' => 'pending',
                    'is_auto_generated' => true,
                    'profanity_score' => $result['score'],
                    'detected_words' => $result['detected_words'],
                    'context' => $context,
                    'context_id' => $request->input('context_id'),
                    'original_text_hash' => hash('sha256', $text),
                ]);

                // Increment warning and apply policy
                if ($reportedUserId) {
                    $this->applyPolicy((int)$reportedUserId);
                }

                return response()->json([
                    'success' => false,
                    'blocked' => true,
                    'report_created' => true,
                    'detected_words' => $result['detected_words'],
                    'message' => 'Content blocked due to inappropriate language',
                    'report' => $report,
                ], 422);
            } catch (\Throwable $e) {
                Log::error('ProfanityCheck middleware error: ' . $e->getMessage());
                return $next($request);
            }
        }

        return $next($request);
    }

    private function applyPolicy(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update([
            'warning_count' => DB::raw('COALESCE(warning_count, 0) + 1')
        ]);

        $user = DB::table('users')->find($userId);
        if (!$user) return;

        if ((int)$user->warning_count >= 10) {
            DB::table('users')->where('id', $userId)->update(['status' => 'suspended']);
        }

        if ((int)$user->warning_count >= 5) {
            DB::table('blacklist_emails')->updateOrInsert(
                ['email' => $user->email],
                ['reason' => 'Reached 5 warnings', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}


