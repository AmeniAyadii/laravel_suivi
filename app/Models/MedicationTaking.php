<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationTaking extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicament_id',
        'user_id',
        'prise_prevue',
        'prise_reelle',
        'statut',
        'notes'
    ];

    protected $casts = [
        'prise_prevue' => 'datetime',
        'prise_reelle' => 'datetime',
    ];

    // Relations
    public function medicament()
    {
        return $this->belongsTo(Medicament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('statut', 'prevue');
    }

    public function scopeTaken($query)
    {
        return $query->where('statut', 'prise');
    }

    public function scopeMissed($query)
    {
        return $query->where('statut', 'oubliee');
    }
}