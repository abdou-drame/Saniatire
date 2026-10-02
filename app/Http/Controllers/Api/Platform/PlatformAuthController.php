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
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::query()->where('email', $credentials['email'])->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $this->auditLogin($admin, 'echec_connexion_plateforme', "Échec de connexion à l'administration plateforme.", [
                'email' => $credentials['email'],
            ]);

            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $token = $admin->createToken('platform-admin')->plainTextToken;

        $this->auditLogin($admin, 'connexion_plateforme', "Connexion à l'administration plateforme.");

        return response()->json([
            'token' => $token,
            'platform_admin' => new PlatformAdminResource($admin),
        ]);
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
