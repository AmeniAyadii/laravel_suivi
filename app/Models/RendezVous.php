<?php
// app/Models/RendezVous.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\NotificationService; // ✅ AJOUTER CETTE LIGNE

class RendezVous extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rendez_vous';

    protected $fillable = [
        'user_id',
        'titre',
        'motif',
        'medecin_nom',
        'medecin_specialite',
        'medecin_telephone',
        'medecin_email',
        'date_heure',
        'date_fin',
        'lieu',
        'adresse',
        'code_postal',
        'ville',
        'type',
        'lien_visio',
        'statut',
        'notes',
        'notes_medecin',
        'diagnostic',
        'prescriptions',
        'rappel_envoye',
        'rappel_envoye_a',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'date_fin' => 'datetime',
        'rappel_envoye_a' => 'datetime',
        'rappel_envoye' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ==================== RELATIONS ====================
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== SCOPES ====================

    /**
     * Rendez-vous à venir
     */
    public function scopeAVenir($query)
    {
        return $query->where('statut', 'à_venir')
            ->orWhere('statut', 'confirmé')
            ->where('date_heure', '>=', now());
    }

    /**
     * Rendez-vous futurs
     */
    public function scopeFuturs($query)
    {
        return $query->where('date_heure', '>=', now());
    }

    /**
     * Rendez-vous du jour
     */
    public function scopeAujourdhui($query)
    {
        return $query->whereDate('date_heure', now()->toDateString());
    }

    /**
     * Rendez-vous de cette semaine
     */
    public function scopeCetteSemaine($query)
    {
        return $query->whereBetween('date_heure', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Rendez-vous confirmés
     */
    public function scopeConfirmes($query)
    {
        return $query->where('statut', 'confirmé');
    }

    /**
     * Rendez-vous avec rappel non envoyé
     */
    public function scopeRappelNonEnvoye($query)
    {
        return $query->where('rappel_envoye', false)
            ->whereIn('statut', ['à_venir', 'confirmé'])
            ->where('date_heure', '>=', now())
            ->whereNotNull('rappel_envoye_a')
            ->where('rappel_envoye_a', '<=', now());
    }

    /**
     * Rendez-vous par médecin
     */
    public function scopeParMedecin($query, $nom)
    {
        return $query->where('medecin_nom', 'LIKE', "%{$nom}%");
    }

    /**
     * Rendez-vous par lieu
     */
    public function scopeParLieu($query, $lieu)
    {
        return $query->where('lieu', 'LIKE', "%{$lieu}%")
            ->orWhere('ville', 'LIKE', "%{$lieu}%");
    }

    /**
     * Rendez-vous par type
     */
    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    // ==================== ACCESSORS ====================

    /**
     * Format de date affichable
     */
    public function getDateFormateeAttribute()
    {
        return $this->date_heure->format('d/m/Y à H:i');
    }

    /**
     * Durée du rendez-vous
     */
    public function getDureeAttribute()
    {
        if ($this->date_fin) {
            return $this->date_heure->diffInMinutes($this->date_fin);
        }
        return 30; // Durée par défaut
    }

    /**
     * Statut avec label
     */
    public function getStatutLabelAttribute()
    {
        $labels = [
            'à_venir' => 'À venir',
            'confirmé' => 'Confirmé',
            'en_cours' => 'En cours',
            'passé' => 'Passé',
            'annulé' => 'Annulé',
            'reporté' => 'Reporté',
        ];
        return $labels[$this->statut] ?? $this->statut;
    }

    /**
     * Couleur du statut
     */
    public function getStatutCouleurAttribute()
    {
        $colors = [
            'à_venir' => '#3B82F6',
            'confirmé' => '#10B981',
            'en_cours' => '#F59E0B',
            'passé' => '#6B7280',
            'annulé' => '#EF4444',
            'reporté' => '#F59E0B',
        ];
        return $colors[$this->statut] ?? '#6B7280';
    }

    /**
     * Type de rendez-vous en français
     */
    public function getTypeLabelAttribute()
    {
        $labels = [
            'presentiel' => 'Présentiel',
            'visio' => 'Visio-conférence',
            'telephone' => 'Téléphone',
        ];
        return $labels[$this->type] ?? $this->type;
    }

    // ==================== MUTATORS ====================

    /**
     * Définir la date de rappel automatique
     */
    public function setRappelAutoAttribute($minutes = 30)
    {
        if ($this->date_heure) {
            $this->rappel_envoye_a = $this->date_heure->copy()->subMinutes($minutes);
        }
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier si le rendez-vous est aujourd'hui
     */
    public function isToday()
    {
        return $this->date_heure->isToday();
    }

    /**
     * Vérifier si le rendez-vous est à venir
     */
    public function isUpcoming()
    {
        return $this->date_heure->isFuture() && in_array($this->statut, ['à_venir', 'confirmé']);
    }

    /**
     * Vérifier si le rendez-vous est passé
     */
    public function isPast()
    {
        return $this->date_heure->isPast() || $this->statut === 'passé';
    }

    /**
     * Vérifier si le rendez-vous peut être annulé
     */
    public function canBeCancelled()
    {
        return $this->isUpcoming() && $this->date_heure->diffInHours(now()) > 2;
    }

    /**
     * Envoyer le rappel
     */
    public function sendReminder()
    {
        $this->rappel_envoye = true;
        $this->save();
        
        // ✅ Utilisation de NotificationService (maintenant importé)
        NotificationService::create(
            $this->user_id,
            '📅 Rappel rendez-vous',
            "Rendez-vous \"{$this->titre}\" avec Dr. {$this->medecin_nom} à " . $this->date_heure->format('H:i'),
            'appointment',
            null,
            null,
            ['rendezvous_id' => $this->id],
            '/appointments'
        );
    }
}