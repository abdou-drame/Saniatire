<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Étape 9 §2 : gestion des sessions/connexions actives. Réutilise la table
 * Sanctum `personal_access_tokens` (étendue de ip_address/user_agent, voir
 * la migration 2026_08_28_000002) plutôt qu'une table "sessions" dédiée —
 * un token Sanctum représente déjà une session API dans ce projet.
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'ip_address' => $token->ip_address,
                'user_agent' => $token->user_agent,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }

        return response()->json(['message' => 'Session révoquée.']);
    }

    public function revokeOthers(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();

        $count = $request->user()->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json(['message' => "{$count} session(s) révoquée(s)."]);
    }
}
