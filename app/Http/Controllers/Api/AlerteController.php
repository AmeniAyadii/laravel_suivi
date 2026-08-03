<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlerteSante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlerteController extends Controller
{
    /**
     * Récupérer toutes les alertes de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = AlerteSante::where('user_id', $user->id);
        
        // Filtrer par non lues
        if ($request->has('non_lues')) {
            $query->where('est_lue', false);
        }
        
        // Filtrer par résolues
        if ($request->has('resolues')) {
            $query->where('est_resolue', true);
        }
        
        $alertes = $query->orderBy('date_creation', 'desc')->get();
        
        return response()->json([
            'data' => $alertes
        ]);
    }

    /**
     * Marquer une alerte comme lue
     */
    public function marquerLue($id)
    {
        $user = Auth::user();
        $alerte = AlerteSante::where('user_id', $user->id)->findOrFail($id);
        
        $alerte->est_lue = true;
        $alerte->save();
        
        return response()->json([
            'message' => 'Alerte marquée comme lue',
            'data' => $alerte
        ]);
    }

    /**
     * Marquer une alerte comme résolue
     */
    public function marquerResolue($id)
    {
        $user = Auth::user();
        $alerte = AlerteSante::where('user_id', $user->id)->findOrFail($id);
        
        $alerte->est_resolue = true;
        $alerte->date_resolution = now();
        $alerte->save();
        
        return response()->json([
            'message' => 'Alerte marquée comme résolue',
            'data' => $alerte
        ]);
    }

    /**
     * Supprimer une alerte
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $alerte = AlerteSante::where('user_id', $user->id)->findOrFail($id);
        
        $alerte->delete();
        
        return response()->json([
            'message' => 'Alerte supprimée avec succès'
        ]);
    }
}