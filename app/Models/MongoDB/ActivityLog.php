<?php

namespace App\Models\MongoDB;

use MongoDB\Laravel\Eloquent\Model;

class ActivityLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'ip_address',
        'user_agent',
        'details',
        'status',
        'timestamp'
    ];

    protected $casts = [
        'details' => 'array',
        'timestamp' => 'datetime',
    ];

    // Log une action
    public static function log($userId, $action, $module, $details = [], $status = 'success')
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => $details,
            'status' => $status,
            'timestamp' => now(),
        ]);
    }

    // Indexes MongoDB
    // db.activity_logs.createIndex({ user_id: 1, timestamp: -1 })
    // db.activity_logs.createIndex({ action: 1 })
    // db.activity_logs.createIndex({ module: 1 })
    // db.activity_logs.createIndex({ timestamp: -1 })
}