<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        // Vite serves development modules and imported styles from a separate loopback origin.
        $devOrigins = '';
        if (app()->isLocal() && preg_match('~^(https?)://(?:localhost|127\.0\.0\.1|\[::1\]):([0-9]{1,5})/?$~D', Vite::devServerUrl() ?? '', $match)) {
            $devOrigins = " {$match[1]}://127.0.0.1:{$match[2]} {$match[1]}://localhost:{$match[2]}";
        }
        $hotFile = Vite::hotFile();
        $useBuild = $devOrigins !== '' && ! in_array($request->ip(), ['127.0.0.1', '::1'], true)
            && is_file(public_path('build/manifest.json'));

        // A phone on the LAN cannot reach the developer computer through its own loopback address.
        if ($useBuild) {
            Vite::useHotFile(storage_path('framework/vite-build-only'));
            $devOrigins = '';
        }
        try {
            $response = $next($request);
        } finally {
            if ($useBuild) {
                Vite::useHotFile($hotFile);
            }
        }

        $nonce = Vite::cspNonce();
        $scriptPolicy = "script-src 'self' 'nonce-{$nonce}'";

        if ($request->isMethod('GET') && $request->routeIs('filament.parent.*', 'filament.admin.*')
            && str_starts_with($response->headers->get('Content-Type', ''), 'text/html')) {
            // shortcut: the installed Filament Alpine expressions require evaluation, remove when Filament supports the CSP-safe evaluator.
            $scriptPolicy .= " 'unsafe-eval'";
        }

        $policy = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:{$devOrigins}",
            $scriptPolicy.$devOrigins,
            "style-src 'self' 'unsafe-inline'{$devOrigins}",
            app()->isLocal() ? "connect-src 'self' ws: wss:{$devOrigins}" : "connect-src 'self'",
        ]);

        $response->headers->set(
            config('security.http.csp_report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy',
            $policy,
        );
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->remove('X-Powered-By');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.(int) config('security.http.hsts_max_age').'; includeSubDomains',
            );
        }

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
