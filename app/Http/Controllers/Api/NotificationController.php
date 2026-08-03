<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Récupérer toutes les notifications de l'utilisateur
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            Log::info('📥 Notifications récupérées pour l\'utilisateur ' . $user->id);

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $nonLues = Notification::where('user_id', $user->id)
                ->where('lu', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'non_lues' => $nonLues,
                'total' => $notifications->count()
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            $notification->lu = true;
            $notification->save();

            Log::info('📖 Notification ' . $id . ' marquée comme lue');

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur markAsRead: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            
            Notification::where('user_id', $user->id)
                ->where('lu', false)
                ->update(['lu' => true]);

            Log::info('📖 Toutes les notifications marquées comme lues pour l\'utilisateur ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur markAllAsRead: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une notification
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            $notification->delete();

            Log::info('🗑️ Notification ' . $id . ' supprimée');

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer une notification de test
     */
    public function sendTest()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            Log::info('📤 Envoi d\'une notification de test pour l\'utilisateur ' . $user->id);

            $notification = Notification::create([
                'user_id' => $user->id,
                'titre' => '🧪 Notification de test',
                'message' => 'Ceci est une notification de test envoyée depuis votre application !',
                'type' => 'success',
                'lu' => false,
                'date_envoi' => now(),
            ]);

            Log::info('✅ Notification de test créée: ' . $notification->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification de test envoyée',
                'data' => $notification
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur sendTest: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}