import { describe, expect, it } from "vitest";
import { formatDate, formatDateTime, formatTime } from "@/lib/datetime";

/**
 * Toute date/heure renvoyée par l'API porte un offset UTC qui représente
 * déjà l'heure murale voulue (ex. WorkSchedule.heure_debut = "08:00" est
 * stocké tel quel). Sans timeZone: "UTC" fixé, ces fonctions reformatent la
 * même valeur différemment selon le fuseau de la machine qui exécute les
 * tests/le navigateur — d'où l'assertion explicite sur un fuseau autre que
 * UTC (voir régression : portail patient affichant des créneaux décalés
 * par rapport à ceux configurés sur l'écran Plannings).
 */
describe("datetime formatting pins UTC regardless of local timezone", () => {
  const iso = "2026-09-07T08:00:00+00:00";

  it("formatDateTime always shows the UTC wall-clock hour", () => {
    expect(formatDateTime(iso)).toBe("07/09/2026 08:00");
  });

  it("formatTime always shows the UTC wall-clock hour", () => {
    expect(formatTime(iso)).toBe("08:00");
  });

  it("formatDate never rolls over to an adjacent day", () => {
    // 23h30 UTC on the 7th must stay the 7th, not shift to the 8th in a
    // timezone ahead of UTC.
    expect(formatDate("2026-09-07T23:30:00+00:00")).toBe("07/09/2026");
  });
});
