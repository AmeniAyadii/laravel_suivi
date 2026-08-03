<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Symptome extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
        'niveau',
        'date_enregistrement',
        'notes',
    ];

    protected $casts = [
        'date_enregistrement' => 'datetime',
        'niveau' => 'integer',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
