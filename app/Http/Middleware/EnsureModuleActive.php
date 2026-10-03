<?php

namespace App\Http\Middleware;

use App\Domain\Platform\ModuleCatalog;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:<clé>` : refuse côté backend toute route d'un module désactivé
 * pour la structure du compte connecté (le frontend ne fait que masquer
 * les menus, à partir de la liste renvoyée par /auth/me). Règle unique dans
 * ModuleCatalog. Lectures comprises : un module coupé n'est plus consultable.
 *
 * Portail prescripteur coupé (module `prescripteurs`) : 401 plutôt que 403,
 * même traitement qu'une structure suspendue (EnsureTenantContext) — le
 * frontend du portail purge alors le jeton et renvoie à la connexion, qui
 * affiche le motif exact au lieu d'un portail qui plante.
 */
class EnsureModuleActive
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! ModuleCatalog::isCore($module) && ! ModuleCatalog::isPremium($module)) {
            throw new InvalidArgumentException("Module inconnu dans la définition des routes : {$module}");
        }

        $user = $request->user();

        if (! ModuleCatalog::isActiveFor($user?->structure_id, $module)) {
            $status = $module === 'prescripteurs' && $user instanceof ExternalPrescriber ? 401 : 403;
            abort($status, ModuleCatalog::inactiveMessage($module));
        }

        return $next($request);
    }
}
