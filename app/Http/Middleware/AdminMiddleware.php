<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized - Authentication required',
                'error' => 'No authenticated user found'
            ], 401);
        }

        if (!$user->isAdmin() && !$user->isGlobalAdmin()) {
            return response()->json([
                'message' => 'Forbidden - Admin access required',
                'error' => 'User does not have admin privileges'
            ], 403);
        }

        return $next($request);
    }
}
