<?php

namespace App\Domain\Achats\Models;

use App\Domain\User\Models\User;
use Database\Factories\PurchaseOrderReceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrderReception extends Model
{
    /** @use HasFactory<PurchaseOrderReceptionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'purchase_order_item_id',
        'receptionne_par',
        'quantite_recue',
        'date_reception',
        'controle_qualite',
        'numero_lot',
        'date_peremption',
    ];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'date_peremption' => 'date',
        ];
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function receptionnePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionne_par');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('purchase_order_reception');
    }
}
