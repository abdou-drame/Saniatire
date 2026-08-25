<?php

namespace App\Domain\Patient\Models;

use Database\Factories\PatientAllergyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    /** @use HasFactory<PatientAllergyFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'allergen',
        'severity',
        'reaction',
        'notes',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
