<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assessment_date',
        'weight',
        'height',
        'bmi',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'temperature',
        'blood_sugar',
        'cholesterol',
        'sleep_hours',
        'exercise_minutes',
        'water_intake',
        'stress_level',
        'mood',
        'notes',
        'overall_score',
        'recommendations',
        'status'
    ];

    protected $casts = [
        'assessment_date' => 'datetime',
        'weight' => 'float',
        'height' => 'float',
        'bmi' => 'float',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'heart_rate' => 'integer',
        'temperature' => 'float',
        'blood_sugar' => 'float',
        'cholesterol' => 'float',
        'sleep_hours' => 'float',
        'exercise_minutes' => 'integer',
        'water_intake' => 'integer',
        'stress_level' => 'integer',
        'mood' => 'string',
        'overall_score' => 'integer',
        'recommendations' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeRecent($query)
    {
        return $query->orderBy('assessment_date', 'desc');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePeriod($query, $start, $end)
    {
        return $query->whereBetween('assessment_date', [$start, $end]);
    }

    // Accesseurs
    public function getBmiLabelAttribute()
    {
        if ($this->bmi < 18.5) return 'Insuffisance pondérale';
        if ($this->bmi < 25) return 'Poids normal';
        if ($this->bmi < 30) return 'Surpoids';
        if ($this->bmi < 35) return 'Obésité modérée';
        if ($this->bmi < 40) return 'Obésité sévère';
        return 'Obésité morbide';
    }

    public function getBmiColorAttribute()
    {
        if ($this->bmi < 18.5) return 'orange';
        if ($this->bmi < 25) return 'green';
        if ($this->bmi < 30) return 'yellow';
        if ($this->bmi < 35) return 'orange';
        if ($this->bmi < 40) return 'red';
        return 'red';
    }

    public function getBloodPressureLabelAttribute()
    {
        $sys = $this->blood_pressure_systolic;
        $dia = $this->blood_pressure_diastolic;

        if ($sys < 120 && $dia < 80) return 'Normale';
        if ($sys < 130 && $dia < 80) return 'Élevée';
        if ($sys < 140 && $dia < 90) return 'Hypertension stade 1';
        return 'Hypertension stade 2';
    }

    public function getOverallScoreLabelAttribute()
    {
        $score = $this->overall_score ?? 0;
        if ($score >= 90) return 'Excellente';
        if ($score >= 75) return 'Bonne';
        if ($score >= 60) return 'Moyenne';
        if ($score >= 40) return 'À améliorer';
        return 'Critique';
    }

    public function getOverallScoreColorAttribute()
    {
        $score = $this->overall_score ?? 0;
        if ($score >= 90) return '#4CAF50';
        if ($score >= 75) return '#8BC34A';
        if ($score >= 60) return '#FFC107';
        if ($score >= 40) return '#FF9800';
        return '#F44336';
    }
}