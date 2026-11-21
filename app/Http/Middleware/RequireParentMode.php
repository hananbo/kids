<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireParentMode
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('child_mode')) {
            return $next($request);
        }

        if ($request->routeIs('quiz.play.*', 'child-mode.exit', 'password.confirm', 'password.confirm.store', 'passkey.confirm', 'passkey.confirm-options', 'logout')) {
            return $next($request);
        }

        return redirect()->route('child-mode.exit');
    }
}
