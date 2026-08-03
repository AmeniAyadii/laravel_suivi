<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicament extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'nom', 'nom_generique', 'dosage', 'forme',
        'voie_administration', 'frequence', 'horaires_prises',
        'duree_traitement_jours', 'date_debut', 'date_fin',
        'prochaine_prise', 'stock_actuel', 'seuil_alerte_stock',
        'instructions', 'effets_secondaires', 'contre_indications',
        'interactions', 'notes', 'statut', 'rappel_actif'
    ];

    protected $casts = [
        'horaires_prises' => 'array',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'prochaine_prise' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function takings()
    {
        return $this->hasMany(MedicationTaking::class);
    }

    // Vérifier si le médicament est actif
    public function isActive()
    {
        return $this->statut === 'actif' && $this->rappel_actif;
    }

    // Vérifier si le stock est bas
    public function isLowStock()
    {
        return $this->stock_actuel <= $this->seuil_alerte_stock;
    }

    // Prochaine prise formatée
    public function getProchainePriseFormattedAttribute()
    {
        if ($this->prochaine_prise) {
            return $this->prochaine_prise->format('d/m/Y à H:i');
        }
        return null;
    }

    // Scope pour les médicaments actifs
    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    // Scope pour les médicaments avec rappel
    public function scopeWithReminder($query)
    {
        return $query->where('rappel_actif', true);
    }

     // ✅ Relation avec l'utilisateur
   

    // ✅ Accesseur pour les jours restants
    public function getJoursRestantsAttribute(): int
    {
        if ($this->stock_actuel <= 0) return 0;
        
        $prisesParJour = 1;
        
        if (!empty($this->horaires_prises) && is_array($this->horaires_prises)) {
            $prisesParJour = count($this->horaires_prises);
        } elseif (!empty($this->frequence)) {
            $frequence = strtolower($this->frequence);
            if (str_contains($frequence, '8h')) $prisesParJour = 3;
            elseif (str_contains($frequence, '12h')) $prisesParJour = 2;
        }
        
        return intval($this->stock_actuel / $prisesParJour);
    }

    


}