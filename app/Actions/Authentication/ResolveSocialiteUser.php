<?php

namespace App\Actions\Authentication;

use App\Actions\Teams\CreateTeam;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveSocialiteUser
{
    public function __construct(private CreateTeam $createTeam) {}

    /**
     * Resolve a local user from a trusted OAuth provider identity.
     */
    public function __invoke(string $provider, SocialiteUser $socialiteUser): User
    {
        $providerUserId = (string) $socialiteUser->getId();
        $email = $socialiteUser->getEmail();

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'email' => 'Your GitHub account must have a verified primary email address.',
            ]);
        }

        return DB::transaction(function () use ($provider, $providerUserId, $email, $socialiteUser): User {
            $socialAccount = SocialAccount::query()
                ->with('user')
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($socialAccount) {
                return $socialAccount->user;
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $name = $socialiteUser->getName()
                    ?: $socialiteUser->getNickname()
                    ?: Str::before($email, '@');

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::password(64),
                ]);

                $user->markEmailAsVerified();
                ($this->createTeam)($user, $user->name."'s Team", isPersonal: true);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);

            return $user;
        });
    }
}
