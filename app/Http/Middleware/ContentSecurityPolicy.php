<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app()->isLocal()) {
            $viteHttp = 'http://localhost:5173 http://127.0.0.1:5173';
            $viteWs   = 'ws://localhost:5173 ws://127.0.0.1:5173';
            $scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval' blob: {$viteHttp}";
            $policy = implode('; ', [
                "script-src {$scriptSrc}",
                "script-src-elem {$scriptSrc}",
                "connect-src 'self' ws: wss: {$viteHttp} {$viteWs}",
                "worker-src blob:",
            ]);
        } else {
            $policy = implode('; ', [
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:",
                "connect-src 'self' ws: wss:",
                "worker-src blob:",
            ]);
        }

        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
