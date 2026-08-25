<?php

namespace App\Http\Controllers\Api;

use App\Domain\User\Models\User;
use App\Http\Controllers\Api\Concerns\ManagesAuthTokens;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Étape 9 §2 : enrôlement, confirmation, désactivation et vérification de
 * la 2FA (TOTP). setup()/confirm()/disable() exigent un token Sanctum
 * (l'utilisateur peut déjà avoir un token restreint émis par
 * AuthController::login() — voir EnsureTwoFactorSetupComplete). challenge()
 * est la seule route de ce contrôleur accessible sans token : elle échange
 * un challenge temporaire contre le vrai token API.
 */
class TwoFactorController extends Controller
{
    use ManagesAuthTokens;

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'La 2FA est déjà activée pour ce compte.'], 422);
        }

        $secret = $user->generateTwoFactorSecret();

        $qrCodeUrl = app(Google2FA::class)->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->verifyTwoFactorCode($data['code'])) {
            return response()->json(['message' => 'Code invalide.'], 422);
        }

        $recoveryCodes = $user->confirmTwoFactor();

        activity('2fa')->causedBy($user)->log('Authentification à deux facteurs activée.');

        return response()->json([
            'message' => '2FA activée.',
            // Codes en clair : affichés une seule fois, jamais restitués
            // ensuite (le cast encrypted:array ne les rend pas non plus
            // accessibles en clair via l'API après coup).
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Mot de passe incorrect.'], 422);
        }

        if ($user->requiresTwoFactor()) {
            return response()->json([
                'message' => 'La 2FA est obligatoire pour ce rôle et ne peut pas être désactivée.',
            ], 422);
        }

        $user->disableTwoFactor();

        activity('2fa')->causedBy($user)->log('Authentification à deux facteurs désactivée.');

        return response()->json(['message' => '2FA désactivée.']);
    }

    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $cacheKey = "2fa_challenge:{$data['challenge']}";
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            return response()->json(['message' => 'Challenge invalide ou expiré.'], 422);
        }

        $user = User::withoutGlobalScopes()->find($userId);

        if (! $user || ! $user->is_active) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Challenge invalide ou expiré.'], 422);
        }

        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Compte verrouillé suite à trop de tentatives échouées. Réessayez plus tard.',
                'locked_until' => $user->locked_until,
            ], 423);
        }

        $valid = ! empty($data['recovery_code'])
            ? $user->consumeRecoveryCode($data['recovery_code'])
            : (! empty($data['code']) && $user->verifyTwoFactorCode($data['code']));

        if (! $valid) {
            $this->registerFailedAttempt($user);

            return response()->json(['message' => 'Code invalide.'], 422);
        }

        Cache::forget($cacheKey);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $this->issueToken($user, $request);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles')),
        ]);
    }
}
