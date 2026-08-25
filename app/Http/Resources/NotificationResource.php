<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'type_evenement' => $this->type_evenement,
            'canal' => $this->canal,
            'sujet_final' => $this->sujet_final,
            'contenu_final' => $this->contenu_final,
            'destinataire' => $this->destinataire,
            'statut' => $this->statut,
            'scheduled_for' => $this->scheduled_for,
            'envoye_at' => $this->envoye_at,
            'erreur' => $this->erreur,
            'created_at' => $this->created_at,
        ];
    }
}
