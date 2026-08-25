<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Étape 9 §2 : bloque l'accès au reste de l'API tant qu'un utilisateur dont
 * le rôle exige la 2FA (User::ROLES_REQUIRING_TWO_FACTOR) ne l'a pas
 * confirmée. Le token émis au login reste valide pour /auth/2fa/setup,
 * /auth/2fa/confirm, /auth/me et /auth/logout uniquement — ces routes sont
 * délibérément placées hors du groupe portant ce middleware dans
 * routes/api.php plutôt qu'exemptées ici par chemin, pour ne pas dupliquer
 * la liste des routes autorisées dans deux fichiers.
 */
class EnsureTwoFactorSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'requiresTwoFactor')
            && $user->requiresTwoFactor()
            && ! $user->hasTwoFactorEnabled()) {
            abort(423, "Authentification à deux facteurs obligatoire pour ce rôle : activez-la via /auth/2fa/setup avant de continuer.");
        }

        return $next($request);
    }
}
