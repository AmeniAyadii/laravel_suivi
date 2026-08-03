<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'specialite',
        'cabinet_phone',
        'cabinet_address',
        'horaires',
        'accepts_voice_scheduler',
    ];

    protected $casts = [
        'horaires' => 'array',
        'accepts_voice_scheduler' => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->prenom ? $this->prenom . ' ' . $this->nom : $this->nom;
    }
}