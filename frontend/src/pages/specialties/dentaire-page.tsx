import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { DentaireContent } from "@/components/specialties/dentaire/dentaire-content";

export function DentairePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="dentaire">
      {() => <DentaireContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
