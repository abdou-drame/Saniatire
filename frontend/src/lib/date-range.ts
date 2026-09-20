function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function dateKey(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Fenêtre par défaut (30 derniers jours) pour les widgets de synthèse du
 * tableau de bord — même logique que `defaultFrom`/`defaultTo` de
 * DirectionPage (`/rapports`), dont les widgets ne sont qu'un résumé.
 */
export function defaultDashboardRange(): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  from.setMonth(from.getMonth() - 1);
  return { from: dateKey(from), to: dateKey(to) };
}

export function todayKey(): string {
  return dateKey(new Date());
}
