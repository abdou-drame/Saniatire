<?php

namespace App\Domain\Icd\Models;

use Database\Factories\IcdCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared reference table (WHO ICD-10 / ICD-11) — deliberately NOT
 * tenant-scoped. Every structure searches and codes diagnoses against the
 * same referentiel; see database/seeders/README-ICD.md for how the full
 * official dataset can be imported later.
 */
class IcdCode extends Model
{
    /** @use HasFactory<IcdCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'version',
        'label',
        'parent_id',
        'level',
        'status',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Codes mapped to this one in the other referentiel, in either
     * direction — icd_code_mappings only stores CIM-10 -> CIM-11 rows
     * (see IcdCodeSeeder), so a CIM-11 code must match on "cible" instead
     * of "source" to find its CIM-10 equivalent.
     */
    public function equivalents(): \Illuminate\Support\Collection
    {
        $asSource = IcdCodeMapping::query()
            ->where('code_source', $this->code)->where('version_source', $this->version)
            ->pluck('code_cible', 'version_cible');

        $asTarget = IcdCodeMapping::query()
            ->where('code_cible', $this->code)->where('version_cible', $this->version)
            ->pluck('code_source', 'version_source');

        $pairs = $asSource->merge($asTarget);

        if ($pairs->isEmpty()) {
            return collect();
        }

        return static::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $version => $code) {
                    $q->orWhere(fn ($q) => $q->where('code', $code)->where('version', $version));
                }
            })
            ->get();
    }
}
