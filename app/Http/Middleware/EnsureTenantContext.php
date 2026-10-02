<?php

namespace App\Http\Middleware;

use App\Domain\Structure\Models\Structure;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defensive check sitting in front of the TenantScope: an authenticated
 * user without a structure_id is an inconsistent state (should never
 * happen given the users.structure_id NOT NULL constraint) that would
 * otherwise make TenantScope silently return unscoped, all-tenant results.
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = $request->user($guard);

        if ($user && ! $user->structure_id) {
            abort(403, 'Aucune structure associée à cet utilisateur.');
        }

        // Étape 7b : un compte externe (prescripteur) désactivé après coup
        // ne doit plus pouvoir utiliser un token déjà émis — sans ce
        // contrôle, la vérification `isActif()` du login ne s'applique
        // qu'à l'émission du token, jamais à sa réutilisation ultérieure.
        if ($user && method_exists($user, 'isActif') && ! $user->isActif()) {
            abort(401, 'Ce compte est désactivé.');
        }

        // Structure suspendue ou archivée par l'administration plateforme :
        // même contrôle qu'au login (Structure::accessDenialReason()),
        // répété ici pour qu'un token émis avant la suspension cesse
        // immédiatement de fonctionner. 401 plutôt que 403 : les clients
        // frontend purgent le token sur 401 et renvoient à la page de
        // connexion, qui affiche alors le motif exact.
        if ($user && ($reason = Structure::accessDenialReason($user->structure_id))) {
            abort(401, $reason);
        }

        return $next($request);
    }
}
