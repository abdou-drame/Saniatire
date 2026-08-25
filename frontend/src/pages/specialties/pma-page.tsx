import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { PmaContent } from "@/components/specialties/pma/pma-content";

export function PmaPage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="pma">
      {() => <PmaContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
