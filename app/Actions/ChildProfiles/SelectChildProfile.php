<?php

namespace App\Actions\ChildProfiles;

use App\Models\ChildProfile;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Gate;

class SelectChildProfile
{
    public function __invoke(ChildProfile $profile, Session $session): void
    {
        Gate::authorize('update', $profile->team);

        $session->forget('auth.password_confirmed_at');
        $session->put('child_mode', ['profile_id' => $profile->id, 'team_id' => $profile->team_id]);
        $session->regenerate(true);
    }
}
