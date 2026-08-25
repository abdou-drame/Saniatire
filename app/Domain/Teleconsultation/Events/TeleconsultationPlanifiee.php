<?php

namespace App\Domain\Teleconsultation\Events;

use App\Domain\Teleconsultation\Models\Teleconsultation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeleconsultationPlanifiee
{
    use Dispatchable, SerializesModels;

    public function __construct(public Teleconsultation $teleconsultation)
    {
    }
}
