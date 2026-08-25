<?php

namespace App\Http\Controllers\Api;

use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExternalPrescriberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PrescriberPortalAuthController extends Controller
{
    public function __construct(private readonly PortalActivationService $activationService) {}

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $prescriber = $this->activationService->activate(ExternalPrescriber::class, $data['token'], $data['password']);

        return response()->json(['message' => 'Compte activé. Vous pouvez vous connecter.', 'prescriber' => new ExternalPrescriberResource($prescriber)]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $prescriber = ExternalPrescriber::withoutGlobalScopes()->where('email', $credentials['email'])->first();

        if (! $prescriber || ! $prescriber->portal_activated_at || ! $prescriber->isActif()) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        if (! Hash::check($credentials['password'], $prescriber->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $token = $prescriber->createToken('prescriber-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'prescriber' => new ExternalPrescriberResource($prescriber),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('prescriber')->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): ExternalPrescriberResource
    {
        return new ExternalPrescriberResource($request->user('prescriber'));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('external_prescribers')->sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker('external_prescribers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (ExternalPrescriber $prescriber, string $password) {
                $prescriber->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
