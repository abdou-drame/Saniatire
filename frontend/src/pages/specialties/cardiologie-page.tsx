import { useParams } from "react-router-dom";
import { SpecialtyPageShell } from "@/components/clinical/specialty-page-shell";
import { CardiologieContent } from "@/components/specialties/cardiologie/cardiologie-content";

export function CardiologiePage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);

  return (
    <SpecialtyPageShell patientId={patientId} specialty="cardiologie">
      {() => <CardiologieContent patientId={patientId} />}
    </SpecialtyPageShell>
  );
}
