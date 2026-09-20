import { LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useCreateEmployeeProfile, useUpdateEmployeeProfile } from "@/hooks/use-employee-profiles";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { STATUT_EMPLOI_LABEL } from "@/pages/personnel/personnel-status";
import type { EmployeeProfile, StatutEmploi } from "@/types/api";

const STATUT_EMPLOI_VALUES: StatutEmploi[] = ["actif", "en_conge", "suspendu", "termine"];

export interface EmployeeProfileFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present when editing an existing profile; absent/null when creating one. */
  profile?: EmployeeProfile | null;
  /**
   * Pré-sélectionne et verrouille l'utilisateur en mode création — utilisé
   * par le pont "Créer la fiche RH" depuis un compte utilisateur qui vient
   * d'être créé (écran Comptes utilisateurs). Ignoré en mode édition.
   */
  initialUserId?: number;
}

/**
 * Création/édition d'une fiche personnel. Le rattachement à un utilisateur
 * (user_id) est figé une fois la fiche créée — en édition le champ est
 * verrouillé et le nom résolu est affiché en lecture seule plutôt que
 * d'être renvoyé dans le payload de mise à jour.
 */
export function EmployeeProfileFormDialog({
  open,
  onOpenChange,
  profile = null,
  initialUserId,
}: EmployeeProfileFormDialogProps) {
  const isEditing = Boolean(profile);
  const isPreselected = !isEditing && initialUserId !== undefined;

  const { hasPermission } = useAuth();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));
  const createProfile = useCreateEmployeeProfile();
  const updateProfile = useUpdateEmployeeProfile();

  const [userId, setUserId] = useState<string>("");
  const [dateEmbauche, setDateEmbauche] = useState("");
  const [typeContrat, setTypeContrat] = useState("");
  const [statutEmploi, setStatutEmploi] = useState<StatutEmploi>("actif");
  const [qualification, setQualification] = useState("");
  const [numeroOrdre, setNumeroOrdre] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    if (profile) {
      setUserId(String(profile.user_id));
      setDateEmbauche(profile.date_embauche ?? "");
      setTypeContrat(profile.type_contrat ?? "");
      setStatutEmploi(profile.statut_emploi);
      setQualification(profile.qualification ?? "");
      setNumeroOrdre(profile.numero_ordre ?? "");
    } else {
      setUserId(initialUserId !== undefined ? String(initialUserId) : "");
      setDateEmbauche("");
      setTypeContrat("");
      setStatutEmploi("actif");
      setQualification("");
      setNumeroOrdre("");
    }
  }, [open, profile, initialUserId]);

  const canSubmit = isEditing || Boolean(userId);
  const isPending = createProfile.isPending || updateProfile.isPending;
  const resolvedUser = profile
    ? usersDirectory.byId.get(profile.user_id)
    : isPreselected
      ? usersDirectory.byId.get(initialUserId!)
      : undefined;

  function handleSubmit() {
    setSubmitError(null);
    setFieldErrors({});

    if (isEditing && profile) {
      updateProfile.mutate(
        {
          id: profile.id,
          date_embauche: dateEmbauche || undefined,
          type_contrat: typeContrat || undefined,
          statut_emploi: statutEmploi,
          qualification: qualification || undefined,
          numero_ordre: numeroOrdre || undefined,
        },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setSubmitError(apiErrorMessage(err)),
        },
      );
      return;
    }

    if (!userId) return;
    createProfile.mutate(
      {
        user_id: Number(userId),
        date_embauche: dateEmbauche || undefined,
        type_contrat: typeContrat || undefined,
        statut_emploi: statutEmploi,
        qualification: qualification || undefined,
        numero_ordre: numeroOrdre || undefined,
      },
      {
        onSuccess: () => onOpenChange(false),
        onError: (err) => setSubmitError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier la fiche personnel" : "Nouvelle fiche personnel"}</DialogTitle>
          <DialogDescription>
            {isEditing
              ? "Mettez à jour les informations RH de cet agent."
              : "Rattachez une fiche RH à un utilisateur existant."}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Utilisateur</Label>
            {isEditing || isPreselected ? (
              <p className="flex h-9 items-center rounded-md border border-border bg-surface-hover px-3 text-sm text-text-muted">
                {resolvedUser
                  ? `${resolvedUser.first_name} ${resolvedUser.last_name}`
                  : `Utilisateur #${profile?.user_id ?? initialUserId}`}
              </p>
            ) : (
              <>
                <Select
                  value={userId}
                  onChange={(e) => setUserId(e.target.value)}
                  disabled={usersDirectory.isLoading}
                >
                  <option value="">Sélectionner un utilisateur</option>
                  {(usersDirectory.data ?? []).map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.first_name} {user.last_name}
                    </option>
                  ))}
                </Select>
                <FieldError>{fieldErrors.user_id}</FieldError>
              </>
            )}
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Date d'embauche</Label>
              <Input type="date" value={dateEmbauche} onChange={(e) => setDateEmbauche(e.target.value)} />
              <FieldError>{fieldErrors.date_embauche}</FieldError>
            </div>
            <div>
              <Label>Type de contrat</Label>
              <Input
                type="text"
                placeholder="CDI, CDD…"
                value={typeContrat}
                onChange={(e) => setTypeContrat(e.target.value)}
              />
              <FieldError>{fieldErrors.type_contrat}</FieldError>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Statut d'emploi</Label>
              <Select value={statutEmploi} onChange={(e) => setStatutEmploi(e.target.value as StatutEmploi)}>
                {STATUT_EMPLOI_VALUES.map((value) => (
                  <option key={value} value={value}>
                    {STATUT_EMPLOI_LABEL[value]}
                  </option>
                ))}
              </Select>
              <FieldError>{fieldErrors.statut_emploi}</FieldError>
            </div>
            <div>
              <Label>Qualification</Label>
              <Input
                type="text"
                placeholder="Médecin, infirmier…"
                value={qualification}
                onChange={(e) => setQualification(e.target.value)}
              />
              <FieldError>{fieldErrors.qualification}</FieldError>
            </div>
          </div>

          <div>
            <Label>Numéro d'ordre</Label>
            <Input
              type="text"
              placeholder="Optionnel"
              value={numeroOrdre}
              onChange={(e) => setNumeroOrdre(e.target.value)}
            />
            <FieldError>{fieldErrors.numero_ordre}</FieldError>
          </div>

          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {submitError}
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || isPending}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            {isEditing ? "Enregistrer" : "Créer la fiche"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
