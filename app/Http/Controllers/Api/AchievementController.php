<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AchievementController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Pour l'instant, retourner un tableau vide
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Aucune récompense disponible pour le moment'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur achievements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des récompenses',
            ], 500);
        }
    }

    public function stats(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_points' => 0,
                'total_achievements' => 0,
                'recent_achievements' => [],
            ]
        ]);
    }
}