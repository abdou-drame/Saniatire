import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { PediatrieContent } from "@/components/specialties/pediatrie/pediatrie-content";

export function PediatriePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="pediatrie">
      {() => <PediatrieContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
