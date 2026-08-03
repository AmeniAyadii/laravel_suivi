<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeuilPersonnalise extends Model
{
    use HasFactory;

    protected $table = 'seuils_personnalises';
    
    protected $fillable = [
        'user_id',
        'type_constante',
        'min_normal',
        'max_normal',
        'min_alerte',
        'max_alerte',
    ];

    protected $casts = [
        'min_normal' => 'decimal:2',
        'max_normal' => 'decimal:2',
        'min_alerte' => 'decimal:2',
        'max_alerte' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}