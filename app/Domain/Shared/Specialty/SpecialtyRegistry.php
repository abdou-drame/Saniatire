<?php

namespace App\Domain\Shared\Specialty;

use App\Domain\Cardiologie\Models\CardioRecord;
use App\Domain\Dentaire\Models\DentalChart;
use App\Domain\Dialyse\Models\DialysisProgram;
use App\Domain\Kinesitherapie\Models\KineProgram;
use App\Domain\Maternite\Models\MaternityRecord;
use App\Domain\MedecineTravail\Models\OccupationalHealthRecord;
use App\Domain\Oncologie\Models\OncoRecord;
use App\Domain\Ophtalmo\Models\OphtalmoRecord;
use App\Domain\Pediatrie\Models\PediatricRecord;
use App\Domain\Pma\Models\PmaRecord;
use App\Domain\SanteMentale\Models\MentalHealthRecord;
use App\Domain\SoinsDomicile\Models\HomeCareRecord;
use App\Http\Resources\CardioRecordResource;
use App\Http\Resources\DentalChartResource;
use App\Http\Resources\DialysisProgramResource;
use App\Http\Resources\HomeCareRecordResource;
use App\Http\Resources\KineProgramResource;
use App\Http\Resources\MaternityRecordResource;
use App\Http\Resources\MentalHealthRecordResource;
use App\Http\Resources\OccupationalHealthRecordResource;
use App\Http\Resources\OncoRecordResource;
use App\Http\Resources\OphtalmoRecordResource;
use App\Http\Resources\PediatricRecordResource;
use App\Http\Resources\PmaRecordResource;

/**
 * Single extension point for the whole specialty architecture: adding a
 * new specialty (étape 4b) means adding one line here (plus its own
 * migrations/model/resource/controller) — nothing else in the shared
 * layer (timeline, stats) needs to change.
 */
class SpecialtyRegistry
{
    private const MAP = [
        'maternite' => [MaternityRecord::class, MaternityRecordResource::class],
        'dentaire' => [DentalChart::class, DentalChartResource::class],
        'dialyse' => [DialysisProgram::class, DialysisProgramResource::class],
        'ophtalmo' => [OphtalmoRecord::class, OphtalmoRecordResource::class],
        'cardiologie' => [CardioRecord::class, CardioRecordResource::class],
        'kinesitherapie' => [KineProgram::class, KineProgramResource::class],
        'oncologie' => [OncoRecord::class, OncoRecordResource::class],
        'pma' => [PmaRecord::class, PmaRecordResource::class],
        'sante_mentale' => [MentalHealthRecord::class, MentalHealthRecordResource::class],
        'pediatrie' => [PediatricRecord::class, PediatricRecordResource::class],
        'medecine_travail' => [OccupationalHealthRecord::class, OccupationalHealthRecordResource::class],
        'soins_domicile' => [HomeCareRecord::class, HomeCareRecordResource::class],
    ];

    public static function modelFor(string $type): ?string
    {
        return self::MAP[$type][0] ?? null;
    }

    public static function resourceFor(string $type): ?string
    {
        return self::MAP[$type][1] ?? null;
    }

    public static function types(): array
    {
        return array_keys(self::MAP);
    }
}
