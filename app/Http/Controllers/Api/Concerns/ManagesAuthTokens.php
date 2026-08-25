<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Domain\User\Models\User;
use Illuminate\Http\Request;

/**
 * Logique partagée entre AuthController (login classique) et
 * TwoFactorController (login::challenge, étape 9 §2) : émission de token
 * avec métadonnées de session, et verrouillage après tentatives échouées.
 */
trait ManagesAuthTokens
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    private function issueToken(User $user, Request $request): string
    {
        $token = $user->createToken('api');

        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        return $token->plainTextToken;
    }

    private function registerFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= self::MAX_ATTEMPTS
                ? now()->addMinutes(self::LOCK_MINUTES)
                : $user->locked_until,
        ])->save();

        if ($attempts >= self::MAX_ATTEMPTS) {
            $user->forceFill(['failed_login_attempts' => 0])->save();
        }
    }
}
