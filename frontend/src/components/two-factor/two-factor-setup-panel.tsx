import { useMutation } from "@tanstack/react-query";
import { AlertTriangle, LoaderCircle, ShieldCheck } from "lucide-react";
import QRCode from "qrcode";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { SixDigitInput } from "@/components/two-factor/six-digit-input";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";

interface SetupResponse {
  secret: string;
  qr_code_url: string;
}

interface ConfirmResponse {
  message: string;
  recovery_codes: string[];
}

/**
 * Shared 2FA enrollment flow: generate secret + QR code, confirm with a
 * first TOTP code, then show the one-time recovery codes. Used both from
 * the account settings page and from the forced-setup screen shown to
 * mandatory-2FA roles that haven't completed enrollment yet.
 */
export function TwoFactorSetupPanel({ onActivated }: { onActivated: () => void }) {
  const [step, setStep] = useState<"idle" | "qr" | "done">("idle");
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [secret, setSecret] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [attempt, setAttempt] = useState(0);

  const setupMutation = useMutation({
    mutationFn: async () => (await api.post<SetupResponse>("/auth/2fa/setup")).data,
    onSuccess: async (data) => {
      setSecret(data.secret);
      setError(null);
      const dataUrl = await QRCode.toDataURL(data.qr_code_url);
      setQrDataUrl(dataUrl);
      setStep("qr");
    },
    onError: (err) => setError(apiErrorMessage(err)),
  });

  const confirmMutation = useMutation({
    mutationFn: async (code: string) => (await api.post<ConfirmResponse>("/auth/2fa/confirm", { code })).data,
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes);
      setStep("done");
      setError(null);
    },
    onError: (err) => {
      setError(apiErrorMessage(err));
      setAttempt((a) => a + 1);
    },
  });

  if (step === "idle") {
    return (
      <div className="space-y-3">
        <p className="text-sm text-text-muted">
          Activez la double authentification pour sécuriser votre compte avec un code généré par une
          application d'authentification (Google Authenticator, Authy...).
        </p>
        {error && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
        )}
        <Button size="sm" onClick={() => setupMutation.mutate()} disabled={setupMutation.isPending}>
          {setupMutation.isPending && <LoaderCircle size={14} className="animate-spin" />}
          Activer la 2FA
        </Button>
      </div>
    );
  }

  if (step === "qr") {
    return (
      <div className="space-y-4">
        <div className="flex flex-col items-center gap-3">
          {qrDataUrl && (
            <img
              src={qrDataUrl}
              alt="QR code d'activation de la double authentification"
              className="h-48 w-48 rounded-md border border-border bg-white p-2"
            />
          )}
          <p className="text-center text-xs text-text-muted">
            Scannez ce QR code avec votre application d'authentification, ou saisissez ce code manuellement :
          </p>
          <code className="rounded-md border border-border bg-bg px-3 py-1.5 text-xs tracking-wider text-text">
            {secret}
          </code>
        </div>

        <div className="space-y-2">
          <label className="text-xs font-medium text-text-muted">
            Code à 6 chiffres généré par l'application
          </label>
          <SixDigitInput
            key={attempt}
            disabled={confirmMutation.isPending}
            onComplete={(code) => confirmMutation.mutate(code)}
          />
        </div>

        {confirmMutation.isPending && (
          <p className="flex items-center gap-1.5 text-xs text-text-muted">
            <LoaderCircle size={12} className="animate-spin" /> Vérification...
          </p>
        )}

        {error && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <p className="flex items-center gap-1.5 rounded-md border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
        <ShieldCheck size={14} /> Double authentification activée.
      </p>

      <div className="space-y-2 rounded-md border border-warning/30 bg-warning/10 p-3">
        <p className="flex items-center gap-1.5 text-xs font-medium text-warning">
          <AlertTriangle size={14} /> Notez ces codes de récupération maintenant — ils ne seront plus jamais
          affichés. Chacun ne peut être utilisé qu'une seule fois si vous perdez l'accès à votre application.
        </p>
        <div className="grid grid-cols-2 gap-2 font-mono text-xs text-text">
          {recoveryCodes.map((code) => (
            <span key={code} className="rounded border border-border bg-bg px-2 py-1 text-center">
              {code}
            </span>
          ))}
        </div>
      </div>

      <Button size="sm" onClick={onActivated}>
        J'ai noté mes codes de récupération
      </Button>
    </div>
  );
}
