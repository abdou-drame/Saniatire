<?php

namespace Tests;

use App\Domain\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * actingAs() authenticates a request directly (Auth::setUser()),
     * bypassing the real login endpoint entirely. The mandatory-2FA gate
     * (EnsureTwoFactorSetupComplete) is a login-flow concern, and is
     * exercised deliberately through a genuine HTTP login by
     * Step9TwoFactorAuthTest. Every other fixture that just needs "a user
     * with role X" would otherwise 423 the moment that role is added to
     * User::ROLES_REQUIRING_TWO_FACTOR, for a reason unrelated to what it's
     * testing — so the precondition is satisfied here once, instead of in
     * every unrelated test.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            $user->generateTwoFactorSecret();
            $user->confirmTwoFactor();
        }

        return parent::actingAs($user, $guard);
    }
}
