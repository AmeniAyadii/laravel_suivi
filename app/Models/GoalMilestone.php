<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalMilestone extends Model
{
    protected $fillable = [
        'goal_id',
        'title',
        'target_value',
        'is_completed',
        'completed_date',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'is_completed' => 'boolean',
        'completed_date' => 'date',
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    public function markAsCompleted()
    {
        $this->is_completed = true;
        $this->completed_date = now();
        $this->save();
    }
}