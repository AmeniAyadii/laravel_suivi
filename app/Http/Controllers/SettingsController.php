<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Récupérer les paramètres de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Récupérer les paramètres ou créer les valeurs par défaut
        $settings = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'notifications_enabled' => true,
                'medication_reminders' => true,
                'appointment_reminders' => true,
                'health_tips_enabled' => true,
                'data_sync_enabled' => true,
                'biometric_enabled' => false,
                'language' => 'Français',
                'theme' => 'Système',
                'font_size' => 'Moyen',
                'font_size_value' => 1.0,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
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
                    'maladies_chroniques' => $user->maladies_chroniques,
                    'created_at' => $user->created_at,
                ],
                'settings' => $settings,
            ]
        ]);
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'notifications_enabled' => 'sometimes|boolean',
            'medication_reminders' => 'sometimes|boolean',
            'appointment_reminders' => 'sometimes|boolean',
            'health_tips_enabled' => 'sometimes|boolean',
            'data_sync_enabled' => 'sometimes|boolean',
            'biometric_enabled' => 'sometimes|boolean',
            'language' => 'sometimes|string|max:50',
            'theme' => 'sometimes|string|max:50',
            'font_size' => 'sometimes|string|max:50',
            'font_size_value' => 'sometimes|numeric|min:0.5|max:2.0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        $settings->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour',
            'data' => $settings
        ]);
    }
}
