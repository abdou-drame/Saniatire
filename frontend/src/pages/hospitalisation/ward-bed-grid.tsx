import { BedDouble } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import { useWardsWithBeds } from "@/hooks/use-hospitalizations";
import { BED_STATUS_LABEL } from "@/pages/hospitalisation/hospitalisation-status";
import type { Bed, BedStatus, Ward } from "@/types/api";

const BED_TILE_CLASS: Record<BedStatus, string> = {
  libre: "border-success/30 bg-success/10 text-success",
  occupe: "border-danger/30 bg-danger/10 text-danger",
  reserve: "border-warning/30 bg-warning/10 text-warning",
  entretien: "border-border-strong bg-surface-hover text-text-muted",
  indisponible: "border-border-strong bg-surface-hover text-text-subtle",
};

export interface WardBedGridProps {
  /** Si fourni, les tuiles de lits libres deviennent cliquables pour lancer une admission pré-remplie. */
  onSelectFreeBed?: (ward: Ward, bed: Bed) => void;
}

/** Vue par service : chaque lit est une tuile colorée par statut, regroupée par ward. */
export function WardBedGrid({ onSelectFreeBed }: WardBedGridProps) {
  const wardsQuery = useWardsWithBeds();

  if (wardsQuery.isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Vue des lits par service</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-24 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (wardsQuery.isError) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Vue des lits par service</CardTitle>
        </CardHeader>
        <CardContent>
          <ErrorState message={apiErrorMessage(wardsQuery.error)} onRetry={() => wardsQuery.refetch()} />
        </CardContent>
      </Card>
    );
  }

  const wards = wardsQuery.data ?? [];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Vue des lits par service</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {wards.length === 0 ? (
          <EmptyState icon={BedDouble} title="Aucun service" description="Aucun service n'est configuré." />
        ) : (
          wards.map((ward) => (
            <div key={ward.id}>
              <p className="mb-2 text-xs font-medium uppercase tracking-wide text-text-subtle">{ward.name}</p>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                {ward.beds.map((bed) => {
                  const clickable = bed.status === "libre" && Boolean(onSelectFreeBed);
                  // Un lit libre non cliquable ici est presque toujours dû à un
                  // rôle sans le droit d'admettre (ex. infirmier) plutôt qu'à un
                  // vrai bug d'interaction — un survol explique pourquoi plutôt
                  // que de laisser le clic échouer silencieusement.
                  const deniedTooltip = bed.status === "libre" && !onSelectFreeBed
                    ? "Vous n'avez pas les droits pour admettre un patient."
                    : undefined;
                  return (
                    <div
                      key={bed.id}
                      role={clickable ? "button" : undefined}
                      tabIndex={clickable ? 0 : undefined}
                      onClick={clickable ? () => onSelectFreeBed?.(ward, bed) : undefined}
                      onKeyDown={
                        clickable
                          ? (e) => {
                              if (e.key === "Enter" || e.key === " ") {
                                e.preventDefault();
                                onSelectFreeBed?.(ward, bed);
                              }
                            }
                          : undefined
                      }
                      className={cn(
                        "flex flex-col items-center justify-center gap-1 rounded-md border px-2 py-3 text-center",
                        BED_TILE_CLASS[bed.status],
                        clickable && "cursor-pointer transition-transform hover:scale-[1.03] hover:shadow-sm",
                      )}
                      title={clickable ? "Admettre un patient dans ce lit" : deniedTooltip}
                    >
                      <BedDouble size={16} />
                      <span className="text-xs font-medium">
                        {bed.room_number} · {bed.bed_label}
                      </span>
                      <span className="text-[10px] uppercase tracking-wide">{BED_STATUS_LABEL[bed.status]}</span>
                    </div>
                  );
                })}
                {ward.beds.length === 0 && (
                  <p className="col-span-full text-xs text-text-subtle">Aucun lit dans ce service.</p>
                )}
              </div>
            </div>
          ))
        )}
      </CardContent>
    </Card>
  );
}
