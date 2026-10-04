<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authentification de l'administrateur de plateforme (guard `platform`,
 * voir config/auth.php). Volontairement minimal, même patron que
 * PatientPortalAuthController/PrescriberPortalAuthController pour
 * login/logout/me — mais sans activate() ni forgotPassword()/resetPassword() :
 * il n'y a pas d'inscription ni de flux self-service pour ce compte, créé
 * uniquement via `php artisan platform:create-admin`.
 */
class PlatformAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::query()->where('email', $credentials['email'])->first();

        if ($admin?->isLocked()) {
            $this->auditLogin($admin, 'connexion_plateforme_verrouillee', "Tentative de connexion sur un compte plateforme verrouillé.");

            return response()->json([
                'message' => 'Compte verrouillé suite à trop de tentatives échouées. Réessayez plus tard.',
                'locked_until' => $admin->locked_until,
            ], 423);
        }

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            if ($admin) {
                $this->registerFailedAttempt($admin);
            }

            $this->auditLogin($admin, 'echec_connexion_plateforme', "Échec de connexion à l'administration plateforme.", [
                'email' => $credentials['email'],
            ]);

            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $admin->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

        $token = $admin->createToken('platform-admin')->plainTextToken;

        $this->auditLogin($admin, 'connexion_plateforme', "Connexion à l'administration plateforme.");

        return response()->json([
            'token' => $token,
            'platform_admin' => new PlatformAdminResource($admin),
        ]);
    }

    /**
     * Même règle que le personnel (ManagesAuthTokens) : 5 échecs
     * consécutifs verrouillent le compte 15 minutes.
     */
    private function registerFailedAttempt(PlatformAdmin $admin): void
    {
        $attempts = $admin->failed_login_attempts + 1;
        $locked = $attempts >= self::MAX_ATTEMPTS;

        $admin->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked ? now()->addMinutes(self::LOCK_MINUTES) : $admin->locked_until,
        ])->save();

        if ($locked) {
            $this->auditLogin($admin, 'verrouillage_plateforme', 'Compte plateforme verrouillé après '.self::MAX_ATTEMPTS.' échecs de connexion.');
        }
    }

    /**
     * Connexions (réussies ou non) tracées dans le même journal que les
     * actions plateforme (log_name administration_plateforme, consultable
     * via PlatformAuditLogController). structure_id reste null : une
     * connexion n'appartient à aucune structure. L'IP est ajoutée par le
     * hook Activity::creating d'AppServiceProvider.
     */
    private function auditLogin(?PlatformAdmin $admin, string $action, string $description, array $properties = []): void
    {
        $logger = activity('administration_plateforme')
            ->withProperties([...$properties, 'action' => $action]);

        if ($admin) {
            $logger->causedBy($admin);
        }

        $logger->log($description);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('platform')->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): PlatformAdminResource
    {
        return new PlatformAdminResource($request->user('platform'));
    }
}
