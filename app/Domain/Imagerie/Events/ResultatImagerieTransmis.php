<?php

namespace App\Domain\Imagerie\Events;

use App\Domain\Imagerie\Models\ImagingStudy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultatImagerieTransmis
{
    use Dispatchable, SerializesModels;

    public function __construct(public ImagingStudy $imagingStudy)
    {
    }
}
