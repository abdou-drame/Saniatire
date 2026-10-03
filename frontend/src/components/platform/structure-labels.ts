const STRUCTURE_TYPE_LABEL: Record<string, string> = {
  cabinet: "Cabinet",
  centre_specialise: "Centre spécialisé",
  laboratoire: "Laboratoire",
  imagerie: "Imagerie",
  clinique: "Clinique",
  polyclinique: "Polyclinique",
  groupe_sante: "Groupe santé",
};

export function structureTypeLabel(type: string): string {
  return STRUCTURE_TYPE_LABEL[type] ?? type;
}
