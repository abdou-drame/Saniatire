import axios from "axios";

/** Turns any error thrown by an api.* call into a short, French, user-facing message. */
export function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (!error.response) {
      return "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.";
    }
    const status = error.response.status;
    const serverMessage = (error.response.data as { message?: string } | undefined)?.message;
    if (status === 403) {
      // Certains contrôleurs (ex. validation d'une commande d'achat par
      // rôle Spatie) renvoient un message 403 précis et destiné à être
      // affiché tel quel (ex. "Le rôle « X » est requis pour valider ce
      // niveau.") — on le préserve verbatim s'il est présent plutôt que de
      // toujours retomber sur le message générique.
      return serverMessage ?? "Vous n'avez pas les permissions nécessaires pour accéder à ces données.";
    }
    if (status === 404) {
      return "Ressource introuvable.";
    }
    if (status === 422) {
      return serverMessage ?? "Certaines informations saisies sont invalides.";
    }
    if (status >= 500) {
      return "Le serveur a rencontré une erreur. Réessayez dans un instant.";
    }
    return serverMessage ?? "Une erreur inattendue est survenue.";
  }
  return "Une erreur inattendue est survenue.";
}

export function isForbidden(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 403;
}

const UNAVAILABLE_PREFIX = "Praticien indisponible sur ce créneau";

/** Distinguishes the specific "practitioner unavailable" 422 from any other
 * validation error, so the appointment dialog can offer a dérogation instead
 * of showing the generic error box. The prefix is a stable server-side
 * literal (see AppointmentController::presenceCheckResponse), not user input. */
export function isPractitionerUnavailableError(error: unknown): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) return false;
  const message = (error.response.data as { message?: string } | undefined)?.message;
  return Boolean(message?.startsWith(UNAVAILABLE_PREFIX));
}
