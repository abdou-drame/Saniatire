<?php

namespace App\Http\Middleware;

use App\Domain\Platform\SubscriptionState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lecture seule réelle d'une structure dont l'abonnement a expiré au-delà
 * du délai de grâce (ou a été suspendu/résilié) : toute requête d'écriture
 * est refusée côté backend, quelle que soit l'interface. Les lectures
 * restent permises. Même règle que /auth/me (SubscriptionState), jamais
 * recalculée ailleurs.
 *
 * Exceptions : la gestion de ses propres sessions et la déconnexion
 * restent possibles (sécurité du compte, pas une donnée de la structure),
 * de même que le paiement en ligne du renouvellement : c'est le moyen de
 * sortir de la lecture seule.
 * 423 plutôt que 403 : ce n'est pas un défaut de permission du rôle, la
 * ressource est verrouillée jusqu'au renouvellement.
 */
class EnsureSubscriptionWritable
{
    private const ALWAYS_ALLOWED = [
        'api/auth/sessions*',
        'api/subscription/dexpay-checkout',
        'api/portail-patient/logout',
        'api/portail-prescripteur/logout',
    ];

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        if ($request->isMethodSafe() || $request->is(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        $user = $request->user($guard);
        $state = SubscriptionState::forStructure($user?->structure_id);

        if ($state->isReadOnly()) {
            abort(423, $state->readOnlyMessage());
        }

        return $next($request);
    }
}
