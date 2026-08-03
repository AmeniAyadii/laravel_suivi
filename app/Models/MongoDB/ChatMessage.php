<?php

namespace App\Models\MongoDB;

use MongoDB\Laravel\Eloquent\Model;

class ChatMessage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'chat_messages';

    protected $fillable = [
        'user_id',
        'session_id',
        'role', // user, bot, system
        'content',
        'severity',
        'analysis',
        'quick_actions',
        'metadata',
        'timestamp'
    ];

    protected $casts = [
        'analysis' => 'array',
        'quick_actions' => 'array',
        'metadata' => 'array',
        'timestamp' => 'datetime',
    ];

    // Créer un message
    public static function createMessage($userId, $sessionId, $role, $content, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
            'timestamp' => now(),
        ]);
    }

    // Indexes à créer dans MongoDB
    // db.chat_messages.createIndex({ user_id: 1, session_id: 1, timestamp: -1 })
    // db.chat_messages.createIndex({ session_id: 1 })
    // db.chat_messages.createIndex({ timestamp: -1 })
}