<?php

namespace App\Domain\Referral\Events;

use App\Domain\Referral\Models\PatientReferral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferencementAccepte
{
    use Dispatchable, SerializesModels;

    public function __construct(public PatientReferral $referral)
    {
    }
}
