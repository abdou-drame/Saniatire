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
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $token = $admin->createToken('platform-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'platform_admin' => new PlatformAdminResource($admin),
        ]);
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
