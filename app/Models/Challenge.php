<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Challenge extends Model
{
    protected $fillable = [
        'title', 'description', 'icon', 'color_hex',
        'participants_count', 'completion_rate', 'start_date',
        'end_date', 'rewards', 'requirements', 'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rewards' => 'array',
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    // Relations
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_participants')
                    ->withPivot('progress', 'is_completed', 'joined_date', 'completed_date')
                    ->withTimestamps();
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        $now = now();
        return $this->attributes['is_active'] && 
               $now->between($this->start_date, $this->end_date);
    }

    public function getIsUpcomingAttribute(): bool
    {
        return now()->isBefore($this->start_date);
    }

    public function getDaysRemainingAttribute(): string
    {
        $diff = now()->diff($this->end_date);
        return "{$diff->days} jours restants";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('start_date', '<=', now())
                     ->where('end_date', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now())
                     ->where('is_active', true);
    }

    // Methods
    public function addParticipant(int $userId): bool
    {
        if ($this->participants()->where('user_id', $userId)->exists()) {
            return false;
        }

        $this->participants()->attach($userId, [
            'joined_date' => now(),
            'progress' => 0,
            'is_completed' => false,
        ]);

        $this->increment('participants_count');
        return true;
    }

    public function updateParticipantProgress(int $userId, int $progress): void
    {
        $participant = $this->participants()
                           ->where('user_id', $userId)
                           ->first();

        if ($participant) {
            $participant->pivot->progress = $progress;
            
            if ($progress >= 100 && !$participant->pivot->is_completed) {
                $participant->pivot->is_completed = true;
                $participant->pivot->completed_date = now();
                $this->increment('completion_rate');
            }
            
            $participant->pivot->save();
        }
    }
}