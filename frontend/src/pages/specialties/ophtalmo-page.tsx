import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { OphtalmoContent } from "@/components/specialties/ophtalmo/ophtalmo-content";

export function OphtalmoPage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="ophtalmo">
      {() => <OphtalmoContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
