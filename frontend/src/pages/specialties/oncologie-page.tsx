import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { OncologieContent } from "@/components/specialties/oncologie/oncologie-content";

export function OncologiePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="oncologie">
      {() => <OncologieContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
