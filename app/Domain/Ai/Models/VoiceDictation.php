<?php

namespace App\Domain\Ai\Models;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Structure uniquement (Étape 9 §4 / §7) : aucune transcription réelle n'est
 * effectuée par cette application. `status` reste `en_attente` jusqu'à ce
 * qu'un job externe (non fourni ici) le fasse évoluer — voir
 * app/Domain/Ai/README.md.
 */
class VoiceDictation extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'user_id',
        'consultation_id',
        'audio_path',
        'status',
        'transcription',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('voice_dictation');
    }
}
