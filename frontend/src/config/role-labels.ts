const ROLE_LABELS: Record<string, string> = {
  medecin: "Médecin",
  infirmier: "Infirmier",
  secretaire: "Secrétaire",
  administrateur: "Administrateur",
  direction: "Direction",
  directeur_medical: "Directeur médical",
  comptable: "Comptable",
  caissier: "Caissier",
  conformite: "Conformité",
};

export function roleLabel(role: string | undefined): string {
  if (!role) return "—";
  return ROLE_LABELS[role] ?? role;
}
