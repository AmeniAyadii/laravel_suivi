<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'photo_url',
        'age',
        'sexe',
        'taille',
        'poids',
        'telephone',
        'groupe_sanguin',
        'allergies',
        'maladies_chroniques',
        'medicaments_count',
        'appointments_count',
        'health_score',
    ];

    protected $casts = [
        'age' => 'integer',
        'taille' => 'float',
        'poids' => 'float',
        'medicaments_count' => 'integer',
        'appointments_count' => 'integer',
        'health_score' => 'float',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}