import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { MaterniteContent } from "@/components/specialties/maternite/maternite-content";

export function MaternitePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="maternite">
      {() => <MaterniteContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
