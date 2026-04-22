<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminYetkisi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('admin.giris')
                ->with('uyari', 'Bu sayfaya erişmek için giriş yapmalısınız.');
        }

        if (!$request->user()->is_admin) {
            return redirect()->route('admin.giris')
                ->with('uyari', 'Bu sayfaya erişim yetkiniz bulunmuyor.');
        }

        return $next($request);
    }
}
