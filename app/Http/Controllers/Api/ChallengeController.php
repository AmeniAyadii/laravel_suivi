<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            // ✅ Récupérer TOUS les défis actifs (sans filtre de date)
            $challenges = Challenge::where('is_active', true)
                                   ->orderBy('start_date', 'asc')
                                   ->get();
            
            Log::info('📊 Défis trouvés:', ['count' => $challenges->count()]);

            // Ajouter le statut de participation
            foreach ($challenges as $challenge) {
                $challenge->is_joined = $challenge->participants()
                                                  ->where('user_id', $userId)
                                                  ->exists();
                $challenge->is_completed = $challenge->participants()
                                                     ->where('user_id', $userId)
                                                     ->where('is_completed', true)
                                                     ->exists();
            }

            return response()->json([
                'success' => true,
                'data' => $challenges,
                'count' => $challenges->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur challenges: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function join(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;
            $challenge = Challenge::findOrFail($id);
            
            // ✅ Vérifier si le défi est actif
            if (!$challenge->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce défi n\'est plus actif',
                ], 400);
            }
            
            // ✅ Vérifier si l'utilisateur participe déjà
            if ($challenge->participants()->where('user_id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous participez déjà à ce défi',
                ], 400);
            }

            // ✅ Ajouter le participant
            $challenge->participants()->attach($userId, [
                'joined_date' => now(),
                'progress' => 0,
                'is_completed' => false,
            ]);

            // ✅ Incrémenter le compteur
            $challenge->increment('participants_count');

            return response()->json([
                'success' => true,
                'message' => 'Vous avez rejoint le défi avec succès',
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur join challenge: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la participation: ' . $e->getMessage(),
            ], 500);
        }
    }
}