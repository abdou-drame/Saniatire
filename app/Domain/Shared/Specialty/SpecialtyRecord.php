<?php

namespace App\Domain\Shared\Specialty;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contract implemented by every specialty's root model (MaternityRecord,
 * DentalChart, DialysisProgram, ...). Lets cross-cutting code (timeline,
 * stats) treat specialties uniformly despite each having its own table
 * shape — see SpecialtyRegistry for the type => model/resource mapping.
 */
interface SpecialtyRecord
{
    public static function specialtyType(): string;

    public function consultation(): BelongsTo;
}
