import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalPreferences, useUpdatePortalPreferences } from "@/hooks/portal/use-portal-preferences";
import { portalErrorMessage } from "@/lib/portal-error";
import type { NotificationChannel } from "@/types/api";

const CHANNEL_OPTIONS: { value: NotificationChannel; label: string }[] = [
  { value: "email", label: "E-mail" },
  { value: "sms", label: "SMS" },
  { value: "whatsapp", label: "WhatsApp" },
  { value: "push", label: "Notification sur l'application" },
];

export function PortalPreferencesPage() {
  const preferencesQuery = usePortalPreferences();
  const updateMutation = useUpdatePortalPreferences();
  const [selected, setSelected] = useState<NotificationChannel[]>([]);
  const [notice, setNotice] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (preferencesQuery.data) setSelected(preferencesQuery.data.canaux);
  }, [preferencesQuery.data]);

  function toggle(channel: NotificationChannel) {
    setSaved(false);
    setSelected((prev) => (prev.includes(channel) ? prev.filter((c) => c !== channel) : [...prev, channel]));
  }

  function handleSave() {
    setNotice(null);
    if (selected.length === 0) {
      setNotice("Choisissez au moins un moyen de notification.");
      return;
    }
    updateMutation.mutate(selected, {
      onSuccess: () => setSaved(true),
      onError: (error) => setNotice(portalErrorMessage(error)),
    });
  }

  return (
    <div className="space-y-6">
      <h2 className="font-heading text-lg font-semibold text-text">Mes préférences</h2>

      {preferencesQuery.isError ? (
        <ErrorState message={portalErrorMessage(preferencesQuery.error)} onRetry={() => preferencesQuery.refetch()} />
      ) : preferencesQuery.isLoading ? (
        <TableSkeleton rows={4} columns={1} />
      ) : (
        <Card>
          <CardContent className="space-y-4 pt-5">
            <p className="text-sm text-text-muted">Choisissez comment vous souhaitez être prévenu (rendez-vous, résultats, factures).</p>
            <div className="space-y-2">
              {CHANNEL_OPTIONS.map((option) => (
                <label
                  key={option.value}
                  className="flex items-center gap-3 rounded-md border border-border bg-surface px-4 py-3 text-sm text-text"
                >
                  <input
                    type="checkbox"
                    checked={selected.includes(option.value)}
                    onChange={() => toggle(option.value)}
                    className="h-4 w-4 rounded border-border-strong accent-accent"
                  />
                  {option.label}
                </label>
              ))}
            </div>

            {notice && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">{notice}</p>
            )}
            {saved && !notice && (
              <p className="flex items-center gap-1.5 rounded-md border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
                <CheckCircle2 size={14} />
                Préférences enregistrées.
              </p>
            )}

            <Button onClick={handleSave} disabled={updateMutation.isPending}>
              {updateMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
              Enregistrer
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
