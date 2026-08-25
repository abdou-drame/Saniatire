<?php

namespace App\Domain\Achats\Models;

use App\Domain\User\Models\User;
use Database\Factories\PurchaseOrderApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrderApproval extends Model
{
    /** @use HasFactory<PurchaseOrderApprovalFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'purchase_order_id',
        'approval_rule_id',
        'level',
        'min_amount',
        'role_name',
        'statut',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function approvalRule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('purchase_order_approval');
    }
}
