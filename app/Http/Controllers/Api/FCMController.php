<?php
// app/Http/Controllers/Api/FCMController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FCMController extends Controller
{
    // Enregistrer le token FCM
    public function register(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->token;
        $user->fcm_token_updated_at = now();
        $user->save();

        Log::info('Token FCM enregistré pour l\'utilisateur ' . $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Token enregistré',
        ]);
    }

    // Supprimer le token FCM
    public function unregister(Request $request)
    {
        $user = $request->user();
        $user->fcm_token = null;
        $user->fcm_token_updated_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Token supprimé',
        ]);
    }
}