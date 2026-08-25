<?php

namespace App\Http\Controllers\Api;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PatientPortalAuthController extends Controller
{
    public function __construct(private readonly PortalActivationService $activationService) {}

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $patient = $this->activationService->activate(Patient::class, $data['token'], $data['password']);

        return response()->json(['message' => 'Compte activé. Vous pouvez vous connecter.', 'patient' => new PatientResource($patient)]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $patient = Patient::withoutGlobalScopes()->where('email', $credentials['email'])->first();

        if (! $patient || ! $patient->portal_activated_at) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        if (! Hash::check($credentials['password'], $patient->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $token = $patient->createToken('patient-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'patient' => new PatientResource($patient),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('patient')->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): PatientResource
    {
        return new PatientResource($request->user('patient'));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('patients')->sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker('patients')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Patient $patient, string $password) {
                $patient->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
