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
import { useCreateUserAccount, useUpdateUserAccount, useUserRoles } from "@/hooks/use-user-accounts";
import { useSites } from "@/hooks/use-sites";
import { roleLabel } from "@/config/role-labels";
import { apiErrorMessage } from "@/lib/api-error";
import type { UserAccount } from "@/types/api";

export interface UserAccountFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present when editing an existing account; absent/null when creating one. */
  account?: UserAccount | null;
  /**
   * Invoqué juste après une création réussie, si l'utilisateur choisit
   * "Créer la fiche RH" — le parent (écran Comptes utilisateurs) est
   * responsable d'ouvrir EmployeeProfileFormDialog avec ce user_id.
   */
  onRequestEmployeeProfile?: (userId: number) => void;
}

/**
 * Création/édition d'un compte personnel (User, distinct de la fiche RH
 * EmployeeProfile — cf. use-employee-profiles.ts). Mécanisme de mot de
 * passe initial retenu après discussion explicite : UserController::store
 * exige déjà `password` (required|min:8) à la création — ce formulaire
 * reprend exactement cette exigence, sans invitation par email ni token
 * (aucun de ces mécanismes n'existe côté backend pour les comptes
 * personnel, contrairement aux portails patient/prescripteur).
 */
export function UserAccountFormDialog({
  open,
  onOpenChange,
  account = null,
  onRequestEmployeeProfile,
}: UserAccountFormDialogProps) {
  const isEditing = Boolean(account);

  const rolesQuery = useUserRoles();
  const sitesQuery = useSites();
  const createAccount = useCreateUserAccount();
  const updateAccount = useUpdateUserAccount();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("");
  const [siteIds, setSiteIds] = useState<number[]>([]);
  const [isActive, setIsActive] = useState(true);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [createdAccount, setCreatedAccount] = useState<UserAccount | null>(null);
  const [createdPassword, setCreatedPassword] = useState("");

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    setCreatedAccount(null);
    setCreatedPassword("");
    if (account) {
      setFirstName(account.first_name);
      setLastName(account.last_name);
      setEmail(account.email);
      setPhone(account.phone ?? "");
      setPassword("");
      setRole(account.roles[0] ?? "");
      setSiteIds(account.sites.map((s) => s.id));
      setIsActive(account.is_active);
    } else {
      setFirstName("");
      setLastName("");
      setEmail("");
      setPhone("");
      setPassword("");
      setRole("");
      setSiteIds([]);
      setIsActive(true);
    }
  }, [open, account]);

  const isPending = createAccount.isPending || updateAccount.isPending;

  function toggleSite(siteId: number) {
    setSiteIds((prev) => (prev.includes(siteId) ? prev.filter((id) => id !== siteId) : [...prev, siteId]));
  }

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!firstName.trim()) errors.first_name = "Le prénom est obligatoire.";
    if (!lastName.trim()) errors.last_name = "Le nom est obligatoire.";
    if (!email.trim()) errors.email = "L'email est obligatoire.";
    if (!role) errors.role = "Sélectionnez un rôle.";
    if (siteIds.length === 0) errors.site_ids = "Sélectionnez au moins un site.";
    if (!isEditing && password.trim().length < 8) {
      errors.password = "Le mot de passe initial doit contenir au moins 8 caractères.";
    }
    if (isEditing && password.trim().length > 0 && password.trim().length < 8) {
      errors.password = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  function handleSubmit() {
    setSubmitError(null);
    if (!validate()) return;

    const basePayload = {
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim(),
      phone: phone.trim() || undefined,
      role,
      site_ids: siteIds,
      is_active: isActive,
    };

    if (isEditing && account) {
      updateAccount.mutate(
        { id: account.id, ...basePayload, ...(password.trim() ? { password: password.trim() } : {}) },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setSubmitError(apiErrorMessage(err)),
        },
      );
      return;
    }

    createAccount.mutate(
      { ...basePayload, password: password.trim() },
      {
        onSuccess: (created) => {
          setCreatedAccount(created);
          setCreatedPassword(password.trim());
        },
        onError: (err) => setSubmitError(apiErrorMessage(err)),
      },
    );
  }

  if (createdAccount) {
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Compte créé</DialogTitle>
            <DialogDescription>
              {createdAccount.first_name} {createdAccount.last_name} — {createdAccount.email}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-sm text-text">
              Mot de passe initial : <span className="font-tabular font-semibold">{createdPassword}</span>
              <br />
              <span className="text-xs text-text-muted">
                Communiquez-le à l'utilisateur par un canal sécurisé — il ne sera plus affiché ensuite.
              </span>
            </p>
          </div>
          <DialogFooter>
            <Button variant="secondary" onClick={() => onOpenChange(false)}>
              Fermer
            </Button>
            {onRequestEmployeeProfile && (
              <Button
                onClick={() => {
                  onRequestEmployeeProfile(createdAccount.id);
                  onOpenChange(false);
                }}
              >
                Créer la fiche RH
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le compte" : "Nouveau compte utilisateur"}</DialogTitle>
          <DialogDescription>
            {isEditing
              ? "Rôle, sites, statut et mot de passe de ce compte."
              : "Crée un compte de connexion personnel (distinct de la fiche RH)."}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Prénom</Label>
              <Input value={firstName} onChange={(e) => setFirstName(e.target.value)} />
              <FieldError>{fieldErrors.first_name}</FieldError>
            </div>
            <div>
              <Label>Nom</Label>
              <Input value={lastName} onChange={(e) => setLastName(e.target.value)} />
              <FieldError>{fieldErrors.last_name}</FieldError>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              <FieldError>{fieldErrors.email}</FieldError>
            </div>
            <div>
              <Label>Téléphone</Label>
              <Input value={phone} onChange={(e) => setPhone(e.target.value)} />
              <FieldError>{fieldErrors.phone}</FieldError>
            </div>
          </div>

          <div>
            <Label>{isEditing ? "Nouveau mot de passe (optionnel)" : "Mot de passe initial"}</Label>
            <Input
              type="text"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder={isEditing ? "Laisser vide pour ne pas modifier" : "Au moins 8 caractères"}
            />
            <FieldError>{fieldErrors.password}</FieldError>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Rôle</Label>
              <Select value={role} onChange={(e) => setRole(e.target.value)} disabled={rolesQuery.isLoading}>
                <option value="">Sélectionner...</option>
                {(rolesQuery.data ?? []).map((r) => (
                  <option key={r} value={r}>
                    {roleLabel(r)}
                  </option>
                ))}
              </Select>
              <FieldError>{fieldErrors.role}</FieldError>
            </div>
            <div>
              <Label>Statut</Label>
              <label className="flex h-9 items-center gap-2 text-sm font-normal text-text">
                <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                Compte actif
              </label>
            </div>
          </div>

          <div>
            <Label>Sites</Label>
            <div className="flex flex-wrap gap-x-4 gap-y-2 rounded-md border border-border p-3">
              {sitesQuery.isLoading && <p className="text-xs text-text-muted">Chargement des sites...</p>}
              {(sitesQuery.data ?? []).map((site) => (
                <label key={site.id} className="flex items-center gap-2 text-sm font-normal text-text">
                  <input
                    type="checkbox"
                    checked={siteIds.includes(site.id)}
                    onChange={() => toggleSite(site.id)}
                  />
                  {site.name}
                </label>
              ))}
              {!sitesQuery.isLoading && (sitesQuery.data ?? []).length === 0 && (
                <p className="text-xs text-text-muted">Aucun site disponible.</p>
              )}
            </div>
            <FieldError>{fieldErrors.site_ids}</FieldError>
          </div>
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            {isEditing ? "Enregistrer" : "Créer le compte"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
