<?php

namespace App\Domain\Laboratoire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extension point only — see the migration docblock. Nothing in the app
 * writes to this table yet; it exists so a future lab analyzer interface
 * (HL7/ASTM) has a place to log exchanged messages without a schema change.
 */
class LabAnalyzerInterfaceLog extends Model
{
    protected $fillable = [
        'lab_sample_id',
        'lab_result_id',
        'direction',
        'raw_payload',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(LabSample::class, 'lab_sample_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LabResult::class, 'lab_result_id');
    }
}
