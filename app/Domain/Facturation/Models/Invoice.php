<?php

namespace App\Domain\Facturation\Models;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Caisse\Models\Payment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'insurance_convention_id',
        'numero',
        'date_emission',
        'date_echeance',
        'montant_total',
        'montant_part_patient',
        'montant_part_assurance',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'montant_total' => 'decimal:2',
            'montant_part_patient' => 'decimal:2',
            'montant_part_assurance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (! $invoice->date_echeance && $invoice->date_emission) {
                $jours = config('notifications.echeance_facture_defaut_jours', 30);
                $invoice->date_echeance = Carbon::parse($invoice->date_emission)->addDays($jours);
            }
        });

        static::created(function (Invoice $invoice) {
            if (! $invoice->numero) {
                $invoice->update(['numero' => "FAC-{$invoice->structure_id}-{$invoice->id}"]);
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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function solde(): float
    {
        return round((float) $this->montant_total - (float) $this->payments->sum('montant'), 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('invoice');
    }
}
