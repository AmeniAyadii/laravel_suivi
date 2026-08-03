<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstanteVitale extends Model
{
    protected $table = 'constantes_vitales';
    
    protected $fillable = [
        'user_id', 'type_constante', 'valeur', 'unite', 
        'date_mesure', 'notes', 'est_anormal'
    ];
    
    protected $casts = [
        'date_mesure' => 'datetime',
        'valeur' => 'decimal:2',
        'est_anormal' => 'boolean'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function alertes()
    {
        return $this->hasMany(AlerteSante::class, 'constante_id');
    }
}