<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'category', 'target_value',
        'current_value', 'unit', 'status', 'start_date', 'target_date',
        'completed_date', 'progress_percentage', 'is_recurring',
        'recurrence_pattern', 'reminders', 'icon', 'color_hex', 'metadata'
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_date' => 'date',
        'is_recurring' => 'boolean',
        'reminders' => 'array',
        'metadata' => 'array',
        'progress_percentage' => 'integer',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class);
    }

    // Accessors
    public function getProgressAttribute(): float
    {
        return $this->progress_percentage / 100;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date && 
               $this->target_date->isPast() && 
               !$this->is_completed;
    }

    public function getTimeRemainingAttribute(): string
    {
        if (!$this->target_date) {
            return 'Pas de date limite';
        }

        $now = now();
        $diff = $now->diff($this->target_date);

        if ($this->target_date->isPast()) {
            return 'Dépassé';
        }

        if ($diff->days > 30) {
            $months = floor($diff->days / 30);
            return "{$months} mois restants";
        } elseif ($diff->days > 7) {
            $weeks = floor($diff->days / 7);
            return "{$weeks} semaines restantes";
        } elseif ($diff->days > 1) {
            return "{$diff->days} jours restants";
        } elseif ($diff->days == 1) {
            return '1 jour restant';
        } else {
            return "{$diff->h} heures restantes";
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['not_started', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Methods
    public function updateProgress(float $newValue): void
    {
        $this->current_value = $newValue;
        
        if ($this->target_value > 0) {
            $progress = ($newValue / $this->target_value) * 100;
            $this->progress_percentage = min(100, (int) round($progress));
        }

        if ($this->progress_percentage >= 100 && $this->status !== 'completed') {
            $this->status = 'completed';
            $this->completed_date = now();
            $this->fireGoalCompletedEvent();
        } elseif ($this->status === 'not_started') {
            $this->status = 'in_progress';
        }

        $this->save();
        $this->checkMilestones();
    }

    private function checkMilestones(): void
    {
        foreach ($this->milestones as $milestone) {
            if (!$milestone->is_completed && 
                $this->current_value >= $milestone->target_value) {
                $milestone->markAsCompleted();
            }
        }
    }

    private function fireGoalCompletedEvent(): void
    {
        event(new GoalCompleted($this));
    }

    // Factory
    public static function createFromRequest(array $data, int $userId): self
    {
        $goal = self::create([
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'category' => $data['category'],
            'target_value' => $data['target_value'],
            'current_value' => 0,
            'unit' => $data['unit'],
            'status' => 'not_started',
            'start_date' => $data['start_date'] ?? now(),
            'target_date' => $data['target_date'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'icon' => $data['icon'] ?? '🎯',
            'color_hex' => $data['color_hex'] ?? '#4CAF50',
            'reminders' => $data['reminders'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Create milestones if any
        if (!empty($data['milestones'])) {
            foreach ($data['milestones'] as $milestoneData) {
                $goal->milestones()->create([
                    'title' => $milestoneData['title'],
                    'target_value' => $milestoneData['target_value'],
                ]);
            }
        }

        return $goal;
    }
}