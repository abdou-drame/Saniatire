<?php

namespace App\Domain\Achats\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalRule extends Model
{
    /** @use HasFactory<ApprovalRuleFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'level',
        'min_amount',
        'role_name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('approval_rule');
    }
}
