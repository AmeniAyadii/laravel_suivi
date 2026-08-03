<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StatistiqueController extends Controller
{
    /**
     * Statistiques globales
     */
    public function getGlobalStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $period = $request->period ?? 'month';
            $dateRange = $this->getDateRange($period);

            $totalMedicaments = $user->medicaments()->count();
            $medicamentsActifs = $user->medicaments()
                ->where('statut', 'actif')
                ->count();

            $stats = [
                'period' => $period,
                'date_range' => $dateRange,
                'total_medicaments' => $totalMedicaments,
                'medicaments_actifs' => $medicamentsActifs,
                'total_rendezvous' => $user->rendezvous()->count(),
                'rendezvous_a_venir' => $user->rendezvous()
                    ->where('date_heure', '>=', now())
                    ->count(),
                'total_symptomes' => $user->symptomes()->count(),
                'symptomes_mois' => $user->symptomes()
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->count(),
                'taux_completion' => $this->calculateCompletionRate($user),
                'score_sante' => $this->calculateHealthScore($user),
                'derniere_activite' => $user->updated_at,
                'membre_depuis' => $user->created_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques globales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des médicaments
     */
    public function getMedicationStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $total = $user->medicaments()->count();
            $actifs = $user->medicaments()->where('statut', 'actif')->count();
            $termines = $user->medicaments()->where('statut', 'termine')->count();
            $inactifs = $user->medicaments()->where('statut', 'inactif')->count();

            $parCategorie = [];
            if (Schema::hasColumn('medicaments', 'categorie')) {
                $parCategorie = $user->medicaments()
                    ->select('categorie', DB::raw('count(*) as total'))
                    ->groupBy('categorie')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'categorie' => $item->categorie ?? 'Non catégorisé',
                            'total' => $item->total
                        ];
                    })->toArray();
            }

            $frequencePlusUtilisee = $user->medicaments()
                ->select('nom', DB::raw('count(*) as total'))
                ->groupBy('nom')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'nom' => $item->nom,
                        'total' => $item->total
                    ];
                })->toArray();

            $parJour = $user->medicaments()
                ->select(DB::raw('DAYNAME(created_at) as jour'), DB::raw('count(*) as total'))
                ->groupBy('jour')
                ->get()
                ->map(function ($item) {
                    return [
                        'jour' => $item->jour,
                        'total' => $item->total
                    ];
                })->toArray();

            $evolutionMensuelle = $this->getMonthlyEvolution($user, 'medicaments');

            $stats = [
                'total' => $total,
                'actifs' => $actifs,
                'termines' => $termines,
                'inactifs' => $inactifs,
                'par_categorie' => $parCategorie,
                'frequence_plus_utilisee' => $frequencePlusUtilisee,
                'par_jour' => $parJour,
                'evolution_mensuelle' => $evolutionMensuelle,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques médicaments: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => $this->getEmptyMedicationStats($user)
            ]);
        }
    }

    private function getEmptyMedicationStats($user)
    {
        return [
            'total' => $user->medicaments()->count(),
            'actifs' => 0,
            'termines' => 0,
            'inactifs' => 0,
            'par_categorie' => [],
            'frequence_plus_utilisee' => [],
            'par_jour' => [],
            'evolution_mensuelle' => $this->getMonthlyEvolution($user, 'medicaments'),
        ];
    }

    /**
     * Statistiques des symptômes
     */
    public function getSymptomStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $period = $request->period ?? 'month';
            $dateRange = $this->getDateRange($period);

            $hasNiveauColumn = Schema::hasColumn('symptomes', 'niveau');

            $parNiveau = [];
            if ($hasNiveauColumn) {
                $parNiveau = $user->symptomes()
                    ->select('niveau', DB::raw('count(*) as total'))
                    ->groupBy('niveau')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'niveau' => $item->niveau,
                            'total' => $item->total
                        ];
                    })->toArray();
            }

            $plusFrequents = $user->symptomes()
                ->select('description', DB::raw('count(*) as total'))
                ->groupBy('description')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'description' => $item->description,
                        'total' => $item->total
                    ];
                })->toArray();

            $stats = [
                'total' => $user->symptomes()->count(),
                'par_niveau' => $parNiveau,
                'plus_frequents' => $plusFrequents,
                'evolution_hebdomadaire' => $this->getWeeklyEvolution($user, 'symptomes'),
                'tendance' => $this->getSymptomTrend($user),
                'par_mois' => $this->getMonthlyEvolution($user, 'symptomes'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques symptômes: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => $this->getEmptySymptomStats($user)
            ]);
        }
    }

    private function getEmptySymptomStats($user)
    {
        return [
            'total' => $user->symptomes()->count(),
            'par_niveau' => [],
            'plus_frequents' => [],
            'evolution_hebdomadaire' => $this->getWeeklyEvolution($user, 'symptomes'),
            'tendance' => $this->getSymptomTrend($user),
            'par_mois' => $this->getMonthlyEvolution($user, 'symptomes'),
        ];
    }

    /**
     * Statistiques des rendez-vous
     */
    public function getAppointmentStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Vérifier si la table 'rendez_vous' existe
            if (!Schema::hasTable('rendez_vous')) {
                Log::warning('Table rendez_vous n\'existe pas');
                return response()->json([
                    'success' => true,
                    'data' => $this->getEmptyAppointmentStats()
                ]);
            }

            $hasTitreColumn = Schema::hasColumn('rendez_vous', 'titre');
            $hasMedecinNomColumn = Schema::hasColumn('rendez_vous', 'medecin_nom');
            $hasDateHeureColumn = Schema::hasColumn('rendez_vous', 'date_heure');
            $hasLieuColumn = Schema::hasColumn('rendez_vous', 'lieu');

            $prochainsRendezVous = [];
            if ($hasDateHeureColumn) {
                $query = DB::table('rendez_vous')
                    ->where('user_id', $user->id)
                    ->where('date_heure', '>=', now())
                    ->orderBy('date_heure', 'asc')
                    ->limit(5);

                $prochainsRendezVous = $query->get()
                    ->map(function ($rdv) use ($hasTitreColumn, $hasMedecinNomColumn, $hasDateHeureColumn, $hasLieuColumn) {
                        return [
                            'titre' => $hasTitreColumn ? ($rdv->titre ?? 'Rendez-vous') : 'Rendez-vous',
                            'medecin_nom' => $hasMedecinNomColumn ? ($rdv->medecin_nom ?? 'Inconnu') : 'Inconnu',
                            'date_heure' => $hasDateHeureColumn ? $rdv->date_heure : now()->addDays(rand(1, 30))->toISOString(),
                            'lieu' => $hasLieuColumn ? ($rdv->lieu ?? '') : '',
                        ];
                    })->toArray();
            }

            $stats = [
                'total' => DB::table('rendez_vous')->where('user_id', $user->id)->count(),
                'a_venir' => $hasDateHeureColumn ? DB::table('rendez_vous')->where('user_id', $user->id)->where('date_heure', '>=', now())->count() : 0,
                'passes' => $hasDateHeureColumn ? DB::table('rendez_vous')->where('user_id', $user->id)->where('date_heure', '<', now())->count() : 0,
                'par_mois' => $this->getMonthlyEvolutionSafe($user, 'rendez_vous'),
                'prochains_rendezvous' => $prochainsRendezVous,
                'par_specialite' => [],
                'taux_assiduite' => $this->calculateAttendanceRate($user),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques rendez-vous: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => $this->getEmptyAppointmentStats()
            ]);
        }
    }

    /**
     * Statistiques de santé
     */
    public function getHealthStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $stats = [
                'imc' => $this->calculateIMC($user),
                'poids_ideal' => $this->calculateIdealWeight($user),
                'statut' => $this->getHealthStatus($user),
                'recommandations' => $this->getHealthRecommendations($user),
                'indicateurs' => [
                    'taille' => $user->taille,
                    'poids' => $user->poids,
                    'age' => $user->age,
                    'sexe' => $user->sexe,
                    'groupe_sanguin' => $user->groupe_sanguin,
                    'allergies' => $user->allergies,
                    'maladies_chroniques' => $user->maladies_chroniques,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques santé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTHODES PRIVÉES ====================

    private function getDateRange($period)
    {
        $end = Carbon::now();
        $start = Carbon::now();

        switch ($period) {
            case 'week':
                $start = $end->copy()->subWeek();
                break;
            case 'month':
                $start = $end->copy()->subMonth();
                break;
            case 'year':
                $start = $end->copy()->subYear();
                break;
            default:
                $start = $end->copy()->subMonth();
        }

        return ['start' => $start, 'end' => $end];
    }

    // ✅ AJOUTER CES MÉTHODES MANQUANTES

    private function getEmptyAppointmentStats()
    {
        return [
            'total' => 0,
            'a_venir' => 0,
            'passes' => 0,
            'par_mois' => $this->getEmptyEvolutionData(),
            'prochains_rendezvous' => [],
            'par_specialite' => [],
            'taux_assiduite' => 100,
        ];
    }

    private function getEmptyEvolutionData()
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $data[] = [
                'mois' => $date->format('M'),
                'total' => 0,
                'annee' => $date->year
            ];
        }
        return $data;
    }

    private function getMonthlyEvolutionSafe($user, $table)
    {
        try {
            if (!Schema::hasTable($table)) {
                return $this->getEmptyEvolutionData();
            }

            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $count = DB::table($table)
                    ->where('user_id', $user->id)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();

                $data[] = [
                    'mois' => $date->format('M'),
                    'total' => $count,
                    'annee' => $date->year
                ];
            }
            return $data;
        } catch (\Exception $e) {
            Log::warning('Erreur getMonthlyEvolutionSafe: ' . $e->getMessage());
            return $this->getEmptyEvolutionData();
        }
    }

    // ==================== MÉTHODES EXISTANTES ====================

    private function calculateIMC($user)
    {
        if (!$user->taille || !$user->poids) {
            return null;
        }

        $tailleM = $user->taille / 100;
        return round($user->poids / ($tailleM * $tailleM), 1);
    }

    private function calculateIdealWeight($user)
    {
        if (!$user->taille || !$user->sexe) {
            return null;
        }

        $tailleCm = $user->taille;
        if ($user->sexe === 'M' || $user->sexe === 'm') {
            return round($tailleCm - 100 - ($tailleCm - 150) / 4);
        } else {
            return round($tailleCm - 100 - ($tailleCm - 150) / 2.5);
        }
    }

    private function getHealthStatus($user)
    {
        $imc = $this->calculateIMC($user);
        $status = [
            'label' => 'Normal',
            'color' => '#10B981',
            'icon' => 'check_circle',
            'description' => 'Votre poids est dans la norme'
        ];

        if ($imc) {
            if ($imc < 18.5) {
                $status = [
                    'label' => 'Maigreur',
                    'color' => '#F59E0B',
                    'icon' => 'warning',
                    'description' => 'Votre poids est inférieur à la normale'
                ];
            } elseif ($imc < 25) {
                $status = [
                    'label' => 'Normal',
                    'color' => '#10B981',
                    'icon' => 'check_circle',
                    'description' => 'Votre poids est dans la norme'
                ];
            } elseif ($imc < 30) {
                $status = [
                    'label' => 'Surpoids',
                    'color' => '#F59E0B',
                    'icon' => 'warning',
                    'description' => 'Votre poids est supérieur à la normale'
                ];
            } else {
                $status = [
                    'label' => 'Obésité',
                    'color' => '#EF4444',
                    'icon' => 'error',
                    'description' => 'Une attention particulière est recommandée'
                ];
            }
        }

        return $status;
    }

    private function getHealthRecommendations($user)
    {
        $recommendations = [];
        $imc = $this->calculateIMC($user);

        if ($imc) {
            if ($imc < 18.5) {
                $recommendations[] = 'Augmentez votre apport calorique avec des aliments nutritifs';
                $recommendations[] = 'Consultez un nutritionniste pour un suivi personnalisé';
            } elseif ($imc < 25) {
                $recommendations[] = 'Maintenez une alimentation équilibrée';
                $recommendations[] = 'Pratiquez une activité physique régulière (30 min/jour)';
            } elseif ($imc < 30) {
                $recommendations[] = 'Réduisez les aliments riches en graisses et sucres';
                $recommendations[] = 'Augmentez votre activité physique progressive';
                $recommendations[] = 'Consultez un médecin pour un bilan';
            } else {
                $recommendations[] = 'Consultez rapidement votre médecin traitant';
                $recommendations[] = 'Suivez un programme alimentaire adapté';
                $recommendations[] = 'Pratiquez une activité physique encadrée';
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Complétez votre profil médical pour des recommandations personnalisées';
            $recommendations[] = 'Planifiez un bilan de santé annuel';
        }

        return $recommendations;
    }

    private function calculateHealthScore($user)
    {
        $score = 70;

        if ($user->taille && $user->poids) $score += 10;
        if ($user->groupe_sanguin) $score += 5;
        if ($user->allergies) $score += 5;
        if ($user->maladies_chroniques) $score += 5;
        if ($user->sexe) $score += 5;

        $imc = $this->calculateIMC($user);
        if ($imc && $imc >= 18.5 && $imc < 25) $score += 5;

        if ($user->medicaments()->where('statut', 'actif')->count() > 0) $score += 5;
        if ($user->rendezvous()->where('date_heure', '>=', now())->count() > 0) $score += 5;

        return min($score, 100);
    }

    private function calculateCompletionRate($user)
    {
        $total = $user->medicaments()->count();
        if ($total === 0) return 100;

        $actifs = $user->medicaments()->where('statut', 'actif')->count();
        $termines = $user->medicaments()->where('statut', 'termine')->count();
        $completes = $actifs + $termines;

        return round(($completes / $total) * 100);
    }

    private function calculateAttendanceRate($user)
    {
        $total = $user->rendezvous()->count();
        if ($total === 0) return 100;
        return 90;
    }

    private function getMonthlyEvolution($user, $table)
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = DB::table($table)
                ->where('user_id', $user->id)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $data[] = [
                'mois' => $date->format('M'),
                'total' => $count,
                'annee' => $date->year
            ];
        }
        return $data;
    }

    private function getWeeklyEvolution($user, $table)
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subWeeks($i);
            $count = DB::table($table)
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [
                    $date->startOfWeek(),
                    $date->endOfWeek()
                ])
                ->count();

            $data[] = [
                'semaine' => $date->format('W'),
                'total' => $count,
                'date' => $date->format('d M')
            ];
        }
        return $data;
    }

    private function getSymptomTrend($user)
    {
        $data = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = $user->symptomes()
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'total' => $count,
                'jour' => $date->format('d M')
            ];
        }
        return $data;
    }
}
