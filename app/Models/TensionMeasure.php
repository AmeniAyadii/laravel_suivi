<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TensionMeasure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'systolic',
        'diastolic',
        'heart_rate',
        'measure_date',
        'notes',
    ];

    protected $casts = [
        'measure_date' => 'datetime',
        'systolic' => 'integer',
        'diastolic' => 'integer',
        'heart_rate' => 'integer',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analyses()
    {
        return $this->hasMany(TensionAnalysis::class);
    }

    // Scope pour les mesures récentes
    public function scopeRecent($query, $limit = 30)
    {
        return $query->orderBy('measure_date', 'desc')->limit($limit);
    }

    // Scope pour les mesures d'un utilisateur
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Getter pour la catégorie de tension
    public function getCategoryAttribute()
    {
        if ($this->systolic < 90 || $this->diastolic < 60) {
            return 'low';
        } elseif ($this->systolic > 180 || $this->diastolic > 120) {
            return 'crisis';
        } elseif ($this->systolic > 140 || $this->diastolic > 90) {
            return 'high';
        } else {
            return 'normal';
        }
    }

    // Getter pour l'affichage formaté
    public function getFormattedMeasureAttribute()
    {
        return "{$this->systolic}/{$this->diastolic}";
    }
}