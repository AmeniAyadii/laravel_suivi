<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // ✅ IMPORTANT: Importer Model

class UserHealthMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date_mesure',
        'poids',
        'taille',
        'imc',
        'pression_systolique',
        'pression_diastolique',
        'frequence_cardiaque',
        'temperature',
        'glycemie',
        'cholesterol',
        'heures_sommeil',
        'minutes_exercice',
        'stress_niveau',
        'notes'
    ];

    protected $casts = [
        'date_mesure' => 'date',
        'poids' => 'decimal:2',
        'taille' => 'decimal:2',
        'imc' => 'decimal:2',
        'temperature' => 'decimal:1',
        'glycemie' => 'decimal:2',
        'cholesterol' => 'decimal:2',
        'heures_sommeil' => 'decimal:1',
    ];

    // Relation avec User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}