<?php

namespace App\Domain\Shared\Auth\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PortailActivationDemandee
{
    use Dispatchable, SerializesModels;

    /**
     * $token est le jeton en clair — jamais persisté nulle part (seul son
     * hash SHA-256 l'est, dans portal_activations) — porté uniquement le
     * temps de construire le lien d'activation envoyé par notification.
     */
    public function __construct(
        public Model $activatable,
        public string $token,
    ) {}
}
