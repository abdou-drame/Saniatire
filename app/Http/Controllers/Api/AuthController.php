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
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ManagesAuthTokens;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScopes()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Compte verrouillé suite à trop de tentatives échouées. Réessayez plus tard.',
                'locked_until' => $user->locked_until,
            ], 423);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            $this->registerFailedAttempt($user);

            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        // Étape 9 §2 : un utilisateur ayant déjà confirmé sa 2FA ne reçoit
        // jamais de token API à cette étape — seulement un challenge
        // temporaire (cache, 5 min) échangé contre un vrai token via
        // TwoFactorController::challenge() après vérification du code TOTP
        // (ou d'un code de récupération). Aucun accès API n'est possible
        // entre les deux appels.
        if ($user->hasTwoFactorEnabled() && ! $user->hasRole('administrateur')) {
            $challenge = Str::random(40);
            Cache::put("2fa_challenge:{$challenge}", $user->id, now()->addMinutes(5));

            return response()->json([
                'two_factor_required' => true,
                'challenge' => $challenge,
            ]);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $this->issueToken($user, $request);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles', 'sites', 'structure')),
            // Rôle à 2FA obligatoire mais pas encore activée : le frontend
            // doit rediriger vers /auth/2fa/setup. EnsureTwoFactorSetupComplete
            // bloque déjà tout le reste de l'API tant que ce n'est pas fait.
            'two_factor_setup_required' => $user->requiresTwoFactor(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'sites', 'structure'));
    }

    /**
     * Administration plateforme : le premier administrateur d'une structure
     * reçoit un mot de passe généré, jamais choisi par le platform admin —
     * EnsureNoPendingPasswordChange bloque tout le reste de l'API tant que
     * ce compte n'est pas passé par ici. Contrairement à resetPassword()
     * (token e-mail, mot de passe oublié), l'identité est déjà prouvée par
     * le token d'authentification courant : pas de mot de passe actuel
     * demandé, la temporaire n'a de toute façon jamais été choisie par
     * l'utilisateur.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        return response()->json(['message' => 'Mot de passe changé.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
