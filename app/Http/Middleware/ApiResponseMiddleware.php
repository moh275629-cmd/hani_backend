<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add CORS headers
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return response('', 200);
        }

        // Add debug headers for DB connection (useful in dev to verify where writes go)
        try {
            $response->headers->set('X-DB-Connection', config('database.default'));
            $response->headers->set('X-DB-Database', (string) DB::connection()->getDatabaseName());
        } catch (\Throwable $e) {
            // Ignore header setting errors
        }

        return $response;
    }
}
