<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'date_start',
        'date_end',
        'data',
        'file_path',
        'status',
    ];

    protected $casts = [
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'data' => 'array',
    ];

    // ==================== RELATIONS ====================
    
    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== SCOPES ====================
    
    /**
     * Scope : rapports générés
     */
    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    /**
     * Scope : rapports envoyés
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope : rapports en brouillon
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope : par utilisateur
     */
    public function scopePourUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope : par période (date_start et date_end)
     */
    public function scopePeriode($query, $dateStart, $dateEnd)
    {
        return $query->whereBetween('date_start', [$dateStart, $dateEnd]);
    }

    /**
     * Scope : rapports récents
     */
    public function scopeRecents($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ==================== ACCESSORS ====================
    
    /**
     * Accesseur : libellé du statut
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'draft' => 'Brouillon',
            'generated' => 'Généré',
            'sent' => 'Envoyé'
        ];
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Accesseur : couleur du statut
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            'draft' => 'gray',
            'generated' => 'green',
            'sent' => 'blue'
        ];
        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Accesseur : période formatée
     */
    public function getPeriodeAttribute()
    {
        if ($this->date_start && $this->date_end) {
            return $this->date_start->format('d/m/Y') . ' - ' . $this->date_end->format('d/m/Y');
        }
        return null;
    }

    /**
     * Accesseur : titre complet avec date
     */
    public function getFullTitleAttribute()
    {
        return $this->title . ' (' . $this->periode . ')';
    }

    // ==================== MUTATEURS ====================
    
    /**
     * Mutateur : définir le titre
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = ucfirst($value);
    }

    /**
     * Mutateur : définir le statut en minuscule
     */
    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = strtolower($value);
    }

    // ==================== MÉTHODES UTILITAIRES ====================
    
    /**
     * Vérifier si le rapport est généré
     */
    public function isGenerated()
    {
        return $this->status === 'generated';
    }

    /**
     * Vérifier si le rapport est envoyé
     */
    public function isSent()
    {
        return $this->status === 'sent';
    }

    /**
     * Vérifier si le rapport est un brouillon
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Marquer le rapport comme généré
     */
    public function markAsGenerated()
    {
        $this->update(['status' => 'generated']);
    }

    /**
     * Marquer le rapport comme envoyé
     */
    public function markAsSent()
    {
        $this->update(['status' => 'sent']);
    }

    /**
     * Marquer le rapport comme brouillon
     */
    public function markAsDraft()
    {
        $this->update(['status' => 'draft']);
    }

    /**
     * Vérifier si le rapport a un fichier PDF
     */
    public function hasFile()
    {
        return !empty($this->file_path);
    }

    /**
     * Obtenir le nom du fichier
     */
    public function getFileName()
    {
        if ($this->file_path) {
            return basename($this->file_path);
        }
        return null;
    }

    /**
     * Obtenir les statistiques du rapport
     */
    public function getStats()
    {
        return $this->data['stats'] ?? null;
    }

    /**
     * Obtenir les données utilisateur du rapport
     */
    public function getUserData()
    {
        return $this->data['user'] ?? null;
    }
}