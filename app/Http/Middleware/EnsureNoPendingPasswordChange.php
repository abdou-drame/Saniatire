<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration plateforme : le premier administrateur d'une structure
 * reçoit un mot de passe généré (jamais choisi par le platform admin qui
 * crée le compte) — ce middleware bloque le reste de l'API tant qu'il n'a
 * pas été changé. /auth/change-password, /auth/me et /auth/logout restent
 * hors du groupe de routes portant ce middleware, même patron que
 * EnsureTwoFactorSetupComplete pour /auth/2fa/*.
 */
class EnsureNoPendingPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            abort(423, 'Vous devez changer votre mot de passe avant de continuer : utilisez /auth/change-password.');
        }

        return $next($request);
    }
}
