<?php

namespace App\Domain\Appointment\Events;

use App\Domain\Appointment\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched only — no listener yet. Will be wired to the notifications
 * module (cahier des charges §69) to trigger SMS/email confirmations.
 */
class RendezVousAnnule
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }
}
