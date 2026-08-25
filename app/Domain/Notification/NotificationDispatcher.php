<?php

namespace App\Domain\Notification;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Shared\Tenancy\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Point d'entrée UNIQUE du système de notification. Le reste de
 * l'application (listeners d'événements, contrôleurs) ne connaît que
 * send() — jamais un canal, un template ou un driver directement. Pour
 * brancher un nouveau type d'événement : appeler send() avec un nouveau
 * $typeEvenement depuis un listener, et fournir un template (seed ou
 * admin) pour ce type — zéro changement ici.
 */
class NotificationDispatcher
{
    /**
     * @param  Model  $notifiable  User ou Patient — doit exposer structure_id, et selon le canal email/phone.
     * @param  array<string,mixed>  $variables  Substituées dans le template ({variable}).
     */
    public function send(Model $notifiable, string $typeEvenement, array $variables, ?CarbonInterface $scheduledFor = null): void
    {
        $structureId = $notifiable->structure_id;

        foreach ($this->resolveChannels($notifiable) as $canal) {
            $template = $this->resolveTemplate($structureId, $typeEvenement, $canal);

            if (! $template) {
                Log::warning("Aucun template de notification pour {$typeEvenement}/{$canal} (structure {$structureId}) — notification ignorée.");

                continue;
            }

            $channelClass = config('notifications.channels')[$canal] ?? null;

            if (! $channelClass) {
                Log::warning("Canal {$canal} non configuré — notification ignorée.");

                continue;
            }

            $destinataire = app($channelClass)->resolveDestinataire($notifiable);

            if (! $destinataire) {
                Log::warning("Notifiable sans destinataire pour le canal {$canal} — notification ignorée.", [
                    'notifiable_type' => $notifiable::class,
                    'notifiable_id' => $notifiable->getKey(),
                ]);

                continue;
            }

            $rendered = $template->render($variables);

            $notification = Notification::create([
                'structure_id' => $structureId,
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->getKey(),
                'type_evenement' => $typeEvenement,
                'canal' => $canal,
                'sujet_final' => $rendered['sujet'],
                'contenu_final' => $rendered['contenu'],
                'destinataire' => $destinataire,
                'statut' => 'en_attente',
                'scheduled_for' => $scheduledFor,
            ]);

            if ($notification->isDue()) {
                $this->attemptSend($notification);
            }
        }
    }

    public function attemptSend(Notification $notification): void
    {
        $channelClass = config('notifications.channels')[$notification->canal] ?? null;

        if (! $channelClass) {
            $notification->update(['statut' => 'echouee', 'erreur' => "Canal {$notification->canal} non configuré."]);

            return;
        }

        try {
            app($channelClass)->send($notification);

            $notification->update(['statut' => 'envoyee', 'envoye_at' => now()]);
        } catch (\Throwable $e) {
            $notification->update(['statut' => 'echouee', 'erreur' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int,string>
     */
    private function resolveChannels(Model $notifiable): array
    {
        $preference = NotificationPreference::withoutGlobalScope(TenantScope::class)
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', $notifiable->getKey())
            ->first();

        return $preference?->canaux ?? config('notifications.default_channels');
    }

    /**
     * Template de la structure si elle en a défini un pour ce couple
     * (type_evenement, canal), sinon le template par défaut global
     * (structure_id null). Deux requêtes distinctes plutôt qu'un
     * orWhereNull : évite toute divergence d'ordre NULLS FIRST/LAST entre
     * Postgres (prod) et SQLite (tests).
     */
    private function resolveTemplate(?int $structureId, string $typeEvenement, string $canal): ?NotificationTemplate
    {
        $query = fn () => NotificationTemplate::withoutGlobalScope(TenantScope::class)
            ->where('type_evenement', $typeEvenement)
            ->where('canal', $canal)
            ->where('actif', true);

        if ($structureId) {
            $specific = $query()->where('structure_id', $structureId)->first();

            if ($specific) {
                return $specific;
            }
        }

        return $query()->whereNull('structure_id')->first();
    }
}
