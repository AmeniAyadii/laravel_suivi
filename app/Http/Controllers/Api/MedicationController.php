<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MedicationService;
use App\Models\Medicament;
use App\Models\MedicationTaking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicationController extends Controller
{
    protected MedicationService $medicationService;

    public function __construct(MedicationService $medicationService)
    {
        $this->medicationService = $medicationService;
        // ✅ Optionnel : désactiver temporairement l'auth pour les tests
        // $this->middleware('auth:sanctum')->except(['index', 'store']);
        $this->middleware('auth:sanctum');
    }

    /**
     * ✅ Obtenir l'ID utilisateur avec fallback
     */
    private function getUserId(Request $request): int
    {
        if ($request->user()) {
            return $request->user()->id;
        }
        // ✅ Fallback pour les tests
        return 1;
    }

    /**
     * Ajouter un médicament (avec reconnaissance vocale avancée)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required_without:nom|string',
            'nom' => 'required_without:text|string|max:150',
            'dosage' => 'nullable|string|max:50',
            'stock_actuel' => 'nullable|integer|min:0',
            'horaires_prises' => 'nullable|array',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after:date_debut',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // ✅ Utiliser la méthode getUserId
        $userId = $this->getUserId($request);

        $result = $this->medicationService->addMedication(
            $request->all(),
            $userId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du médicament',
                'error' => $result['error'] ?? null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'medicament' => $result['medicament'],
                'interactions' => $result['interactions'],
                'has_interactions' => !empty($result['interactions']),
            ],
        ]);
    }

    /**
     * Vérifier les interactions avant ajout
     */
    public function checkInteractions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $this->getUserId($request);
        $tempMedicament = new Medicament(['nom' => $request->nom]);
        $interactions = $this->medicationService->checkInteractions(
            $tempMedicament,
            $userId
        );

        return response()->json([
            'success' => true,
            'data' => [
                'interactions' => $interactions,
                'has_interactions' => !empty($interactions),
            ],
        ]);
    }

    /**
     * Vérifier les alertes de stock
     */
    public function checkStockAlerts(Request $request)
    {
        $userId = $this->getUserId($request);
        $alerts = $this->medicationService->checkStockAlerts($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => $alerts,
                'has_alerts' => !empty($alerts),
            ],
        ]);
    }

    

    /**
     * Enregistrer une prise (observance)
     */
    public function recordTaking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'taking_id' => 'required|exists:medication_takings,id',
            'taken' => 'required|boolean',
            'source' => 'nullable|string|in:manual,voice,auto',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->medicationService->recordTaking(
            $request->taking_id,
            $request->taken,
            $request->source ?? 'manual'
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['taking'],
        ]);
    }

    /**
     * Obtenir le résumé d'observance
     */
    public function getObservanceSummary(Request $request)
    {
        $period = $request->period ?? 'week';
        $userId = $this->getUserId($request);
        
        $summary = $this->medicationService->getObservanceSummary(
            $userId,
            $period
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Générer un message de renouvellement
     */
    public function generateRenewalMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medicament_id' => 'required|exists:medicaments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = $this->medicationService->generateRenewalMessage(
            $request->medicament_id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
            ],
        ]);
    }

    /**
     * Obtenir les prises du jour
     */
    public function getTodayTakings(Request $request)
    {
        $userId = $this->getUserId($request);
        $takings = $this->medicationService->getTodayTakings($userId);

        return response()->json([
            'success' => true,
            'data' => $takings,
        ]);
    }

    /**
     * ✅ Obtenir la liste des médicaments (CORRIGÉ)
     */
    public function index(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            
            // ✅ Vérifier si la relation 'prises' existe
            $medications = Medicament::where('user_id', $userId)
                ->orderBy('nom')
                ->get();

            // ✅ Ajouter les prises manuellement si nécessaire
            foreach ($medications as $medication) {
                $medication->today_takings = MedicationTaking::where('medicament_id', $medication->id)
                    ->whereDate('prise_prevue', now()->format('Y-m-d'))
                    ->orderBy('prise_prevue')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $medications,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir un médicament spécifique
     */
    // app/Http/Controllers/Api/MedicationController.php

/**
 * Obtenir un médicament spécifique
 */
public function show(Request $request, int $id)
{
    try {
        $userId = $this->getUserId($request);
        
        // ✅ Vérifier que l'ID est bien un entier
        if (!is_numeric($id) || $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID invalide',
            ], 400);
        }
        
        $medicament = Medicament::where('user_id', $userId)
            ->findOrFail((int)$id);

        // Ajouter les prises manuellement
        $medicament->prises = MedicationTaking::where('medicament_id', $id)
            ->where('prise_prevue', '>=', now()->subWeek())
            ->orderBy('prise_prevue')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $medicament,
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Médicament non trouvé',
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Mettre à jour un médicament
     */
    public function update(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            
            $medicament = Medicament::where('user_id', $userId)
                ->findOrFail($id);

            $validated = $request->validate([
                'nom' => 'sometimes|string|max:150',
                'dosage' => 'sometimes|string|max:50',
                'stock_actuel' => 'sometimes|integer|min:0',
                'statut' => 'sometimes|in:actif,inactif,termine',
                'rappel_actif' => 'sometimes|boolean',
                'horaires_prises' => 'sometimes|array',
            ]);

            $medicament->update($validated);

            // Mettre à jour la prochaine prise si les horaires changent
            if (isset($validated['horaires_prises'])) {
                $medicament->update([
                    'prochaine_prise' => $this->medicationService->calculateNextDose(
                        $validated['horaires_prises']
                    ),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Médicament mis à jour avec succès',
                'data' => $medicament,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un médicament
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            
            $medicament = Medicament::where('user_id', $userId)
                ->findOrFail($id);

            $medicament->delete();

            return response()->json([
                'success' => true,
                'message' => 'Médicament supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marquer une prise comme prise (raccourci vocal)
     */
    public function markTakingAsTaken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medicament_id' => 'required|exists:medicaments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Trouver la prochaine prise non prise
        $taking = MedicationTaking::where('medicament_id', $request->medicament_id)
            ->where('statut', 'prevue')
            ->whereDate('prise_prevue', now()->format('Y-m-d'))
            ->orderBy('prise_prevue')
            ->first();

        if (!$taking) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune prise à faire pour ce médicament aujourd\'hui.',
            ], 404);
        }

        $result = $this->medicationService->recordTaking(
            $taking->id,
            true,
            'voice'
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'data' => $result['taking'] ?? null,
        ]);
    }
}