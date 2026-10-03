<?php

namespace App\Domain\Platform;

use App\Domain\Platform\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

/**
 * État d'abonnement d'une structure, toujours calculé à la lecture (aucune
 * tâche planifiée, aucun champ à tenir à jour). Règle unique, utilisée à la
 * fois par EnsureSubscriptionWritable (refus réel des écritures) et par
 * /auth/me (affichage frontend) — le frontend n'en recalcule rien.
 *
 * - Période courante = dernière période déjà commencée (starts_at ≤ aujourd'hui).
 * - Aucune période : structure antérieure aux abonnements, rien ne change
 *   pour elle (état normal, pas de bannière).
 * - Période suspendue ou résiliée : lecture seule immédiate, sans grâce.
 * - Sinon : normal jusqu'à ends_at inclus, puis GRACE_DAYS jours de grâce
 *   (bannière pour administrateur/direction), puis lecture seule jusqu'à
 *   l'enregistrement d'une nouvelle période.
 */
class SubscriptionState
{
    public const ACTIVE = 'essai_ou_actif';

    public const GRACE = 'en_grace';

    public const READ_ONLY = 'lecture_seule';

    public const GRACE_DAYS = 7;

    /** Rôles qui voient la bannière pendant la grâce. */
    public const ALERTED_ROLES = ['administrateur', 'direction'];

    private function __construct(
        public readonly string $state,
        public readonly ?Subscription $subscription,
    ) {}

    public static function forStructure(?int $structureId, ?Carbon $today = null): self
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $current = $structureId ? Subscription::query()
            ->with('plan')
            ->where('structure_id', $structureId)
            ->whereDate('starts_at', '<=', $today)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first() : null;

        if (! $current) {
            return new self(self::ACTIVE, null);
        }

        if (in_array($current->status, ['suspendue', 'resiliee'], true)) {
            return new self(self::READ_ONLY, $current);
        }

        if ($today->lte($current->ends_at)) {
            return new self(self::ACTIVE, $current);
        }

        if ($today->lte(self::graceEndsAt($current))) {
            return new self(self::GRACE, $current);
        }

        return new self(self::READ_ONLY, $current);
    }

    public static function graceEndsAt(Subscription $subscription): Carbon
    {
        return $subscription->ends_at->copy()->addDays(self::GRACE_DAYS);
    }

    public function isReadOnly(): bool
    {
        return $this->state === self::READ_ONLY;
    }

    public function readOnlyMessage(): string
    {
        if ($this->subscription && in_array($this->subscription->status, ['suspendue', 'resiliee'], true)) {
            return "L'abonnement de cette structure est {$this->subscription->status} : la structure est en lecture seule. Contactez l'administration de la plateforme.";
        }

        return "L'abonnement de cette structure a expiré et le délai de grâce est dépassé : la structure est en lecture seule jusqu'à son renouvellement. Contactez l'administration de la plateforme.";
    }

    /**
     * Forme exposée par /auth/me. `alert` indique au frontend s'il doit
     * afficher la bannière pour CET utilisateur : toujours en lecture
     * seule, et seulement pour les rôles administratifs pendant la grâce.
     */
    public function toArrayFor(User $user): array
    {
        $alert = match ($this->state) {
            self::READ_ONLY => true,
            self::GRACE => $user->hasAnyRole(self::ALERTED_ROLES),
            default => false,
        };

        return [
            'state' => $this->state,
            'read_only' => $this->isReadOnly(),
            'alert' => $alert,
            'message' => $alert ? $this->alertMessage() : null,
            'plan_name' => $this->subscription?->plan?->name,
            'status' => $this->subscription?->status,
            'ends_at' => $this->subscription?->ends_at?->toDateString(),
            'grace_ends_at' => $this->subscription ? self::graceEndsAt($this->subscription)->toDateString() : null,
            // Bouton « Payer maintenant » : décidé ici, pas par le frontend.
            'can_pay_online' => $alert
                && $user->structure_id !== null
                && $user->hasAnyRole(self::ALERTED_ROLES)
                && ! isset(SubscriptionRenewal::selfServiceOffer($user->structure_id)['error']),
        ];
    }

    private function alertMessage(): string
    {
        if ($this->isReadOnly()) {
            return $this->readOnlyMessage();
        }

        $limit = self::graceEndsAt($this->subscription)->format('d/m/Y');

        return "L'abonnement de votre structure a expiré le {$this->subscription->ends_at->format('d/m/Y')}. Sans renouvellement, la structure passera en lecture seule après le {$limit}.";
    }
}
