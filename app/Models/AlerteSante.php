<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlerteSante extends Model
{
    protected $table = 'alertes_sante';
    public $timestamps = false;
    
    protected $fillable = [
        'user_id', 'type_alerte', 'niveau_gravite', 
        'message', 'constante_id', 'est_lue', 
        'est_resolue', 'date_creation', 'date_resolution'
    ];
    
    protected $casts = [
        'est_lue' => 'boolean',
        'est_resolue' => 'boolean',
        'date_creation' => 'datetime',
        'date_resolution' => 'datetime'
    ];
}