<?php

namespace App\Http\Controllers\Api;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PractitionerController extends Controller implements HasMiddleware
{
    /**
     * Lightweight identity-only lookup so the accueil/secrétariat screen can
     * populate a practitioner select without needing `users.view` (which
     * secrétaire deliberately lacks — see RolePermissionSeeder — to keep
     * full staff-account management out of reach). Gated on
     * `appointments.view` instead: any role that can see the appointments
     * calendar already needs to know which practitioners exist to filter or
     * book against, so this doesn't widen access beyond what's already
     * implied.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:appointments.view'),
        ];
    }

    public function index(): JsonResponse
    {
        $practitioners = User::query()
            ->role('medecin')
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return response()->json([
            'data' => $practitioners->map(fn (User $user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ]),
        ]);
    }
}
