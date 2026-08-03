<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    protected $table = 'appointment_reminders';

    protected $fillable = [
        'rendez_vous_id',
        'user_id',
        'send_at',
        'sent_at',
        'type',
        'message',
        'is_sent',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}