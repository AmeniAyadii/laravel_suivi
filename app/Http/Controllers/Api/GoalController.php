<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\GoalMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoalController extends Controller
{
    /**
     * Liste des objectifs de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $goals = Goal::where('user_id', $userId)
                        ->with('milestones')
                        ->orderBy('created_at', 'desc')
                        ->get();

            return response()->json([
                'success' => true,
                'data' => $goals,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur index goals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des objectifs',
            ], 500);
        }
    }

    /**
     * Créer un nouvel objectif
     */
    public function store(Request $request)
    {
        try {
            Log::info('📝 Création d\'un objectif', [
                'user_id' => $request->user()->id,
                'data' => $request->all()
            ]);

            // app/Http/Controllers/Api/GoalController.php
// Dans la méthode store, modifiez la validation :

$validator = Validator::make($request->all(), [
    'title' => 'required|string|max:255',
    'description' => 'nullable|string',
    // ✅ Corrigé : utiliser blood_sugar (avec underscore)
    'category' => 'required|string|in:medication,exercise,nutrition,sleep,hydration,weight,blood_pressure,blood_sugar,stress,other',
    'target_value' => 'required|numeric|min:0.01',
    'unit' => 'required|string|max:50',
    'start_date' => 'required|date',
    'target_date' => 'nullable|date|after:today',
    'is_recurring' => 'boolean',
    'recurrence_pattern' => 'nullable|in:daily,weekly,monthly',
    'icon' => 'nullable|string|max:10',
    'color_hex' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
    'milestones' => 'nullable|array',
]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $goal = Goal::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'description' => $request->description ?? '',
                'category' => $request->category,
                'target_value' => $request->target_value,
                'current_value' => 0,
                'unit' => $request->unit,
                'status' => 'not_started',
                'start_date' => $request->start_date,
                'target_date' => $request->target_date ?? null,
                'is_recurring' => $request->is_recurring ?? false,
                'recurrence_pattern' => $request->recurrence_pattern ?? null,
                'icon' => $request->icon ?? '🎯',
                'color_hex' => $request->color_hex ?? '#4CAF50',
                'reminders' => $request->reminders ?? [],
                'metadata' => $request->metadata ?? [],
            ]);

            // Créer les milestones
            if ($request->has('milestones') && is_array($request->milestones)) {
                foreach ($request->milestones as $milestoneData) {
                    GoalMilestone::create([
                        'goal_id' => $goal->id,
                        'title' => $milestoneData['title'],
                        'target_value' => $milestoneData['target_value'],
                    ]);
                }
            }

            Log::info('✅ Objectif créé', ['goal_id' => $goal->id]);

            return response()->json([
                'success' => true,
                'message' => 'Objectif créé avec succès',
                'data' => $goal->load('milestones'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erreur store goal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher un objectif spécifique
     */
    public function show($id)
    {
        try {
            $goal = Goal::with('milestones')->findOrFail($id);
            
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $goal,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Objectif non trouvé',
            ], 404);
        }
    }

    /**
     * Mettre à jour un objectif
     */
    public function update(Request $request, $id)
    {
        try {
            $goal = Goal::findOrFail($id);
            
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $goal->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Objectif mis à jour',
                'data' => $goal->load('milestones'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Mettre à jour la progression
     */
    public function updateProgress(Request $request, $id)
    {
        try {
            $goal = Goal::findOrFail($id);
            
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $request->validate([
                'value' => 'required|numeric|min:0',
            ]);

            $goal->current_value = $request->value;
            
            if ($goal->target_value > 0) {
                $progress = ($request->value / $goal->target_value) * 100;
                $goal->progress_percentage = min(100, (int) round($progress));
            }

            if ($goal->progress_percentage >= 100 && $goal->status !== 'completed') {
                $goal->status = 'completed';
                $goal->completed_date = now();
            }

            $goal->save();

            return response()->json([
                'success' => true,
                'message' => 'Progression mise à jour',
                'data' => $goal,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Supprimer un objectif
     */
    public function destroy($id)
    {
        try {
            $goal = Goal::findOrFail($id);
            
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $goal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Objectif supprimé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Statistiques des objectifs
     */
    public function stats(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $goals = Goal::where('user_id', $userId)->get();
            
            $total = $goals->count();
            $completed = $goals->where('status', 'completed')->count();
            $inProgress = $goals->where('status', 'in_progress')->count();
            
            $averageProgress = $total > 0 ? round($goals->avg('progress_percentage'), 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_goals' => $total,
                    'completed_goals' => $completed,
                    'in_progress_goals' => $inProgress,
                    'average_progress' => $averageProgress,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
            ], 500);
        }
    }
}