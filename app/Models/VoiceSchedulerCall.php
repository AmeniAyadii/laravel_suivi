<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceSchedulerCall extends Model
{
    protected $fillable = [
        'user_id',
        'rendez_vous_id',
        'call_sid',
        'cabinet_phone',
        'doctor_name',
        'preferred_date',
        'preferred_time',
        'status',
        'offered_date',
        'offered_time',
        'conversation_log',
        'user_response',
        'recording_url',
        'retry_count',
        'called_at',
        'answered_at',
        'completed_at',
    ];

    protected $casts = [
        'preferred_date' => 'datetime',
        'called_at' => 'datetime',
        'answered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }

    // ✅ Accesseurs
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'calling', 'answered', 'negotiating']);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'En attente',
            'calling' => 'En appel',
            'answered' => 'Décroché',
            'negotiating' => 'En négociation',
            'confirmed' => 'Confirmé',
            'failed' => 'Échec',
            'cancelled' => 'Annulé',
            'completed' => 'Terminé',
        ];
        return $labels[$this->status] ?? $this->status;
    }
}