<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Tolak request dengan 403 jika user login bukan admin (users.role).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (optional($request->user())->role !== 'admin') {
            return response()->json([
                'message' => 'Hanya admin yang boleh melakukan aksi ini'
            ], 403);
        }

        return $next($request);
    }
}
