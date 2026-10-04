<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * « Mot de passe oublié » des trois espaces (personnel, patient,
 * prescripteur) : la réponse est toujours la même, que l'adresse existe ou
 * non, que l'envoi soit limité ou qu'il échoue. Renvoyer le statut du broker
 * (« Aucun utilisateur avec cette adresse », « Veuillez patienter ») révélait
 * quels emails ont un compte.
 */
trait SendsPasswordResetLinks
{
    private function sendResetLinkWithoutDisclosure(PasswordBroker $broker, Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            $broker->sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            // Serveur d'envoi indisponible : tracé pour l'exploitation, mais
            // jamais exposé (l'échec ne doit pas trahir un compte existant).
            report($exception);
        }

        return response()->json([
            'message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vient de vous être envoyé.',
        ]);
    }
}
