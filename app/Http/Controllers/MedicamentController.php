<?php

namespace App\Http\Controllers;

use App\Models\Medicament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicamentController extends Controller
{
    /**
     * Liste des médicaments de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $medicaments = Medicament::where('user_id', $userId)
                ->where('statut', 'actif')
                ->orderBy('prochaine_prise')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $medicaments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prochain médicament à prendre
     */
    public function next(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $medicament = Medicament::where('user_id', $userId)
                ->where('statut', 'actif')
                ->where('prochaine_prise', '>=', now())
                ->orderBy('prochaine_prise')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $medicament
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter un nouveau médicament
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:150',
            'dosage' => 'nullable|string|max:50',
            'frequence' => 'nullable|string|max:100',
            'prochaine_prise' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->id;

            $medicament = Medicament::create([
                'user_id' => $userId,
                'nom' => $request->nom,
                'dosage' => $request->dosage,
                'frequence' => $request->frequence,
                'prochaine_prise' => $request->prochaine_prise,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Médicament ajouté avec succès',
                'data' => $medicament
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un médicament
     */
    public function update(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $medicament = Medicament::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$medicament) {
                return response()->json([
                    'success' => false,
                    'error' => 'Médicament non trouvé'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nom' => 'sometimes|string|max:150',
                'dosage' => 'nullable|string|max:50',
                'frequence' => 'nullable|string|max:100',
                'prochaine_prise' => 'nullable|date',
                'statut' => 'nullable|in:actif,inactif,termine',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $medicament->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Médicament mis à jour',
                'data' => $medicament
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un médicament
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $medicament = Medicament::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$medicament) {
                return response()->json([
                    'success' => false,
                    'error' => 'Médicament non trouvé'
                ], 404);
            }

            $medicament->delete();

            return response()->json([
                'success' => true,
                'message' => 'Médicament supprimé'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
