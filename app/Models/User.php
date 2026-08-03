<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'age',
        'sexe',
        'taille',
        'poids',
        'groupe_sanguin',
        'allergies',
        'maladies_chroniques',
        'email',
        'password',
        'is_premium',        // ✅ Ajouter ce champ
        'premium_expires_at', // ✅ Ajouter ce champ
        
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'taille' => 'decimal:2',
        'poids' => 'decimal:2',
    ];

    // Relations
    public function medicaments()
    {
        return $this->hasMany(Medicament::class);
    }

    public function rendezVous()
    {
        return $this->hasMany(RendezVous::class);
    }

    public function symptomes()
    {
        return $this->hasMany(Symptome::class);
    }

    // Dans app/Models/User.php
public function notifications()
{
    return $this->hasMany(Notification::class);
}

public function notificationsNonLues()
{
    return $this->notifications()->where('lu', false);
}

    /**
     * 🔥 Vérifier si l'utilisateur est premium
     */
    public function isPremium(): bool
    {
        return $this->is_premium && ($this->premium_expires_at === null || $this->premium_expires_at > now());
    }
}
