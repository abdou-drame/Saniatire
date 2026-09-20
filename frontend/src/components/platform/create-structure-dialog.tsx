import { AlertTriangle, Check, Copy, LoaderCircle } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useCreatePlatformStructure } from "@/hooks/use-platform-structures";
import { apiErrorMessage } from "@/lib/api-error";
import type { CreateStructureResponse, StructureType } from "@/types/api";

const STRUCTURE_TYPE_OPTIONS: { value: StructureType; label: string }[] = [
  { value: "cabinet", label: "Cabinet" },
  { value: "centre_specialise", label: "Centre spécialisé" },
  { value: "laboratoire", label: "Laboratoire" },
  { value: "imagerie", label: "Imagerie" },
  { value: "clinique", label: "Clinique" },
  { value: "polyclinique", label: "Polyclinique" },
  { value: "groupe_sante", label: "Groupe santé" },
];

const EMPTY_FORM = {
  code: "",
  legal_name: "",
  trade_name: "",
  type: "cabinet" as StructureType,
  city: "",
  phone: "",
  email: "",
  admin_first_name: "",
  admin_last_name: "",
  admin_email: "",
};

export interface CreateStructureDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function CreateStructureDialog({ open, onOpenChange }: CreateStructureDialogProps) {
  const [step, setStep] = useState<"form" | "reveal">("form");
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<CreateStructureResponse | null>(null);
  const [copied, setCopied] = useState(false);
  const mutation = useCreatePlatformStructure();

  function handleOpenChange(next: boolean) {
    // Étape reveal : impossible de fermer sans passer par le bouton explicite
    // "J'ai noté le mot de passe" — évite une fermeture accidentelle (Échap,
    // clic sur l'overlay) qui ferait perdre un mot de passe jamais restitué.
    if (!next && step === "reveal") {
      return;
    }
    onOpenChange(next);
    if (!next) {
      setStep("form");
      setForm(EMPTY_FORM);
      setError(null);
      setResult(null);
      setCopied(false);
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    mutation.mutate(
      {
        code: form.code,
        legal_name: form.legal_name,
        trade_name: form.trade_name || undefined,
        type: form.type,
        city: form.city || undefined,
        phone: form.phone || undefined,
        email: form.email || undefined,
        admin_first_name: form.admin_first_name,
        admin_last_name: form.admin_last_name,
        admin_email: form.admin_email,
      },
      {
        onSuccess: (data) => {
          setResult(data);
          setStep("reveal");
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  async function handleCopy() {
    if (!result) return;
    try {
      await navigator.clipboard.writeText(result.admin_generated_password);
      setCopied(true);
    } catch {
      // Presse-papiers indisponible (contexte non sécurisé, permission refusée) :
      // le mot de passe reste affiché à l'écran, pas de dégradation fonctionnelle.
    }
  }

  function handleAcknowledge() {
    onOpenChange(false);
    setStep("form");
    setForm(EMPTY_FORM);
    setError(null);
    setResult(null);
    setCopied(false);
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        {step === "form" ? (
          <>
            <DialogHeader>
              <DialogTitle>Nouvelle structure</DialogTitle>
              <DialogDescription>
                Crée la structure et son tout premier compte administrateur en une seule opération.
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label htmlFor="code">Code</Label>
                  <Input
                    id="code"
                    required
                    value={form.code}
                    onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                  />
                </div>
                <div>
                  <Label htmlFor="type">Type</Label>
                  <Select
                    id="type"
                    value={form.type}
                    onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as StructureType }))}
                  >
                    {STRUCTURE_TYPE_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </Select>
                </div>
              </div>

              <div>
                <Label htmlFor="legal_name">Raison sociale</Label>
                <Input
                  id="legal_name"
                  required
                  value={form.legal_name}
                  onChange={(e) => setForm((f) => ({ ...f, legal_name: e.target.value }))}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label htmlFor="trade_name">Nom commercial</Label>
                  <Input
                    id="trade_name"
                    value={form.trade_name}
                    onChange={(e) => setForm((f) => ({ ...f, trade_name: e.target.value }))}
                  />
                </div>
                <div>
                  <Label htmlFor="city">Ville</Label>
                  <Input
                    id="city"
                    value={form.city}
                    onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label htmlFor="phone">Téléphone</Label>
                  <Input
                    id="phone"
                    value={form.phone}
                    onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                  />
                </div>
                <div>
                  <Label htmlFor="email">E-mail</Label>
                  <Input
                    id="email"
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                  />
                </div>
              </div>

              <div className="border-t border-border pt-3">
                <p className="mb-3 text-xs font-medium text-text-muted">Premier administrateur de la structure</p>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <Label htmlFor="admin_first_name">Prénom</Label>
                    <Input
                      id="admin_first_name"
                      required
                      value={form.admin_first_name}
                      onChange={(e) => setForm((f) => ({ ...f, admin_first_name: e.target.value }))}
                    />
                  </div>
                  <div>
                    <Label htmlFor="admin_last_name">Nom</Label>
                    <Input
                      id="admin_last_name"
                      required
                      value={form.admin_last_name}
                      onChange={(e) => setForm((f) => ({ ...f, admin_last_name: e.target.value }))}
                    />
                  </div>
                </div>
                <div className="mt-3">
                  <Label htmlFor="admin_email">E-mail de connexion</Label>
                  <Input
                    id="admin_email"
                    type="email"
                    required
                    value={form.admin_email}
                    onChange={(e) => setForm((f) => ({ ...f, admin_email: e.target.value }))}
                  />
                </div>
              </div>

              {error && <FieldError>{error}</FieldError>}

              <DialogFooter>
                <Button type="button" variant="secondary" onClick={() => handleOpenChange(false)}>
                  Annuler
                </Button>
                <Button type="submit" disabled={mutation.isPending}>
                  {mutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
                  Créer la structure
                </Button>
              </DialogFooter>
            </form>
          </>
        ) : (
          result && (
            <>
              <DialogHeader>
                <DialogTitle>Structure créée</DialogTitle>
                <DialogDescription>
                  {result.structure.legal_name} — administrateur {result.admin.first_name} {result.admin.last_name} (
                  {result.admin.email})
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-3 rounded-md border border-warning/30 bg-warning/10 p-3">
                <p className="flex items-center gap-1.5 text-xs font-medium text-warning">
                  <AlertTriangle size={14} />
                  Mot de passe affiché une seule fois — notez-le et transmettez-le de façon sécurisée à
                  l'administrateur. Il ne sera plus jamais consultable après la fermeture de cette fenêtre.
                </p>
                <div className="flex items-center gap-2">
                  <code className="flex-1 rounded border border-border bg-bg px-3 py-1.5 text-sm tracking-wider text-text">
                    {result.admin_generated_password}
                  </code>
                  <Button type="button" variant="secondary" size="sm" onClick={handleCopy}>
                    {copied ? <Check size={14} /> : <Copy size={14} />}
                    {copied ? "Copié" : "Copier"}
                  </Button>
                </div>
                <p className="text-xs text-warning">
                  Un changement de mot de passe sera exigé de l'administrateur dès sa première connexion.
                </p>
              </div>

              <DialogFooter>
                <Button type="button" onClick={handleAcknowledge}>
                  J'ai noté le mot de passe
                </Button>
              </DialogFooter>
            </>
          )
        )}
      </DialogContent>
    </Dialog>
  );
}
