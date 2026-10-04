<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité posés sur toutes les réponses de l'API. Elle ne
 * sert que du JSON et des fichiers téléchargés : la CSP interdit tout
 * chargement de ressource et tout affichage dans un cadre, ce qui
 * neutralise une réponse détournée (ex. un fichier importé contenant du
 * HTML) ouverte directement dans un navigateur.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        // HSTS n'a de sens (et n'est pris en compte) qu'en HTTPS ; derrière
        // le proxy Dokploy, trustProxies rend isSecure() fiable.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
