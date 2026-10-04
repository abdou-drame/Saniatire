<?php

namespace App\Http\Controllers\Api;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use App\Http\Controllers\Api\Concerns\ManagesAuthTokens;
use App\Http\Controllers\Api\Concerns\SendsPasswordResetLinks;
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
    use ManagesAuthTokens, SendsPasswordResetLinks;

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

        if ($reason = Structure::accessDenialReason($user->structure_id)) {
            return response()->json(['message' => $reason], 403);
        }

        // Étape 9 §2 : un utilisateur ayant déjà confirmé sa 2FA ne reçoit
        // jamais de token API à cette étape — seulement un challenge
        // temporaire (cache, 5 min) échangé contre un vrai token via
        // TwoFactorController::challenge() après vérification du code TOTP
        // (ou d'un code de récupération). Aucun accès API n'est possible
        // entre les deux appels. Aucune exception de rôle : une 2FA affichée
        // « activée » est toujours vérifiée à la connexion.
        if ($user->hasTwoFactorEnabled()) {
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
     * ce compte n'est pas passé par ici.
     *
     * Le mot de passe actuel (y compris le temporaire, connu puisqu'il vient
     * de servir à se connecter) est exigé : un jeton volé ne doit pas
     * suffire à verrouiller le titulaire hors de son compte. Les autres
     * sessions sont révoquées, seule la session courante reste ouverte.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Mot de passe actuel incorrect.',
                'errors' => ['current_password' => ['Mot de passe actuel incorrect.']],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return response()->json(['message' => 'Mot de passe changé.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->sendResetLinkWithoutDisclosure(Password::broker(), $request);
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

                // Mot de passe oublié = compte potentiellement compromis :
                // toutes les sessions ouvertes sont fermées.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
