<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CanonicalizeEcommerceUrl
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = $request->getPathInfo(); // e.g. "/ecommerce/".

        // En ecommerce, forzamos URL sin slash final (excepto la raiz "/").
        if (str_starts_with($path, '/ecommerce') && $path !== '/' && str_ends_with($path, '/')) {
            $normalizedPath = rtrim($path, '/');
            $query = $request->getQueryString();
            $target = $request->getSchemeAndHttpHost() . $normalizedPath . ($query ? '?' . $query : '');

            return redirect()->to($target, 301);
        }

        return $next($request);
    }
}

