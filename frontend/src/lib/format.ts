export function formatFcfa(amount: number): string {
  return `${amount.toLocaleString("fr-FR", { maximumFractionDigits: 0 })} FCFA`;
}

export function formatNumber(value: number): string {
  return value.toLocaleString("fr-FR");
}

export function formatPercent(value: number | null): string {
  return value === null ? "—" : `${value.toLocaleString("fr-FR", { maximumFractionDigits: 1 })} %`;
}

/**
 * For fields the backend returns as a 0-1 ratio (e.g. taux_recouvrement =
 * round(encaisse/facture, 2)) rather than an already-scaled 0-100 percent.
 * Multiplying by 100 is unit display formatting, not a recalculation: the
 * backend's rounding to 2 decimal places on the fraction already fixes the
 * value to whole percentage points, so 0 decimals here shows no more and
 * no less precision than the backend produced.
 */
export function formatRatioAsPercent(value: number | null): string {
  return value === null ? "—" : `${Math.round(value * 100).toLocaleString("fr-FR")} %`;
}
