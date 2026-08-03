<?php

namespace App\Models\MongoDB;

use MongoDB\Laravel\Eloquent\Model;

class HealthLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'health_logs';

    protected $fillable = [
        'user_id',
        'date',
        'symptoms',
        'medications_taken',
        'mood',
        'sleep_quality',
        'energy_level',
        'notes',
        'vitals',
        'activities'
    ];

    protected $casts = [
        'symptoms' => 'array',
        'medications_taken' => 'array',
        'vitals' => 'array',
        'activities' => 'array',
        'date' => 'datetime',
    ];

    // Indexes MongoDB
    // db.health_logs.createIndex({ user_id: 1, date: -1 })
    // db.health_logs.createIndex({ "symptoms.name": 1 })
}