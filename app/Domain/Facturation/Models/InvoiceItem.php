<?php

namespace App\Domain\Facturation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InvoiceItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'invoice_id',
        'billable_item_id',
        'libelle',
        'categorie',
        'quantite',
        'prix_unitaire',
        'montant_total',
        'taux_couverture_applique',
        'montant_assurance',
        'montant_patient',
    ];

    protected function casts(): array
    {
        return [
            'prix_unitaire' => 'decimal:2',
            'montant_total' => 'decimal:2',
            'taux_couverture_applique' => 'decimal:2',
            'montant_assurance' => 'decimal:2',
            'montant_patient' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('invoice_item');
    }
}
