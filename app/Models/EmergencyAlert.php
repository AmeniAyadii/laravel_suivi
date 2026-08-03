<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symptomes',
        'latitude',
        'longitude',
        'adresse',
        'statut',
        'resolue_a',
        'notes'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'resolue_a' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('statut', ['envoyee', 'en_cours']);
    }

    public function scopeResolved($query)
    {
        return $query->where('statut', 'terminee');
    }
}