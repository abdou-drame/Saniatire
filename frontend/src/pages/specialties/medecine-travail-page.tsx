import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { MedecineTravailContent } from "@/components/specialties/medecine-travail/medecine-travail-content";

export function MedecineTravailPage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="medecine_travail">
      {() => <MedecineTravailContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
