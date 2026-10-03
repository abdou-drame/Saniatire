import { CreditCard, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useSubscriptionSelfCheckout } from "@/hooks/use-subscription-payment";
import { apiErrorMessage } from "@/lib/api-error";
import type { SubscriptionStatus } from "@/types/api";

/**
 * Bannière d'abonnement : texte, visibilité et bouton « Payer maintenant »
 * décidés par le backend (/auth/me) ; la lecture seule elle-même est
 * appliquée côté serveur (423). Le paiement redirige vers la page DexPay.
 */
export function SubscriptionBanner({ subscription }: { subscription: SubscriptionStatus | undefined }) {
  const checkout = useSubscriptionSelfCheckout();
  const [error, setError] = useState<string | null>(null);

  if (!subscription?.alert || !subscription.message) return null;

  function handlePay() {
    setError(null);
    checkout.mutate(undefined, {
      onSuccess: (transaction) => {
        if (transaction.payment_url) {
          window.location.assign(transaction.payment_url);
        } else {
          setError("Le lien de paiement n'a pas pu être obtenu. Réessayez dans un instant.");
        }
      },
      onError: (err) => setError(apiErrorMessage(err)),
    });
  }

  return (
    <div
      role="alert"
      className={
        subscription.read_only
          ? "flex flex-wrap items-center justify-between gap-2 border-b border-danger/30 bg-danger/10 px-6 py-2 text-sm text-danger"
          : "flex flex-wrap items-center justify-between gap-2 border-b border-warning/30 bg-warning/10 px-6 py-2 text-sm text-warning"
      }
    >
      <div className="min-w-0">
        <p>{subscription.message}</p>
        {error && <p className="mt-0.5 text-xs font-medium">{error}</p>}
      </div>
      {subscription.can_pay_online && (
        <Button size="sm" onClick={handlePay} disabled={checkout.isPending || checkout.isSuccess}>
          {checkout.isPending || checkout.isSuccess ? (
            <LoaderCircle size={14} className="animate-spin" />
          ) : (
            <CreditCard size={14} />
          )}
          Payer maintenant
        </Button>
      )}
    </div>
  );
}
