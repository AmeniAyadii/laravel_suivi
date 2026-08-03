<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'title', 'description', 'icon', 'color_hex',
        'type', 'level', 'points', 'requirements'
    ];

    protected $casts = [
        'requirements' => 'array',
        'level' => 'integer',
        'points' => 'integer',
    ];

    // Relations
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
                    ->withPivot('unlocked_at')
                    ->withTimestamps();
    }

    // Methods
    public function unlockForUser(int $userId): void
    {
        if (!$this->users()->where('user_id', $userId)->exists()) {
            $this->users()->attach($userId, [
                'unlocked_at' => now(),
            ]);
            
            event(new AchievementUnlocked($this, $userId));
        }
    }

    public function isUnlockedByUser(int $userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }
}