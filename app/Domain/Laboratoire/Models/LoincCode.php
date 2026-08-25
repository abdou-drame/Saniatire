<?php

namespace App\Domain\Laboratoire\Models;

use Database\Factories\LoincCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared reference table (LOINC) — deliberately NOT tenant-scoped, exactly
 * like App\Domain\Icd\Models\IcdCode. Every structure prescribes and
 * reports lab analyses against the same referentiel; see
 * database/seeders/README-LOINC.md for how the full official dataset can
 * be imported later.
 */
class LoincCode extends Model
{
    /** @use HasFactory<LoincCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'component',
        'default_unit',
        'version',
        'status',
    ];
}
