<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicationTaking;
use App\Models\Medicament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MedicationTakingController extends Controller
{
    /**
     * Liste des prises de médicaments
     */
    public function index(Request $request)
    {
        $query = MedicationTaking::where('user_id', Auth::id());

        // Filtrer par statut
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtrer par date
        if ($request->has('date')) {
            $query->whereDate('prise_prevue', $request->date);
        }

        $takings = $query->with('medicament')
                        ->orderBy('prise_prevue', 'desc')
                        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $takings
        ]);
    }

    /**
     * Créer une prise de médicament
     */
    public function store(Request $request)
    {
        $request->validate([
            'medicament_id' => 'required|exists:medicaments,id',
            'prise_prevue' => 'required|date|after:now',
        ]);

        // Vérifier que le médicament appartient à l'utilisateur
        $medicament = Medicament::where('id', $request->medicament_id)
                                ->where('user_id', Auth::id())
                                ->firstOrFail();

        $taking = MedicationTaking::create([
            'medicament_id' => $request->medicament_id,
            'user_id' => Auth::id(),
            'prise_prevue' => $request->prise_prevue,
            'statut' => 'prevue',
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prise de médicament ajoutée avec succès',
            'data' => $taking->load('medicament')
        ], 201);
    }

    /**
     * Enregistrer la prise d'un médicament
     */
    public function take(Request $request, $id)
    {
        $taking = MedicationTaking::where('id', $id)
                                  ->where('user_id', Auth::id())
                                  ->firstOrFail();

        $taking->update([
            'prise_reelle' => now(),
            'statut' => 'prise'
        ]);

        // Mettre à jour la prochaine prise du médicament
        $medicament = $taking->medicament;
        // Logique pour calculer la prochaine prise...

        return response()->json([
            'success' => true,
            'message' => 'Médicament pris avec succès',
            'data' => $taking
        ]);
    }

    /**
     * Marquer une prise comme oubliée
     */
    public function miss(Request $request, $id)
    {
        $taking = MedicationTaking::where('id', $id)
                                  ->where('user_id', Auth::id())
                                  ->firstOrFail();

        $taking->update([
            'statut' => 'oubliee',
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prise marquée comme oubliée',
            'data' => $taking
        ]);
    }

    /**
     * Statistiques des prises
     */
    public function statistics()
    {
        $userId = Auth::id();

        $total = MedicationTaking::where('user_id', $userId)->count();
        $taken = MedicationTaking::where('user_id', $userId)
                                 ->where('statut', 'prise')
                                 ->count();
        $missed = MedicationTaking::where('user_id', $userId)
                                  ->where('statut', 'oubliee')
                                  ->count();
        $pending = MedicationTaking::where('user_id', $userId)
                                   ->where('statut', 'prevue')
                                   ->count();

        // Taux d'observance
        $adherenceRate = $total > 0 ? round(($taken / $total) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'taken' => $taken,
                'missed' => $missed,
                'pending' => $pending,
                'adherence_rate' => $adherenceRate
            ]
        ]);
    }
}