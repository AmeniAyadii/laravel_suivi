<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Models\Medicament;
use App\Models\RendezVous;
use App\Models\Symptome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Récupérer tous les rapports de l'utilisateur
     * GET /api/reports
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $reports = Report::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reports->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'title' => $report->title,
                        'type' => $report->type ?? 'custom',
                        'type_label' => $this->getTypeLabel($report->type ?? 'custom'),
                        'status' => $report->status ?? 'pending',
                        'status_label' => $this->getStatusLabel($report->status ?? 'pending'),
                        'date_start' => $report->date_start,
                        'date_end' => $report->date_end,
                        'data' => $report->data,
                        'file_path' => $report->file_path,
                        'created_at' => $report->created_at,
                        'updated_at' => $report->updated_at,
                    ];
                })
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rapports: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un rapport spécifique
     * GET /api/reports/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $report = Report::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $report
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer un rapport
     * POST /api/reports/generate
     */
    public function generate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_start' => 'required|date',
                'date_end' => 'required|date|after_or_equal:date_start',
                'type' => 'required|in:monthly,quarterly,annual,custom'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $dateStart = Carbon::parse($request->date_start);
            $dateEnd = Carbon::parse($request->date_end);

            // Récupérer les données
            $medicaments = Medicament::where('user_id', $user->id)
                ->whereBetween('created_at', [$dateStart, $dateEnd])
                ->get();

            $symptomes = Symptome::where('user_id', $user->id)
                ->whereBetween('created_at', [$dateStart, $dateEnd])
                ->get();

            $rendezVous = RendezVous::where('user_id', $user->id)
                ->whereBetween('created_at', [$dateStart, $dateEnd])
                ->get();

            // Statistiques
            $stats = [
                'medicaments_total' => $medicaments->count(),
                'medicaments_actifs' => $medicaments->where('statut', 'actif')->count(),
                'symptomes_total' => $symptomes->count(),
                'rendezvous_total' => $rendezVous->count(),
                'rendezvous_effectues' => $rendezVous->where('statut', 'effectue')->count(),
                'symptomes_par_jour' => $symptomes->groupBy(function($item) {
                    return Carbon::parse($item->created_at)->format('Y-m-d');
                })->map(function($items) {
                    return $items->count();
                }),
            ];

            // Créer le rapport
            $report = Report::create([
                'user_id' => $user->id,
                'title' => $this->generateTitle($request->type, $dateStart, $dateEnd),
                'type' => $request->type,
                'status' => 'completed',
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'data' => [
                    'medicaments' => $medicaments->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'nom' => $m->nom,
                            'dosage' => $m->dosage,
                            'frequence' => $m->frequence,
                            'statut' => $m->statut ?? 'actif',
                            'created_at' => $m->created_at,
                        ];
                    }),
                    'symptomes' => $symptomes->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'description' => $s->description,
                            'niveau' => $s->niveau,
                            'type' => $s->type,
                            'status' => $s->status,
                            'created_at' => $s->created_at,
                        ];
                    }),
                    'rendezvous' => $rendezVous->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'titre' => $r->titre,
                            'medecin_nom' => $r->medecin_nom,
                            'date_heure' => $r->date_heure,
                            'lieu' => $r->lieu,
                            'statut' => $r->statut ?? 'planifié',
                        ];
                    }),
                    'stats' => $stats,
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'email' => $user->email,
                        'age' => $user->age,
                        'sexe' => $user->sexe,
                        'taille' => $user->taille,
                        'poids' => $user->poids,
                        'groupe_sanguin' => $user->groupe_sanguin,
                        'allergies' => $user->allergies,
                        'maladies_chroniques' => $user->maladies_chroniques
                    ],
                    'periode' => [
                        'debut' => $dateStart->format('Y-m-d H:i:s'),
                        'fin' => $dateEnd->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rapport généré avec succès',
                'data' => $report
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un rapport
     * DELETE /api/reports/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $report = Report::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            // Supprimer le fichier PDF si existe
            if ($report->file_path && Storage::disk('public')->exists($report->file_path)) {
                Storage::disk('public')->delete($report->file_path);
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rapport supprimé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'un rapport
     * PUT /api/reports/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,completed,failed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $report = Report::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            $report->status = $request->status;
            $report->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => $report
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Télécharger un rapport en PDF
     * GET /api/reports/{id}/download
     */
    public function download(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $report = Report::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            if (!$report->file_path || !Storage::disk('public')->exists($report->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier PDF non trouvé'
                ], 404);
            }

            return Storage::disk('public')->download(
                $report->file_path,
                $report->title . '.pdf'
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer un titre pour le rapport
     */
    private function generateTitle($type, $dateStart, $dateEnd)
    {
        $typeLabels = [
            'monthly' => 'Rapport Mensuel',
            'quarterly' => 'Rapport Trimestriel',
            'annual' => 'Rapport Annuel',
            'custom' => 'Rapport Personnalisé',
        ];
        
        $label = $typeLabels[$type] ?? 'Rapport';
        $start = $dateStart->format('d/m/Y');
        $end = $dateEnd->format('d/m/Y');
        
        return "$label ($start - $end)";
    }

    /**
     * Libellé du type
     */
    private function getTypeLabel($type)
    {
        $labels = [
            'monthly' => 'Mensuel',
            'quarterly' => 'Trimestriel',
            'annual' => 'Annuel',
            'custom' => 'Personnalisé',
        ];
        return $labels[$type] ?? $type;
    }

    /**
     * Libellé du statut
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'En attente',
            'completed' => 'Terminé',
            'failed' => 'Échoué',
            'generated' => 'Généré',
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
        ];
        return $labels[$status] ?? $status;
    }
}