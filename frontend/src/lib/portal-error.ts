import axios from "axios";

/** Same role as api-error.ts's apiErrorMessage, but worded for a patient audience — no HTTP/technical jargon. */
export function portalErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (!error.response) {
      return "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.";
    }
    const status = error.response.status;
    const serverMessage = (error.response.data as { message?: string } | undefined)?.message;
    if (status === 404) {
      return "Cette information n'est plus disponible.";
    }
    if (status === 422) {
      return serverMessage ?? "Certaines informations saisies ne sont pas valides.";
    }
    return "Une erreur est survenue. Réessayez dans un instant.";
  }
  return "Une erreur est survenue. Réessayez dans un instant.";
}
