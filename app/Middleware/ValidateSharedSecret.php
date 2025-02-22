<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateSharedSecret
{
    public function handle(Request $request, Closure $next)
    {
        $sharedSecret = config('services.fastapi.shared_secret');
        $requestSecret = $request->header('X-Shared-Secret');

        if (!$requestSecret || $requestSecret !== $sharedSecret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid shared secret'
            ], 401);
        }

        return $next($request);
    }
}