<?php

namespace App\Http\Controllers;

use App\Models\Symptome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SymptomeController extends Controller
{
    /**
     * Liste des symptômes de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $symptomes = Symptome::where('user_id', $userId)
                ->orderBy('date_enregistrement', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $symptomes
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index symptomes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dernier symptôme enregistré
     */
    public function last(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $symptome = Symptome::where('user_id', $userId)
                ->orderBy('date_enregistrement', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $symptome
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur last symptome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter un nouveau symptôme
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'type' => 'nullable|string|in:douleur,fatigue,digestif,respiratoire,neurologique,cardiaque,musculaire,cutane',
            'niveau' => 'nullable|integer|min:1|max:10',
            'statut' => 'nullable|string|in:actif,resolu,en_amelioration,aggravation',  // ✅ CHANGÉ: status → statut
            'duree' => 'nullable|string',
            'frequence' => 'nullable|string',
            'notes' => 'nullable|string',
            'date_enregistrement' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->id;

            $symptome = Symptome::create([
                'user_id' => $userId,
                'description' => $request->description,
                'type' => $request->type ?? 'autre',
                'niveau' => $request->niveau ?? 1,
                'statut' => $request->statut ?? 'actif',  // ✅ CHANGÉ: status → statut
                'date_enregistrement' => $request->date_enregistrement ?? now(),
                'duree' => $request->duree,
                'frequence' => $request->frequence,
                'notes' => $request->notes,
            ]);

            Log::info('Symptôme créé: ' . $symptome->id . ' par user ' . $userId);

            return response()->json([
                'success' => true,
                'message' => 'Symptôme enregistré avec succès',
                'data' => $symptome
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur store symptome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un symptôme
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:douleur,fatigue,digestif,respiratoire,neurologique,cardiaque,musculaire,cutane',
            'niveau' => 'nullable|integer|min:1|max:10',
            'statut' => 'nullable|string|in:actif,resolu,en_amelioration,aggravation',  // ✅ CHANGÉ: status → statut
            'duree' => 'nullable|string',
            'frequence' => 'nullable|string',
            'notes' => 'nullable|string',
            'date_enregistrement' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->id;

            $symptome = Symptome::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$symptome) {
                return response()->json([
                    'success' => false,
                    'error' => 'Symptôme non trouvé'
                ], 404);
            }

            // Mise à jour des champs
            if ($request->has('description')) $symptome->description = $request->description;
            if ($request->has('type')) $symptome->type = $request->type;
            if ($request->has('niveau')) $symptome->niveau = $request->niveau;
            if ($request->has('statut')) {  // ✅ CHANGÉ: status → statut
                $symptome->statut = $request->statut;
                // Si le symptôme est résolu, on enregistre la date de résolution
                if ($request->statut === 'resolu') {
                    $symptome->date_resolution = now();
                }
            }
            if ($request->has('duree')) $symptome->duree = $request->duree;
            if ($request->has('frequence')) $symptome->frequence = $request->frequence;
            if ($request->has('notes')) $symptome->notes = $request->notes;
            if ($request->has('date_enregistrement')) $symptome->date_enregistrement = $request->date_enregistrement;

            $symptome->save();

            Log::info('Symptôme mis à jour: ' . $symptome->id . ' par user ' . $userId);

            return response()->json([
                'success' => true,
                'message' => 'Symptôme mis à jour avec succès',
                'data' => $symptome
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur update symptome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Résoudre un symptôme
     */
    public function resolve(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $symptome = Symptome::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$symptome) {
                return response()->json([
                    'success' => false,
                    'error' => 'Symptôme non trouvé'
                ], 404);
            }

            $symptome->statut = 'resolu';  // ✅ CHANGÉ: status → statut
            $symptome->date_resolution = now();
            $symptome->save();

            Log::info('Symptôme résolu: ' . $symptome->id . ' par user ' . $userId);

            return response()->json([
                'success' => true,
                'message' => 'Symptôme résolu avec succès',
                'data' => $symptome
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur resolve symptome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un symptôme
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $symptome = Symptome::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$symptome) {
                return response()->json([
                    'success' => false,
                    'error' => 'Symptôme non trouvé'
                ], 404);
            }

            $symptome->delete();

            Log::info('Symptôme supprimé: ' . $symptome->id . ' par user ' . $userId);

            return response()->json([
                'success' => true,
                'message' => 'Symptôme supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur destroy symptome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des symptômes
     */
    public function stats(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $symptomes = Symptome::where('user_id', $userId)->get();

            $stats = [
                'total' => $symptomes->count(),
                'actifs' => $symptomes->where('statut', 'actif')->count(),  // ✅ CHANGÉ: status → statut
                'resolus' => $symptomes->where('statut', 'resolu')->count(),
                'en_amelioration' => $symptomes->where('statut', 'en_amelioration')->count(),
                'aggravation' => $symptomes->where('statut', 'aggravation')->count(),
                'par_type' => $symptomes->groupBy('type')->map->count(),
                'par_status' => $symptomes->groupBy('statut')->map->count(),  // ✅ CHANGÉ: status → statut
                'niveau_moyen' => $symptomes->avg('niveau') ?? 0,
                'niveau_max' => $symptomes->max('niveau') ?? 0,
                'niveau_min' => $symptomes->min('niveau') ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur stats symptomes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyse des symptômes (IA)
     */
    // app/Http/Controllers/SymptomeController.php - Méthode analyze

public function analyze(Request $request)
{
    try {
        $userId = $request->user()->id;
        $symptomes = Symptome::where('user_id', $userId)->get();

        if ($symptomes->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => '📊 Aucun symptôme à analyser',
                    'insights' => [],
                    'recommendations' => 'Commencez à enregistrer vos symptômes',
                    'severity_trend' => ['average' => 0, 'max' => 0, 'min' => 0, 'std_dev' => 0]
                ]
            ]);
        }

        $niveaux = $symptomes->pluck('niveau')->toArray();
        $avg = count($niveaux) > 0 ? array_sum($niveaux) / count($niveaux) : 0;
        $max = count($niveaux) > 0 ? max($niveaux) : 0;
        $min = count($niveaux) > 0 ? min($niveaux) : 0;

        // Calcul de l'écart-type
        $stdDev = 0;
        if (count($niveaux) > 1) {
            $variance = 0;
            foreach ($niveaux as $n) {
                $variance += pow($n - $avg, 2);
            }
            $stdDev = sqrt($variance / count($niveaux));
        }

        // Détection de la tendance
        $trend = 'stable';
        if ($avg > 6) $trend = 'détérioration';
        elseif ($avg < 4) $trend = 'amélioration';

        // Types les plus fréquents
        $types = $symptomes->groupBy('type')->map->count();
        $mostFrequent = $types->sortDesc()->keys()->first() ?? 'Aucun';

        // Symptôme le plus sévère
        $mostSevere = $symptomes->sortByDesc('niveau')->first();

        // ✅ Construction du résumé avec formatage propre
        $summary = "📊 Analyse de " . $symptomes->count() . " symptômes\n";
        $summary .= "• Symptômes actifs: " . $symptomes->where('statut', 'actif')->count() . "\n";
        $summary .= "• Résolus: " . $symptomes->where('statut', 'resolu')->count() . "\n";
        $summary .= "• En amélioration: " . $symptomes->where('statut', 'en_amelioration')->count() . "\n";
        $summary .= "• En aggravation: " . $symptomes->where('statut', 'aggravation')->count() . "\n";
        $summary .= "• Niveau moyen: " . round($avg, 1) . "/10\n";
        $summary .= "• Types les plus fréquents: " . $mostFrequent;

        // ✅ Ajout d'alerte si nécessaire
        if ($avg > 6) {
            $summary .= "\n⚠️ Attention: Niveau de sévérité élevé. Il est recommandé de consulter un médecin.";
        } elseif ($avg > 4) {
            $summary .= "\n⚠️ Niveau modéré - Surveillez vos symptômes.";
        } else {
            $summary .= "\n✅ Niveau gérable - Continuez à suivre vos symptômes.";
        }

        // ✅ Recommandations formatées
        $recommendations = [];
        if ($avg > 7) {
            $recommendations[] = "🔴 Niveau de sévérité élevé - Consultez un médecin rapidement";
        } elseif ($avg > 5) {
            $recommendations[] = "⚠️ Niveau modéré - Surveillez vos symptômes";
        } else {
            $recommendations[] = "✅ Niveau gérable - Continuez à suivre vos symptômes";
        }

        if ($max > 8) {
            $recommendations[] = "🚨 Présence de symptômes critiques - Urgence médicale recommandée";
        }

        $recommendations[] = "📝 Continuez à enregistrer vos symptômes quotidiennement";
        $recommendations[] = "🩺 N'hésitez pas à consulter un professionnel de santé";

        // ✅ Insights formatés
        $insights = [
            'most_frequent' => $mostFrequent,
            'overall_trend' => $trend,
        ];

        if ($mostSevere) {
            $insights['worst_day'] = [
                'symptom' => $mostSevere->description,
                'level' => $mostSevere->niveau,
            ];
            $insights['most_severe'] = [
                'symptom' => $mostSevere->description,
                'level' => $mostSevere->niveau,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'insights' => $insights,
                'recommendations' => implode("\n", $recommendations),
                'severity_trend' => [
                    'average' => round($avg, 1),
                    'max' => $max,
                    'min' => $min,
                    'std_dev' => round($stdDev, 1),
                ]
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur analyze symptomes: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Erreur: ' . $e->getMessage()
        ], 500);
    }
}
}