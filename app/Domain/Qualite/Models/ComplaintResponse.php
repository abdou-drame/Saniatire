<?php

namespace App\Domain\Qualite\Models;

use App\Domain\User\Models\User;
use Database\Factories\ComplaintResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintResponse extends Model
{
    /** @use HasFactory<ComplaintResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'auteur_id',
        'message',
        'visible_patient',
    ];

    protected function casts(): array
    {
        return [
            'visible_patient' => 'boolean',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
