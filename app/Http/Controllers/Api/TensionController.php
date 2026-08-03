<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TensionMeasure;
use App\Models\TensionAnalysis;
use App\Services\TensionAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TensionController extends Controller
{
    protected $analyzerService;

    public function __construct(TensionAnalyzerService $analyzerService)
    {
        $this->analyzerService = $analyzerService;
    }

    /**
     * 📊 Enregistrer une nouvelle mesure
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'systolic' => 'required|integer|min:50|max:250',
            'diastolic' => 'required|integer|min:30|max:160',
            'heart_rate' => 'nullable|integer|min:20|max:220',
            'measure_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Créer la mesure
            $measure = TensionMeasure::create([
                'user_id' => Auth::id(),
                'systolic' => $request->systolic,
                'diastolic' => $request->diastolic,
                'heart_rate' => $request->heart_rate,
                'measure_date' => $request->measure_date,
                'notes' => $request->notes,
            ]);

            $analysis = null;

            // Analyse automatique pour les utilisateurs Premium
            if (Auth::user()->isPremium()) {
                // Analyse individuelle
                $analysisResult = $this->analyzerService->analyzeSingleMeasure($measure);
                
                $analysis = TensionAnalysis::create([
                    'user_id' => Auth::id(),
                    'measure_id' => $measure->id,
                    'analysis_type' => 'single',
                    'summary' => $analysisResult['summary'],
                    'recommendation' => $analysisResult['recommendation'],
                    'severity_level' => $analysisResult['severity_level'],
                    'details' => $analysisResult['details'] ?? null,
                    'analyzed_date' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => '✅ Mesure enregistrée avec succès',
                'data' => $measure,
                'analysis' => $analysis,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '❌ Erreur lors de l\'enregistrement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📈 Récupérer l'historique des mesures
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 30);
        $userId = Auth::id();

        $measures = TensionMeasure::forUser($userId)
            ->recent($limit)
            ->get();

        return response()->json([
            'data' => $measures,
            'count' => $measures->count(),
        ]);
    }

    

    /**
     * 📊 Récupérer une mesure spécifique
     */
    public function show($id)
    {
        $measure = TensionMeasure::forUser(Auth::id())->findOrFail($id);
        return response()->json(['data' => $measure]);
    }

    /**
     * 📝 Mettre à jour une mesure
     */
    public function update(Request $request, $id)
    {
        $measure = TensionMeasure::forUser(Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'systolic' => 'sometimes|integer|min:50|max:250',
            'diastolic' => 'sometimes|integer|min:30|max:160',
            'heart_rate' => 'nullable|integer|min:20|max:220',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $measure->update($request->all());

        return response()->json([
            'message' => '✅ Mesure mise à jour',
            'data' => $measure
        ]);
    }

    /**
     * 🗑️ Supprimer une mesure
     */
    public function destroy($id)
    {
        $measure = TensionMeasure::forUser(Auth::id())->findOrFail($id);
        $measure->delete();

        return response()->json([
            'message' => '✅ Mesure supprimée'
        ]);
    }

    /**
     * 🤖 Analyse des tendances (Premium)
     */
    // app/Http/Controllers/Api/TensionController.php

public function analyzeTrends(Request $request)
{
    // 🔥 Vérifier que l'utilisateur est authentifié
    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.'
        ], 401);
    }

    // 🔥 Récupérer les données de la requête
    $measures = $request->input('measures', []);
    
    if (empty($measures)) {
        return response()->json([
            'success' => false,
            'message' => 'Aucune mesure fournie'
        ], 400);
    }

    if (count($measures) < 3) {
        return response()->json([
            'success' => false,
            'message' => 'Minimum 3 mesures nécessaires'
        ], 400);
    }

    // 🔥 ICI, vous pouvez appeler votre API Python pour l'analyse
    // OU faire l'analyse directement en PHP
    
    // Simulation d'analyse
    $avgSystolic = collect($measures)->avg('systolic');
    $avgDiastolic = collect($measures)->avg('diastolic');
    
    $analysis = [
        'summary' => "📊 Analyse de " . count($measures) . " mesures récentes\nMoyenne: " . round($avgSystolic) . "/" . round($avgDiastolic) . " mmHg",
        'recommendation' => $avgSystolic > 140 ? '⚠️ Tension élevée. Consultez votre médecin.' : '✅ Tension normale. Continuez votre suivi.',
        'severity_level' => $avgSystolic > 140 ? 'warning' : 'normal',
        'details' => [
            'measure_count' => count($measures),
            'avg_systolic' => round($avgSystolic, 1),
            'avg_diastolic' => round($avgDiastolic, 1),
        ]
    ];

    return response()->json([
        'success' => true,
        'data' => $analysis
    ]);
}
    /**
     * 📊 Récupérer les analyses récentes
     */
    public function getAnalyses(Request $request)
    {
        $limit = $request->get('limit', 10);

        $analyses = TensionAnalysis::where('user_id', Auth::id())
            ->orderBy('analyzed_date', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $analyses]);
    }

    /**
     * 📈 Statistiques pour le Dashboard
     */
    public function getStatistics()
    {
        $userId = Auth::id();

        // Dernière mesure
        $lastMeasure = TensionMeasure::forUser($userId)
            ->orderBy('measure_date', 'desc')
            ->first();

        // Statistiques des 30 derniers jours
        $thirtyDaysAgo = now()->subDays(30);
        $recentMeasures = TensionMeasure::forUser($userId)
            ->where('measure_date', '>=', $thirtyDaysAgo)
            ->get();

        $stats = [
            'total_measures' => $recentMeasures->count(),
            'avg_systolic' => $recentMeasures->avg('systolic'),
            'avg_diastolic' => $recentMeasures->avg('diastolic'),
            'last_measure' => $lastMeasure,
            'is_premium' => Auth::user()->isPremium(),
            'days_tracked' => $recentMeasures->pluck('measure_date')->unique()->count(),
        ];

        // Analyse des catégories
        $categories = [
            'normal' => 0,
            'high' => 0,
            'low' => 0,
            'crisis' => 0,
        ];

        foreach ($recentMeasures as $measure) {
            $category = $measure->category;
            if (isset($categories[$category])) {
                $categories[$category]++;
            }
        }

        $stats['categories_distribution'] = $categories;

        return response()->json(['data' => $stats]);
    }
}