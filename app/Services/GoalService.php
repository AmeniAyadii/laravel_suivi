<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Achievement;
use Carbon\Carbon;

class GoalService
{
    public function getUserStats(int $userId): array
    {
        $goals = Goal::where('user_id', $userId)->get();
        
        $totalGoals = $goals->count();
        $completedGoals = $goals->where('status', 'completed')->count();
        $inProgressGoals = $goals->where('status', 'in_progress')->count();
        
        $averageProgress = $totalGoals > 0 
            ? round($goals->avg('progress_percentage'), 1) 
            : 0;
        
        // Calcul de la série actuelle
        $streak = $this->calculateStreak($userId);
        
        // Objectifs par catégorie
        $categories = $goals->groupBy('category')
                           ->map(function ($group) {
                               return [
                                   'count' => $group->count(),
                                   'completed' => $group->where('status', 'completed')->count(),
                                   'progress' => round($group->avg('progress_percentage'), 1),
                               ];
                           });
        
        return [
            'total_goals' => $totalGoals,
            'completed_goals' => $completedGoals,
            'in_progress_goals' => $inProgressGoals,
            'average_progress' => $averageProgress,
            'current_streak' => $streak['current'],
            'best_streak' => $streak['best'],
            'categories' => $categories,
            'next_milestone' => $this->findNextMilestone($userId),
        ];
    }

    private function calculateStreak(int $userId): array
    {
        $completedGoals = Goal::where('user_id', $userId)
                             ->where('status', 'completed')
                             ->where('completed_date', '>=', Carbon::now()->subDays(30))
                             ->orderBy('completed_date', 'desc')
                             ->get();
        
        if ($completedGoals->isEmpty()) {
            return ['current' => 0, 'best' => 0];
        }
        
        $currentStreak = 0;
        $bestStreak = 0;
        $currentDate = Carbon::now()->startOfDay();
        
        foreach ($completedGoals as $goal) {
            $completedDate = Carbon::parse($goal->completed_date)->startOfDay();
            $diff = $currentDate->diffInDays($completedDate);
            
            if ($diff <= 1) {
                $currentStreak++;
                $currentDate = $completedDate;
            } else {
                break;
            }
        }
        
        // Calculer la meilleure série (simplifié)
        $bestStreak = $this->calculateBestStreak($userId);
        
        return [
            'current' => $currentStreak,
            'best' => $bestStreak,
        ];
    }

    private function calculateBestStreak(int $userId): int
    {
        $completedGoals = Goal::where('user_id', $userId)
                             ->where('status', 'completed')
                             ->orderBy('completed_date', 'asc')
                             ->get();
        
        if ($completedGoals->isEmpty()) {
            return 0;
        }
        
        $bestStreak = 1;
        $currentStreak = 1;
        $previousDate = Carbon::parse($completedGoals->first()->completed_date);
        
        foreach ($completedGoals->skip(1) as $goal) {
            $currentDate = Carbon::parse($goal->completed_date);
            $diff = $previousDate->diffInDays($currentDate);
            
            if ($diff <= 1) {
                $currentStreak++;
            } else {
                $bestStreak = max($bestStreak, $currentStreak);
                $currentStreak = 1;
            }
            
            $previousDate = $currentDate;
        }
        
        return max($bestStreak, $currentStreak);
    }

    private function findNextMilestone(int $userId): ?array
    {
        $goal = Goal::where('user_id', $userId)
                   ->where('status', 'in_progress')
                   ->whereHas('milestones', function($query) {
                       $query->where('is_completed', false);
                   })
                   ->with(['milestones' => function($query) {
                       $query->where('is_completed', false)
                             ->orderBy('target_value', 'asc');
                   }])
                   ->first();
        
        if (!$goal || $goal->milestones->isEmpty()) {
            return null;
        }
        
        $nextMilestone = $goal->milestones->first();
        
        return [
            'goal_title' => $goal->title,
            'milestone_title' => $nextMilestone->title,
            'target_value' => $nextMilestone->target_value,
            'current_value' => $goal->current_value,
            'unit' => $goal->unit,
            'remaining' => $nextMilestone->target_value - $goal->current_value,
            'goal_id' => $goal->id,
        ];
    }
}