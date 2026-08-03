<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TensionAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'measure_id',
        'analysis_type',
        'summary',
        'recommendation',
        'severity_level',
        'details',
        'analyzed_date',
    ];

    protected $casts = [
        'analyzed_date' => 'datetime',
        'details' => 'array',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function measure()
    {
        return $this->belongsTo(TensionMeasure::class);
    }
}