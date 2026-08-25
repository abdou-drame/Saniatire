<?php

namespace App\Domain\Laboratoire\Events;

use App\Domain\Laboratoire\Models\LabResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultatLaboratoireTransmis
{
    use Dispatchable, SerializesModels;

    public function __construct(public LabResult $labResult)
    {
    }
}
