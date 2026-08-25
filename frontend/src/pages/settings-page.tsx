import { ShieldCheck } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DisableTwoFactorDialog } from "@/components/two-factor/disable-two-factor-dialog";
import { TwoFactorSetupPanel } from "@/components/two-factor/two-factor-setup-panel";
import { useAuth } from "@/hooks/use-auth";

export function SettingsPage() {
  const { user, refreshUser } = useAuth();
  const [disableOpen, setDisableOpen] = useState(false);

  if (!user) return null;

  return (
    <div className="mx-auto max-w-2xl space-y-6 p-6">
      <div>
        <h1 className="font-heading text-lg font-semibold text-text">Paramètres</h1>
        <p className="text-sm text-text-muted">Gérez la sécurité de votre compte.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Authentification à deux facteurs</CardTitle>
          {user.two_factor_enabled ? (
            <Badge status="success">2FA activée</Badge>
          ) : (
            <Badge status={user.two_factor_required ? "danger" : "neutral"}>2FA non activée</Badge>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {user.two_factor_enabled ? (
            <>
              <p className="text-sm text-text-muted">
                Un code généré par votre application d'authentification est demandé à chaque connexion.
              </p>
              {user.two_factor_required ? (
                <p className="flex items-center gap-1.5 text-xs text-text-subtle">
                  <ShieldCheck size={14} /> Obligatoire pour votre rôle — ne peut pas être désactivée.
                </p>
              ) : (
                <Button variant="secondary" size="sm" onClick={() => setDisableOpen(true)}>
                  Désactiver la 2FA
                </Button>
              )}
            </>
          ) : (
            <TwoFactorSetupPanel onActivated={() => refreshUser()} />
          )}
        </CardContent>
      </Card>

      <DisableTwoFactorDialog open={disableOpen} onOpenChange={setDisableOpen} onDisabled={() => refreshUser()} />
    </div>
  );
}
