import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { SanteMentaleContent } from "@/components/specialties/sante-mentale/sante-mentale-content";

export function SanteMentalePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="sante_mentale">
      {() => <SanteMentaleContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
