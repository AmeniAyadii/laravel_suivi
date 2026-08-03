<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class EmergencyAlertController extends Controller
{
    /**
     * Créer une alerte d'urgence
     */
    public function store(Request $request)
    {
        $request->validate([
            'symptomes' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'adresse' => 'nullable|string',
        ]);

        $alert = EmergencyAlert::create([
            'user_id' => Auth::id(),
            'symptomes' => $request->symptomes,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'adresse' => $request->adresse,
            'statut' => 'envoyee',
        ]);

        // Notifier les contacts d'urgence
        $this->notifyEmergencyContacts($alert);

        return response()->json([
            'success' => true,
            'message' => 'Alerte d\'urgence envoyée',
            'data' => $alert
        ], 201);
    }

    /**
     * Mettre à jour le statut d'une alerte
     */
    public function updateStatus(Request $request, $id)
    {
        $alert = EmergencyAlert::where('id', $id)
                               ->where('user_id', Auth::id())
                               ->firstOrFail();

        $request->validate([
            'statut' => 'required|in:en_cours,terminee,annulee'
        ]);

        $alert->update([
            'statut' => $request->statut,
            'resolue_a' => $request->statut === 'terminee' ? now() : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'data' => $alert
        ]);
    }

    /**
     * Notifier les contacts d'urgence
     */
    private function notifyEmergencyContacts($alert)
    {
        $user = User::find($alert->user_id);
        
        if ($user->contact_urgence_telephone) {
            // Logique pour envoyer un SMS
            // Vous pouvez utiliser Twilio, Vonage, etc.
            
            // Ou créer une notification
            $contacts = User::where('id', $alert->user_id)->get();
            
            Notification::send($contacts, new \App\Notifications\EmergencyAlert($alert));
        }
    }
}