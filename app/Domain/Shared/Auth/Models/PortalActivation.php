<?php

namespace App\Domain\Shared\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PortalActivation extends Model
{
    protected $fillable = [
        'activatable_type',
        'activatable_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function activatable(): MorphTo
    {
        return $this->morphTo();
    }
}
