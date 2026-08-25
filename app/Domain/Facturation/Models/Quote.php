<?php

namespace App\Domain\Facturation\Models;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'insurance_convention_id',
        'converted_invoice_id',
        'numero',
        'date_emission',
        'montant_total',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'montant_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Quote $quote) {
            if (! $quote->numero) {
                $quote->update(['numero' => "DEV-{$quote->structure_id}-{$quote->id}"]);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function insuranceConvention(): BelongsTo
    {
        return $this->belongsTo(InsuranceConvention::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('quote');
    }
}
