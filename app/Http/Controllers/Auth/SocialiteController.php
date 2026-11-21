<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Authentication\ResolveSocialiteUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SocialiteController extends Controller
{
    public function __construct(private SocialiteFactory $socialite) {}

    /**
     * Redirect the user to GitHub for authentication.
     */
    public function redirect(): RedirectResponse
    {
        return $this->socialite->driver('github')->redirect();
    }

    /**
     * Authenticate the user after GitHub redirects back.
     */
    public function callback(Request $request, ResolveSocialiteUser $resolveSocialiteUser): RedirectResponse
    {
        if ($request->string('error')->isNotEmpty()) {
            return redirect()->route('login')->withErrors([
                'socialite' => 'GitHub authentication was cancelled.',
            ]);
        }

        $user = $resolveSocialiteUser('github', $this->socialite->driver('github')->user());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', [
            'current_team' => $user->currentTeam,
        ]));
    }
}
