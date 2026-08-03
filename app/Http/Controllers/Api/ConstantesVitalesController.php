<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConstanteVitale;
use App\Models\AlerteSante;
use App\Models\Symptome; // ✅ AJOUT
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // ✅ AJOUT pour Log
use Carbon\Carbon;

class ConstantesVitalesController extends Controller
{
    /**
     * ✅ Récupérer toutes les constantes vitales d'un utilisateur
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = ConstanteVitale::where('user_id', $user->id);
        
        // Filtrer par date
        if ($request->has('date_debut')) {
            $query->where('date_mesure', '>=', Carbon::parse($request->date_debut));
        }
        if ($request->has('date_fin')) {
            $query->where('date_mesure', '<=', Carbon::parse($request->date_fin));
        }
        
        // Filtrer par type
        if ($request->has('types')) {
            $types = explode(',', $request->types);
            $query->whereIn('type_constante', $types);
        }
        
        $constantes = $query->orderBy('date_mesure', 'desc')->get();
        
        // Grouper par type pour les statistiques
        $grouped = $constantes->groupBy('type_constante');
        $stats = [];
        
        foreach ($grouped as $type => $items) {
            $stats[$type] = [
                'derniere' => $items->first(),
                'moyenne' => $items->avg('valeur'),
                'min' => $items->min('valeur'),
                'max' => $items->max('valeur'),
                'count' => $items->count(),
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'constantes' => $constantes,
                'statistiques' => $stats,
                'periode' => [
                    'debut' => $request->date_debut ?? Carbon::now()->startOfWeek()->toDateString(),
                    'fin' => $request->date_fin ?? Carbon::now()->endOfWeek()->toDateString()
                ]
            ]
        ]);
    }

    /**
     * Enregistrer une nouvelle mesure
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_constante' => 'required|in:tension_systolique,tension_diastolique,frequence_cardiaque,temperature,glycemie,saturation_oxygene,poids,IMC',
            'valeur' => 'required|numeric|min:0',
            'unite' => 'required|string|max:20',
            'date_mesure' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        $estAnormal = $this->detecterAnomalie($request->type_constante, $request->valeur);

        $constante = ConstanteVitale::create([
            'user_id' => $user->id,
            'type_constante' => $request->type_constante,
            'valeur' => $request->valeur,
            'unite' => $request->unite,
            'date_mesure' => Carbon::parse($request->date_mesure),
            'notes' => $request->notes,
            'est_anormal' => $estAnormal
        ]);

        if ($estAnormal) {
            $this->genererAlerte($constante);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mesure enregistrée avec succès',
            'data' => $constante
        ], 201);
    }

    /**
     * ✅ Récupérer une constante spécifique
     */
    public function show($type)
    {
        $user = auth()->user();
        
        $constantes = ConstanteVitale::where('user_id', $user->id)
            ->where('type_constante', $type)
            ->orderBy('date_mesure', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $constantes
        ]);
    }

    /**
     * ✅ Mettre à jour une constante
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $constante = ConstanteVitale::where('user_id', $user->id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'valeur' => 'numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('valeur')) {
            $constante->valeur = $request->valeur;
            $constante->est_anormal = $this->detecterAnomalie($constante->type_constante, $request->valeur);
        }
        
        if ($request->has('notes')) {
            $constante->notes = $request->notes;
        }
        
        $constante->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Mesure mise à jour avec succès',
            'data' => $constante
        ]);
    }

    /**
     * ✅ Supprimer une constante
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $constante = ConstanteVitale::where('user_id', $user->id)->findOrFail($id);
        $constante->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Mesure supprimée avec succès'
        ]);
    }

    // ============ MÉTHODES PRIVÉES ============

    private function detecterAnomalie($type, $valeur): bool
    {
        $seuils = [
            'tension_systolique' => ['min' => 90, 'max' => 180],
            'tension_diastolique' => ['min' => 60, 'max' => 120],
            'frequence_cardiaque' => ['min' => 40, 'max' => 150],
            'temperature' => ['min' => 35, 'max' => 40.5],
            'glycemie' => ['min' => 0.5, 'max' => 3.0],
            'saturation_oxygene' => ['min' => 90, 'max' => 100]
        ];

        $seuil = $seuils[$type] ?? ['min' => 0, 'max' => PHP_FLOAT_MAX];
        return $valeur < $seuil['min'] || $valeur > $seuil['max'];
    }

    private function genererAlerte($constante)
    {
        try {
            $typesAlertes = [
                'tension_systolique' => ['haute' => 'tension_elevee', 'basse' => 'tension_basse'],
                'tension_diastolique' => ['haute' => 'tension_elevee', 'basse' => 'tension_basse'],
                'frequence_cardiaque' => ['haute' => 'tachycardie', 'basse' => 'bradycardie'],
                'temperature' => ['haute' => 'fievre', 'basse' => 'hypothermie'],
                'glycemie' => ['haute' => 'hyperglycemie', 'basse' => 'hypoglycemie']
            ];

            $seuils = [
                'tension_systolique' => ['min' => 90, 'max' => 160],
                'tension_diastolique' => ['min' => 60, 'max' => 100],
                'frequence_cardiaque' => ['min' => 50, 'max' => 120],
                'temperature' => ['min' => 36, 'max' => 38.5],
                'glycemie' => ['min' => 0.7, 'max' => 1.8]
            ];

            $type = $constante->type_constante;
            $valeur = $constante->valeur;
            $seuil = $seuils[$type] ?? null;

            if (!$seuil) return;

            $typeAlerte = null;
            $message = '';
            $gravite = 'modere';

            if ($valeur < $seuil['min']) {
                $typeAlerte = $typesAlertes[$type]['basse'] ?? 'personnalise';
                $message = "Votre {$type} est basse : {$valeur} {$constante->unite}. ";
                if ($valeur < $seuil['min'] * 0.7) $gravite = 'eleve';
            } elseif ($valeur > $seuil['max']) {
                $typeAlerte = $typesAlertes[$type]['haute'] ?? 'personnalise';
                $message = "Votre {$type} est élevée : {$valeur} {$constante->unite}. ";
                if ($valeur > $seuil['max'] * 1.3) $gravite = 'eleve';
            }

            if ($typeAlerte && class_exists('App\\Models\\AlerteSante')) {
                $existe = \App\Models\AlerteSante::where('user_id', $constante->user_id)
                    ->where('type_alerte', $typeAlerte)
                    ->where('est_resolue', false)
                    ->where('date_creation', '>=', Carbon::now()->subHours(24))
                    ->exists();

                if (!$existe) {
                    \App\Models\AlerteSante::create([
                        'user_id' => $constante->user_id,
                        'type_alerte' => $typeAlerte,
                        'niveau_gravite' => $gravite,
                        'message' => $message . 'Consultez votre médecin si les symptômes persistent.',
                        'constante_id' => $constante->id,
                        'est_lue' => false,
                        'est_resolue' => false,
                        'date_creation' => Carbon::now()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // ✅ Utilisation correcte de Log avec l'import
            Log::warning('Erreur génération alerte: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Corrélation entre symptômes et constantes vitales
     */
    public function correlationSymptomes(Request $request)
    {
        $user = auth()->user();
        $jours = $request->jours ?? 7;
        $dateDebut = Carbon::now()->subDays($jours);
        
        // Récupérer les symptômes
        $symptomes = Symptome::where('user_id', $user->id)
            ->where('date_enregistrement', '>=', $dateDebut)
            ->orderBy('date_enregistrement', 'asc')
            ->get();
            
        // Récupérer les constantes
        $constantes = ConstanteVitale::where('user_id', $user->id)
            ->where('date_mesure', '>=', $dateDebut)
            ->orderBy('date_mesure', 'asc')
            ->get();
        
        // Grouper par jour
        $correlation = [];
        foreach ($symptomes as $symptome) {
            $date = $symptome->date_enregistrement->toDateString();
            if (!isset($correlation[$date])) {
                $correlation[$date] = [
                    'date' => $date,
                    'symptomes' => [],
                    'constantes' => []
                ];
            }
            $correlation[$date]['symptomes'][] = [
                'description' => $symptome->description,
                'niveau' => $symptome->niveau
            ];
        }
        
        foreach ($constantes as $constante) {
            $date = $constante->date_mesure->toDateString();
            if (isset($correlation[$date])) {
                $correlation[$date]['constantes'][] = [
                    'type' => $constante->type_constante,
                    'valeur' => $constante->valeur,
                    'unite' => $constante->unite
                ];
            }
        }
        
        // Analyse des corrélations
        $insights = $this->analyserCorrelation(array_values($correlation));
        
        return response()->json([
            'success' => true,
            'data' => [
                'correlation' => array_values($correlation),
                'insights' => $insights
            ]
        ]);
    }

    /**
     * ✅ Récupérer les seuils personnalisés de l'utilisateur
     */
    public function getSeuils(Request $request)
    {
        $user = auth()->user();
        
        // Récupérer tous les seuils personnalisés de l'utilisateur
        $seuils = SeuilPersonnalise::where('user_id', $user->id)->get();
        
        // Formater les données
        $formattedSeuils = [];
        foreach ($seuils as $seuil) {
            $formattedSeuils[$seuil->type_constante] = [
                'min_normal' => $seuil->min_normal,
                'max_normal' => $seuil->max_normal,
                'min_alerte' => $seuil->min_alerte,
                'max_alerte' => $seuil->max_alerte,
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $formattedSeuils
        ]);
    }

    /**
     * ✅ Mettre à jour les seuils personnalisés
     */
    public function setSeuils(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_constante' => 'required|string',
            'min_normal' => 'nullable|numeric',
            'max_normal' => 'nullable|numeric',
            'min_alerte' => 'nullable|numeric',
            'max_alerte' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        $seuil = SeuilPersonnalise::updateOrCreate(
            ['user_id' => $user->id, 'type_constante' => $request->type_constante],
            [
                'min_normal' => $request->min_normal,
                'max_normal' => $request->max_normal,
                'min_alerte' => $request->min_alerte,
                'max_alerte' => $request->max_alerte
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Seuils mis à jour avec succès',
            'data' => $seuil
        ]);
    }

    // app/Http/Controllers/Api/ConstantesVitalesController.php

/**
 * ✅ Exporter les constantes en CSV
 */
public function exportCSV(Request $request)
{
    $user = auth()->user();
    
    // ✅ Récupérer les types ou utiliser les types par défaut
    $types = $request->has('types') 
        ? explode(',', $request->types) 
        : ['tension_systolique', 'tension_diastolique', 'frequence_cardiaque', 'temperature', 'glycemie'];
    
    // ✅ Récupérer les données
    $constantes = ConstanteVitale::where('user_id', $user->id)
        ->whereIn('type_constante', $types)
        ->orderBy('date_mesure', 'desc')
        ->get();
    
    // ✅ Vérifier s'il y a des données
    if ($constantes->isEmpty()) {
        return response()->json([
            'message' => 'Aucune donnée à exporter'
        ], 404);
    }
    
    // ✅ Créer le CSV
    $filename = "constantes_vitales_" . date('Y-m-d') . ".csv";
    $handle = fopen('php://temp', 'w+');
    
    // En-têtes
    fputcsv($handle, ['Date', 'Type', 'Valeur', 'Unité', 'Anormal', 'Notes']);
    
    // Données
    foreach ($constantes as $c) {
        fputcsv($handle, [
            $c->date_mesure->format('Y-m-d H:i:s'),
            $c->type_constante,
            $c->valeur,
            $c->unite,
            $c->est_anormal ? 'Oui' : 'Non',
            $c->notes ?? ''
        ]);
    }
    
    rewind($handle);
    $csvContent = stream_get_contents($handle);
    fclose($handle);
    
    // ✅ Retourner le CSV
    return response($csvContent, 200, [
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
        'Content-Length' => strlen($csvContent),
    ]);
}

    /**
     * ✅ Analyse des corrélations
     */
    private function analyserCorrelation($correlation)
    {
        $insights = [];
        $count = count($correlation);
        
        if ($count < 3) {
            $insights[] = "Pas assez de données pour analyser les corrélations.";
            return $insights;
        }
        
        $tensions_elevees = 0;
        $symptomes_maux_tete = 0;
        $days_with_both = 0;
        
        foreach ($correlation as $day) {
            $tension_haute = false;
            $mal_tete = false;
            
            foreach ($day['constantes'] as $c) {
                if (($c['type'] === 'tension_systolique' && $c['valeur'] > 140) ||
                    ($c['type'] === 'tension_diastolique' && $c['valeur'] > 90)) {
                    $tension_haute = true;
                    $tensions_elevees++;
                }
            }
            
            foreach ($day['symptomes'] as $s) {
                $desc = strtolower($s['description']);
                if (str_contains($desc, 'tête') || 
                    str_contains($desc, 'migraine') ||
                    str_contains($desc, 'céphalée')) {
                    $mal_tete = true;
                    $symptomes_maux_tete++;
                }
            }
            
            if ($tension_haute && $mal_tete) {
                $days_with_both++;
            }
        }
        
        if ($days_with_both > 2) {
            $insights[] = "⚠️ Corrélation observée : jours avec tension élevée et maux de tête. Pensez à consulter un médecin.";
        }
        
        if ($count > 0 && $tensions_elevees / $count > 0.4) {
            $insights[] = "📈 Tension élevée détectée sur " . round(($tensions_elevees / $count) * 100) . "% des jours. Surveillance recommandée.";
        }
        
        if (empty($insights)) {
            $insights[] = "✅ Aucune corrélation significative détectée sur la période.";
        }
        
        return $insights;
    }
}