<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'titre',
        'message',
        'type',
        'icon',
        'couleur',
        'data',
        'lu',
        'lien',
        'date_envoi',
    ];

    protected $casts = [
        'data' => 'array',
        'lu' => 'boolean',
        'date_envoi' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ✅ SCOPE POUR LES NOTIFICATIONS NON LUES
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    // ✅ SCOPE POUR UN TYPE SPÉCIFIQUE
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    // ✅ SCOPE POUR UNE DATE SPÉCIFIQUE
    public function scopeDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }
}