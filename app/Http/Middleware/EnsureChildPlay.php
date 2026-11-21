<?php

namespace App\Http\Middleware;

use App\Models\ChildProfile;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;
use Symfony\Component\HttpFoundation\Response;

class EnsureChildPlay
{
    public static function marks(Route $route): bool
    {
        return in_array(self::class, Router::gatherRouteMiddleware($route), true);
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->route('current_team');
        $profileId = $request->session()->get('child_mode.profile_id');

        abort_unless($team instanceof Team
            && is_int($profileId)
            && $request->session()->get('child_mode.team_id') === $team->id, 403);
        Gate::authorize('update', $team);

        $profile = ChildProfile::query()->whereBelongsTo($team)->findOrFail($profileId);
        $request->attributes->set(ChildProfile::class, $profile);

        return $next($request);
    }

    public static function profile(Request $request): ChildProfile
    {
        $profile = $request->attributes->get(ChildProfile::class);
        abort_unless($profile instanceof ChildProfile, 403);

        return $profile;
    }
}
