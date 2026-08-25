import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { DialyseContent } from "@/components/specialties/dialyse/dialyse-content";

export function DialysePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="dialyse">
      {() => <DialyseContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
