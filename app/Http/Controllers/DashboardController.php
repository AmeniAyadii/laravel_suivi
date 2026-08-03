<?php

namespace App\Http\Controllers;

use App\Models\Medicament;
use App\Models\RendezVous;
use App\Models\Symptome;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Prochain médicament
            $prochainMedicament = Medicament::where('user_id', $user->id)
                ->where('statut', 'actif')
                ->where('prochaine_prise', '>=', now())
                ->orderBy('prochaine_prise')
                ->first();

            // Prochain rendez-vous
            $prochainRendezVous = RendezVous::where('user_id', $user->id)
                ->where('statut', 'à_venir')
                ->where('date_heure', '>=', now())
                ->orderBy('date_heure')
                ->first();

            // Dernier symptôme
            $dernierSymptome = Symptome::where('user_id', $user->id)
                ->orderBy('date_enregistrement', 'desc')
                ->first();

            // Statistiques
            $medicamentsActifs = Medicament::where('user_id', $user->id)
                ->where('statut', 'actif')
                ->count();

            $rendezVousTotal = RendezVous::where('user_id', $user->id)->count();
            $symptomesTotal = Symptome::where('user_id', $user->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'prochain_medicament' => $prochainMedicament,
                    'prochain_rendezvous' => $prochainRendezVous,
                    'dernier_symptome' => $dernierSymptome,
                    'medicaments_actifs' => $medicamentsActifs,
                    'rendezvous_total' => $rendezVousTotal,
                    'symptomes_total' => $symptomesTotal
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
