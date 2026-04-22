<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class YetenekDogrula
{
    public function handle(Request $request, Closure $next, string ...$yetenekler): Response
    {
        foreach ($yetenekler as $yetenek) {
            if (!$request->user()?->tokenCan($yetenek)) {
                abort(403, "Bu işlem için '{$yetenek}' yetkisi gerekli.");
            }
        }

        return $next($request);
    }
}
