import axios from "axios";
import { AlertTriangle, Check, Copy, LoaderCircle, Plus, UserCog } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { FieldError, Label } from "@/components/ui/label";
import { TableSkeleton } from "@/components/ui/loading-state";
import { PlatformInitials } from "@/components/platform/platform-ui";
import {
  useCreatePlatformAdministrator,
  usePlatformStructureAdministrators,
  useResetPlatformUserPassword,
  useSetPlatformAdministratorActive,
  useUnlockPlatformUser,
} from "@/hooks/use-platform-administrators";
import { apiErrorMessage } from "@/lib/api-error";
import type { PlatformStaffUser, PlatformStaffUserWithPassword } from "@/types/api";

type FormField = "first_name" | "last_name" | "email";
type FieldErrors = Partial<Record<FormField, string>>;

const EMPTY_FORM: Record<FormField, string> = { first_name: "", last_name: "", email: "" };

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Les messages de validation Laravel ne sont pas garantis en français (locale
 * du backend) : on ne retient que le champ en cause et on reformule ici.
 */
function serverFieldErrors(error: unknown): FieldErrors | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) return null;
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors;
  if (!errors) return null;
  const result: FieldErrors = {};
  if (errors.first_name) result.first_name = "Prénom invalide.";
  if (errors.last_name) result.last_name = "Nom invalide.";
  if (errors.email) {
    const raw = errors.email.join(" ").toLowerCase();
    result.email = raw.includes("taken") || raw.includes("déjà") || raw.includes("unique")
      ? "Cet email est déjà utilisé par un autre compte."
      : "Adresse email invalide.";
  }
  return Object.keys(result).length > 0 ? result : null;
}

function validate(form: Record<FormField, string>): FieldErrors {
  const errors: FieldErrors = {};
  if (!form.first_name.trim()) errors.first_name = "Le prénom est obligatoire.";
  if (!form.last_name.trim()) errors.last_name = "Le nom est obligatoire.";
  if (!form.email.trim()) errors.email = "L'email est obligatoire.";
  else if (!EMAIL_PATTERN.test(form.email.trim())) errors.email = "Adresse email invalide.";
  return errors;
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString("fr-FR", { dateStyle: "short", timeStyle: "short" });
}

/**
 * Affichage unique d'un mot de passe généré. Aucun endpoint ne le restitue
 * ensuite : il ne vit que dans l'état local du dialogue qui l'affiche.
 */
function GeneratedPasswordPanel({ password }: { password: string }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(password);
      setCopied(true);
    } catch {
      // Presse-papiers indisponible (contexte non sécurisé, permission refusée) :
      // le mot de passe reste affiché à l'écran, pas de dégradation fonctionnelle.
    }
  }

  return (
    <div className="space-y-3 rounded-md border border-warning/30 bg-warning/10 p-3">
      <p className="flex items-start gap-1.5 text-xs font-medium text-warning">
        <AlertTriangle size={14} className="mt-0.5 shrink-0" />
        Mot de passe affiché une seule fois — notez-le et transmettez-le de façon sécurisée à l'administrateur. Il ne
        sera plus jamais consultable après la fermeture de cette fenêtre.
      </p>
      <div className="flex flex-wrap items-center gap-2">
        <code
          data-testid="generated-password"
          className="min-w-0 flex-1 break-all rounded border border-border bg-bg px-3 py-1.5 text-sm tracking-wider text-text"
        >
          {password}
        </code>
        <Button type="button" variant="secondary" size="sm" onClick={handleCopy}>
          {copied ? <Check size={14} /> : <Copy size={14} />}
          {copied ? "Copié" : "Copier"}
        </Button>
      </div>
      <p className="text-xs text-warning">
        Un changement de mot de passe sera exigé de l'administrateur dès sa prochaine connexion.
      </p>
    </div>
  );
}

interface RevealedPassword {
  title: string;
  user: PlatformStaffUser;
  password: string;
}

/**
 * Dialogue de restitution : impossible à fermer autrement que par le bouton
 * explicite (Échap et clic sur l'overlay ignorés) pour ne pas perdre un mot
 * de passe jamais restitué.
 */
function PasswordRevealDialog({ revealed, onAcknowledge }: {
  revealed: RevealedPassword | null;
  onAcknowledge: () => void;
}) {
  return (
    <Dialog open={revealed !== null} onOpenChange={() => undefined}>
      <DialogContent className="w-[calc(100%-2rem)]">
        {revealed && (
          <>
            <DialogHeader>
              <DialogTitle>{revealed.title}</DialogTitle>
              <DialogDescription>
                {revealed.user.first_name} {revealed.user.last_name} ({revealed.user.email})
              </DialogDescription>
            </DialogHeader>
            <GeneratedPasswordPanel password={revealed.password} />
            <DialogFooter>
              <Button type="button" onClick={onAcknowledge}>
                J'ai noté le mot de passe
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

function CreateAdministratorDialog({ structureId, open, onOpenChange, onCreated }: {
  structureId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (revealed: RevealedPassword) => void;
}) {
  const createAdministrator = useCreatePlatformAdministrator(structureId);
  const [form, setForm] = useState(EMPTY_FORM);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  function reset() {
    setForm(EMPTY_FORM);
    setFieldErrors({});
    setSubmitError(null);
  }

  function handleOpenChange(next: boolean) {
    if (!next && createAdministrator.isPending) return;
    onOpenChange(next);
    if (!next) reset();
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setSubmitError(null);
    const errors = validate(form);
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    createAdministrator.mutate(
      { first_name: form.first_name.trim(), last_name: form.last_name.trim(), email: form.email.trim() },
      {
        onSuccess: (result) => {
          onOpenChange(false);
          reset();
          onCreated({ title: "Administrateur créé", user: result.data, password: result.generated_password });
        },
        onError: (err) => {
          const serverErrors = serverFieldErrors(err);
          if (serverErrors) {
            setFieldErrors(serverErrors);
          } else {
            setSubmitError(apiErrorMessage(err));
          }
        },
      },
    );
  }

  function update(field: FormField, value: string) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="w-[calc(100%-2rem)]">
        <DialogHeader>
          <DialogTitle>Nouvel administrateur</DialogTitle>
          <DialogDescription>
            Un mot de passe provisoire est généré et affiché une seule fois ; l'administrateur devra le changer à sa
            première connexion.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} noValidate className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label htmlFor="administrator-first-name">Prénom</Label>
              <Input
                id="administrator-first-name"
                value={form.first_name}
                onChange={(e) => update("first_name", e.target.value)}
                autoComplete="off"
              />
              <FieldError>{fieldErrors.first_name}</FieldError>
            </div>
            <div>
              <Label htmlFor="administrator-last-name">Nom</Label>
              <Input
                id="administrator-last-name"
                value={form.last_name}
                onChange={(e) => update("last_name", e.target.value)}
                autoComplete="off"
              />
              <FieldError>{fieldErrors.last_name}</FieldError>
            </div>
          </div>
          <div>
            <Label htmlFor="administrator-email">E-mail de connexion</Label>
            <Input
              id="administrator-email"
              type="email"
              value={form.email}
              onChange={(e) => update("email", e.target.value)}
              autoComplete="off"
            />
            <FieldError>{fieldErrors.email}</FieldError>
          </div>
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{submitError}</p>
          )}
          <DialogFooter className="flex-wrap">
            <Button type="button" variant="secondary" onClick={() => handleOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={createAdministrator.isPending}>
              {createAdministrator.isPending && <LoaderCircle size={16} className="animate-spin" />}
              Créer l'administrateur
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

type PendingConfirm = { kind: "reset" | "deactivate"; user: PlatformStaffUser };

type MutationCallbacks<T> = { onSuccess: (data: T) => void; onError: (err: unknown) => void };

function AdministratorRow({ user, readOnly, busy, error, onUnlock, onReactivate, onAskReset, onAskDeactivate }: {
  user: PlatformStaffUser;
  readOnly: boolean;
  busy: boolean;
  error: string | null;
  onUnlock: () => void;
  onReactivate: () => void;
  onAskReset: () => void;
  onAskDeactivate: () => void;
}) {
  return (
    <div className="flex flex-col gap-3 border-t border-border px-5 py-4 md:flex-row md:items-center md:justify-between">
      <div className="flex min-w-0 items-start gap-3">
        <PlatformInitials name={`${user.first_name} ${user.last_name}`} className="mt-0.5 h-9 w-9 rounded-full" />
        <div className="min-w-0">
          <p className="text-sm font-medium text-text">
            {user.first_name} {user.last_name}
          </p>
          <p className="break-all text-xs text-text-muted">{user.email}</p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            <Badge status={user.is_active ? "success" : "neutral"}>{user.is_active ? "Actif" : "Désactivé"}</Badge>
            {user.is_locked && (
              <Badge
                status="danger"
                title={user.locked_until ? `Verrouillé jusqu'au ${formatDateTime(user.locked_until)}` : undefined}
              >
                Verrouillé
              </Badge>
            )}
            {user.must_change_password && <Badge status="warning">Changement de mot de passe exigé</Badge>}
          </div>
          {user.is_locked && user.locked_until && (
            <p className="mt-1 text-xs text-text-subtle">
              Verrouillé jusqu'au {formatDateTime(user.locked_until)} ({user.failed_login_attempts} tentative
              {user.failed_login_attempts > 1 ? "s" : ""} échouée{user.failed_login_attempts > 1 ? "s" : ""})
            </p>
          )}
          {error && <p className="mt-1 text-xs text-danger">{error}</p>}
        </div>
      </div>
      {!readOnly && (
        <div className="flex flex-wrap items-center gap-2 pl-12 md:shrink-0 md:justify-end md:pl-0">
          {busy && <LoaderCircle size={14} className="animate-spin text-text-muted" />}
          {user.is_locked && (
            <Button size="sm" variant="secondary" onClick={onUnlock} disabled={busy}>
              Débloquer
            </Button>
          )}
          <Button size="sm" variant="ghost" onClick={onAskReset} disabled={busy}>
            Réinitialiser le mot de passe
          </Button>
          {user.is_active ? (
            <Button size="sm" variant="ghost" onClick={onAskDeactivate} disabled={busy}>
              Désactiver
            </Button>
          ) : (
            <Button size="sm" variant="secondary" onClick={onReactivate} disabled={busy}>
              Réactiver
            </Button>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Administrateurs d'une structure : création (mot de passe provisoire
 * affiché une seule fois), déblocage, réinitialisation du mot de passe,
 * désactivation / réactivation. Aucune action si la structure est archivée.
 */
export function StructureAdministratorsCard({ structureId, readOnly }: { structureId: number; readOnly: boolean }) {
  const administratorsQuery = usePlatformStructureAdministrators(structureId);
  const setActive = useSetPlatformAdministratorActive(structureId);
  const unlock = useUnlockPlatformUser(structureId);
  const resetPassword = useResetPlatformUserPassword(structureId);

  const [createOpen, setCreateOpen] = useState(false);
  const [revealed, setRevealed] = useState<RevealedPassword | null>(null);
  const [pendingConfirm, setPendingConfirm] = useState<PendingConfirm | null>(null);
  const [rowErrors, setRowErrors] = useState<Record<number, string>>({});
  const [busyUserId, setBusyUserId] = useState<number | null>(null);

  function setRowError(userId: number, message: string | null) {
    setRowErrors((current) => {
      const next = { ...current };
      if (message) next[userId] = message;
      else delete next[userId];
      return next;
    });
  }

  /** Exécute une mutation sur une ligne : indicateur et erreur restent propres à cette ligne. */
  function run<T>(
    userId: number,
    action: (callbacks: MutationCallbacks<T>) => void,
    onSuccess?: (data: T) => void,
  ) {
    setRowError(userId, null);
    setBusyUserId(userId);
    action({
      onSuccess: (data) => {
        setBusyUserId(null);
        onSuccess?.(data);
      },
      onError: (err) => {
        setBusyUserId(null);
        setRowError(userId, apiErrorMessage(err));
      },
    });
  }

  function handleUnlock(user: PlatformStaffUser) {
    run(user.id, (callbacks) => unlock.mutate(user.id, callbacks));
  }

  function handleReactivate(user: PlatformStaffUser) {
    run(user.id, (callbacks) => setActive.mutate({ userId: user.id, active: true }, callbacks));
  }

  function handleConfirm() {
    if (!pendingConfirm) return;
    const { kind, user } = pendingConfirm;
    setPendingConfirm(null);
    if (kind === "deactivate") {
      run(user.id, (callbacks) => setActive.mutate({ userId: user.id, active: false }, callbacks));
    } else {
      run<PlatformStaffUserWithPassword>(
        user.id,
        (callbacks) => resetPassword.mutate(user.id, callbacks),
        (result) =>
          setRevealed({ title: "Mot de passe réinitialisé", user: result.data, password: result.generated_password }),
      );
    }
  }

  const confirmUserName = pendingConfirm ? `${pendingConfirm.user.first_name} ${pendingConfirm.user.last_name}` : "";

  return (
    <Card>
      <CardHeader className="flex-wrap gap-2">
        <div className="flex items-center gap-2">
          <CardTitle>Administrateurs</CardTitle>
          {administratorsQuery.data && administratorsQuery.data.length > 0 && (
            <Badge dot={false}>{administratorsQuery.data.length}</Badge>
          )}
        </div>
        {!readOnly && (
          <Button size="sm" variant="secondary" onClick={() => setCreateOpen(true)}>
            <Plus size={14} />
            Nouvel administrateur
          </Button>
        )}
      </CardHeader>
      <CardContent className="p-0">
        {administratorsQuery.isLoading ? (
          <div className="p-5">
            <TableSkeleton columns={2} />
          </div>
        ) : administratorsQuery.isError ? (
          <div className="p-5">
            <ErrorState
              message={apiErrorMessage(administratorsQuery.error)}
              onRetry={() => administratorsQuery.refetch()}
            />
          </div>
        ) : !administratorsQuery.data || administratorsQuery.data.length === 0 ? (
          <EmptyState
            icon={UserCog}
            title="Aucun administrateur"
            description={readOnly ? undefined : "Créez un administrateur pour redonner la main à la structure."}
            className="m-4 py-10"
          />
        ) : (
          <div>
            {administratorsQuery.data.map((user) => (
              <AdministratorRow
                key={user.id}
                user={user}
                readOnly={readOnly}
                busy={busyUserId === user.id}
                error={rowErrors[user.id] ?? null}
                onUnlock={() => handleUnlock(user)}
                onReactivate={() => handleReactivate(user)}
                onAskReset={() => setPendingConfirm({ kind: "reset", user })}
                onAskDeactivate={() => setPendingConfirm({ kind: "deactivate", user })}
              />
            ))}
          </div>
        )}
        <p className="border-t border-border bg-surface-hover/20 px-5 py-3 text-xs text-text-subtle">
          Les patients et les prescripteurs réinitialisent eux-mêmes leur mot de passe via le lien « Mot de passe
          oublié » de leur portail.
        </p>
      </CardContent>

      {!readOnly && (
        <CreateAdministratorDialog
          structureId={structureId}
          open={createOpen}
          onOpenChange={setCreateOpen}
          onCreated={setRevealed}
        />
      )}
      <ConfirmDialog
        open={pendingConfirm !== null}
        onOpenChange={(open) => !open && setPendingConfirm(null)}
        title={
          pendingConfirm?.kind === "reset"
            ? `Réinitialiser le mot de passe de ${confirmUserName} ?`
            : `Désactiver ${confirmUserName} ?`
        }
        description={
          pendingConfirm?.kind === "reset"
            ? "Un nouveau mot de passe provisoire sera généré et affiché une seule fois. Toutes les sessions ouvertes de ce compte seront fermées, le compte sera débloqué et un changement de mot de passe sera exigé à la prochaine connexion."
            : "Le compte ne pourra plus se connecter et ses sessions ouvertes seront fermées immédiatement. Aucune donnée n'est supprimée : vous pourrez le réactiver à tout moment."
        }
        confirmLabel={pendingConfirm?.kind === "reset" ? "Réinitialiser" : "Désactiver"}
        onConfirm={handleConfirm}
      />
      <PasswordRevealDialog revealed={revealed} onAcknowledge={() => setRevealed(null)} />
    </Card>
  );
}
