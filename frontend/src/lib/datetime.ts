export function minutesSince(isoDate: string): number {
  return Math.max(0, Math.round((Date.now() - new Date(isoDate).getTime()) / 60_000));
}

export function isToday(isoDate: string | null): boolean {
  if (!isoDate) return false;
  const date = new Date(isoDate);
  const now = new Date();
  return (
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate()
  );
}

/**
 * Toutes les dates/heures de l'API sont sérialisées en UTC (config('app.timezone')
 * = 'UTC' côté Laravel), et cette valeur UTC représente déjà l'heure murale
 * voulue (ex. WorkSchedule.heure_debut = "08:00" est stockée telle quelle,
 * sans conversion). `timeZone: "UTC"` fige donc l'affichage sur cette même
 * heure murale quel que soit le fuseau du navigateur — sans ça, un patient
 * dont le navigateur n'est pas en UTC verrait des créneaux décalés par
 * rapport à ceux configurés sur l'écran Plannings (qui affiche heure_debut/
 * heure_fin en texte brut, donc jamais affecté par ce décalage).
 */
export function formatTime(isoDate: string): string {
  return new Date(isoDate).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit", timeZone: "UTC" });
}

export function formatDateTime(isoDate: string): string {
  return new Date(isoDate).toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    timeZone: "UTC",
  });
}

export function formatDate(isoDate: string): string {
  return new Date(isoDate).toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    timeZone: "UTC",
  });
}

export function age(birthDate: string): number {
  const birth = new Date(birthDate);
  const now = new Date();
  let years = now.getFullYear() - birth.getFullYear();
  const monthDiff = now.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
    years--;
  }
  return years;
}
