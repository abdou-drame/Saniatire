import { ShieldCheck } from "lucide-react";

/**
 * Moyens de paiement acceptés. Marques reproduites en texte aux couleurs
 * des opérateurs : remplacer par les logos officiels du kit marchand
 * DexPay dès qu'ils sont fournis.
 */
export function PaymentMethods() {
  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-text-muted">
      <span className="inline-flex items-center gap-1.5">
        <ShieldCheck size={14} className="text-success" />
        Paiement sécurisé via DexPay
      </span>
      <span aria-label="Wave" className="rounded-md bg-[#1DC8F2] px-2 py-0.5 font-semibold text-white">
        Wave
      </span>
      <span aria-label="Orange Money" className="rounded-md bg-[#FF7900] px-2 py-0.5 font-semibold text-white">
        Orange Money
      </span>
    </div>
  );
}
