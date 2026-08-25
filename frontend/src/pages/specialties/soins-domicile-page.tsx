import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { SoinsDomicileContent } from "@/components/specialties/soins-domicile/soins-domicile-content";

export function SoinsDomicilePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="soins_domicile">
      {(patient) => <SoinsDomicileContent patientId={patientId} patient={patient} />}
    </SpecialtyPageShell>
  );
}
