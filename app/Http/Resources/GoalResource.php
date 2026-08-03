<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'target_value' => (float) $this->target_value,
            'current_value' => (float) $this->current_value,
            'unit' => $this->unit,
            'status' => $this->status,
            'start_date' => $this->start_date->toISOString(),
            'target_date' => $this->target_date?->toISOString(),
            'completed_date' => $this->completed_date?->toISOString(),
            'progress_percentage' => $this->progress_percentage,
            'progress' => $this->progress,
            'is_completed' => $this->is_completed,
            'is_overdue' => $this->is_overdue,
            'time_remaining' => $this->time_remaining,
            'is_recurring' => $this->is_recurring,
            'recurrence_pattern' => $this->recurrence_pattern,
            'icon' => $this->icon,
            'color_hex' => $this->color_hex,
            'milestones' => GoalMilestoneResource::collection($this->whenLoaded('milestones')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

class GoalMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'target_value' => (float) $this->target_value,
            'is_completed' => $this->is_completed,
            'completed_date' => $this->completed_date?->toISOString(),
        ];
    }
}