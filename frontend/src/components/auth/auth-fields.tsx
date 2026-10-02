import { Eye, EyeOff, Lock, Mail, type LucideIcon } from "lucide-react";
import { useState, type InputHTMLAttributes, type ReactNode } from "react";
import { Link } from "react-router-dom";
import { Input } from "@/components/ui/input";

type FieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, "id"> & { id: string; label: string };

function FieldFrame({ id, label, icon: Icon, children }: { id: string; label: string; icon: LucideIcon; children: ReactNode }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-xs font-medium text-text-muted">
        {label}
      </label>
      <div className="relative">
        <Icon size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
        {children}
      </div>
    </div>
  );
}

export function EmailField({ id, label, ...props }: FieldProps) {
  return (
    <FieldFrame id={id} label={label} icon={Mail}>
      <Input id={id} type="email" className="h-10 bg-bg pl-9" {...props} />
    </FieldFrame>
  );
}

export function PasswordField({ id, label, ...props }: FieldProps) {
  const [visible, setVisible] = useState(false);
  return (
    <FieldFrame id={id} label={label} icon={Lock}>
      <Input id={id} type={visible ? "text" : "password"} className="h-10 bg-bg pl-9 pr-10" {...props} />
      <button
        type="button"
        onClick={() => setVisible((v) => !v)}
        aria-label={visible ? "Masquer le mot de passe" : "Afficher le mot de passe"}
        aria-pressed={visible}
        className="absolute right-1 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-text-subtle transition-colors hover:text-text focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-accent"
      >
        {visible ? <EyeOff size={16} /> : <Eye size={16} />}
      </button>
    </FieldFrame>
  );
}

export function AuthNotice({ children }: { children: ReactNode }) {
  return (
    <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">{children}</p>
  );
}

/** Ligne de séparation suivie des liens de bascule vers les autres portails. */
export function AuthSwitchLinks({ links }: { links: { to: string; label: string }[] }) {
  return (
    <div className="mt-6 flex flex-col items-center gap-2 border-t border-border pt-5 text-sm">
      {links.map((link) => (
        <Link key={link.to} to={link.to} className="text-text-muted transition-colors hover:text-text">
          {link.label}
        </Link>
      ))}
    </div>
  );
}
