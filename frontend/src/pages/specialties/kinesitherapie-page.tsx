import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { KinesitherapieContent } from "@/components/specialties/kinesitherapie/kinesitherapie-content";

export function KinesitherapiePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="kinesitherapie">
      {() => <KinesitherapieContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
