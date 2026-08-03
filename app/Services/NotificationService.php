<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\RendezVous;
use App\Models\Medicament;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; // ✅ AJOUTER CETTE IMPORTATION
use App\Events\NotificationEvent; // ✅ AJOUTER CETTE IMPORTATION

class NotificationService
{
    // ==================== CRÉATION DE NOTIFICATIONS ====================

    /**
     * Créer une notification pour un utilisateur
     */
    public static function create(
        $userId,
        string $titre,
        string $message,
        string $type = 'info',
        ?string $icon = null,
        ?string $couleur = null,
        ?array $data = null,
        ?string $lien = null
    ) {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('Utilisateur non trouvé pour la notification', ['user_id' => $userId]);
                return null;
            }

            $icon = $icon ?? self::getIconForType($type);
            $couleur = $couleur ?? self::getColorForType($type);

            $notification = Notification::create([
                'user_id' => $userId,
                'titre' => $titre,
                'message' => $message,
                'type' => $type,
                'icon' => $icon,
                'couleur' => $couleur,
                'data' => $data,
                'lien' => $lien,
                'lu' => false,
                'date_envoi' => now(),
            ]);

            try {
                event(new NotificationEvent($notification));
            } catch (\Exception $e) {
                Log::warning('Erreur lors du déclenchement de l\'événement: ' . $e->getMessage());
            }

            Log::info('Notification créée', [
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'titre' => $titre
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('Erreur création notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Créer une notification pour tous les utilisateurs
     */
    public static function createForAll(
        string $titre,
        string $message,
        string $type = 'info',
        ?string $icon = null,
        ?string $couleur = null
    ) {
        $users = User::all();
        $notifications = [];

        foreach ($users as $user) {
            $notification = self::create(
                $user->id,
                $titre,
                $message,
                $type,
                $icon,
                $couleur
            );
            if ($notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Créer une notification pour un groupe d'utilisateurs
     */
    public static function createForUsers(
        array $userIds,
        string $titre,
        string $message,
        string $type = 'info'
    ) {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notification = self::create($userId, $titre, $message, $type);
            if ($notification) {
                $notifications[] = $notification;
            }
        }
        return $notifications;
    }

    // ==================== ICÔNES ET COULEURS ====================

    private static function getIconForType(string $type): string
    {
        return match ($type) {
            'success' => 'check_circle',
            'warning' => 'warning',
            'error' => 'error',
            'appointment' => 'calendar_today',
            'medication' => 'medication',
            'reminder' => 'notifications',
            default => 'info',
        };
    }

    private static function getColorForType(string $type): string
    {
        return match ($type) {
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'error' => '#EF4444',
            'appointment' => '#3B82F6',
            'medication' => '#8B5CF6',
            'reminder' => '#F59E0B',
            default => '#3B82F6',
        };
    }

    // ==================== VÉRIFICATION DES RAPPELS ====================

    /**
     * Vérifier les rendez-vous et envoyer des rappels
     */
    public function checkAppointments()
    {
        Log::info('📅 Vérification des rendez-vous...');
        
        // Vérifier si la table rendez_vous existe
        if (!Schema::hasTable('rendez_vous')) {
            Log::warning('⚠️ La table rendez_vous n\'existe pas encore');
            return;
        }
        
        $now = Carbon::now();
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        try {
            $appointments = RendezVous::whereDate('date_heure', $today)
                ->orWhereDate('date_heure', $tomorrow)
                ->with('user')
                ->get();

            Log::info('📊 ' . $appointments->count() . ' rendez-vous trouvés');

            foreach ($appointments as $appointment) {
                $time = Carbon::parse($appointment->date_heure);
                $diffInMinutes = $now->diffInMinutes($time, false);

                if ($diffInMinutes <= 60 && $diffInMinutes >= 0) {
                    $this->sendAppointmentReminder($appointment);
                }
                
                if ($time->isTomorrow() && $time->diffInHours($now) < 24) {
                    $this->sendAppointmentReminder($appointment, true);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur checkAppointments: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer un rappel de rendez-vous
     */
    private function sendAppointmentReminder($appointment, $isDayBefore = false)
    {
        try {
            $time = Carbon::parse($appointment->date_heure);
            $user = $appointment->user;
            
            if (!$user) {
                Log::warning('Rendez-vous sans utilisateur', ['id' => $appointment->id]);
                return;
            }

            $title = $isDayBefore ? '📅 Rappel rendez-vous demain' : '📅 Rappel rendez-vous';
            $body = $isDayBefore 
                ? "Vous avez un rendez-vous demain à " . $time->format('H:i') . " avec " . ($appointment->medecin_nom ?? 'Dr. Inconnu')
                : "Vous avez un rendez-vous dans 1 heure à " . $time->format('H:i') . " avec " . ($appointment->medecin_nom ?? 'Dr. Inconnu');

            self::create(
                $user->id,
                $title,
                $body,
                'appointment',
                null,
                null,
                [
                    'appointment_id' => (string) $appointment->id,
                    'time' => $time->toISOString(),
                    'medecin' => $appointment->medecin_nom ?? 'Inconnu',
                ],
                '/appointments'
            );
        } catch (\Exception $e) {
            Log::error('Erreur sendAppointmentReminder: ' . $e->getMessage());
        }
    }

    /**
     * Vérifier les médicaments à prendre
     */
    public function checkMedications()
    {
        Log::info('💊 Vérification des médicaments...');
        
        // Vérifier si la table medicaments existe
        if (!Schema::hasTable('medicaments')) {
            Log::warning('⚠️ La table medicaments n\'existe pas encore');
            return;
        }
        
        $now = Carbon::now();

        try {
            $medications = Medicament::where('statut', 'actif')
                ->whereNotNull('prochaine_prise')
                ->where('prochaine_prise', '<=', $now->copy()->addHours(2))
                ->where('prochaine_prise', '>=', $now->copy()->subMinutes(30))
                ->with('user')
                ->get();

            Log::info('📊 ' . $medications->count() . ' médicaments à prendre');

            foreach ($medications as $medication) {
                $this->sendMedicationReminder($medication);
            }
        } catch (\Exception $e) {
            Log::error('Erreur checkMedications: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer un rappel de médicament
     */
    private function sendMedicationReminder($medication)
    {
        try {
            $user = $medication->user;
            
            if (!$user) {
                Log::warning('Médicament sans utilisateur', ['id' => $medication->id]);
                return;
            }

            $title = '💊 Rappel médicament';
            $body = "Il est temps de prendre " . $medication->nom . ($medication->dosage ? " (" . $medication->dosage . ")" : "");

            self::create(
                $user->id,
                $title,
                $body,
                'medication',
                null,
                null,
                [
                    'medication_id' => (string) $medication->id,
                    'nom' => $medication->nom,
                    'dosage' => $medication->dosage ?? '',
                ],
                '/medications'
            );
        } catch (\Exception $e) {
            Log::error('Erreur sendMedicationReminder: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer un résumé quotidien
     */
    public function sendDailySummary(User $user)
    {
        try {
            $today = Carbon::today();
            
            $appointments = RendezVous::where('user_id', $user->id)
                ->whereDate('date_heure', $today)
                ->count();

            $medications = Medicament::where('user_id', $user->id)
                ->whereDate('prochaine_prise', $today)
                ->where('statut', 'actif')
                ->count();

            $title = '📊 Résumé du jour';
            $body = "Vous avez $appointments rendez-vous et $medications médicaments à prendre aujourd'hui";

            self::create(
                $user->id,
                $title,
                $body,
                'reminder',
                null,
                null,
                [
                    'appointments' => (string) $appointments,
                    'medications' => (string) $medications,
                ],
                '/dashboard'
            );
        } catch (\Exception $e) {
            Log::error('Erreur sendDailySummary: ' . $e->getMessage());
        }
    }

    /**
     * Vérifier les rappels pour un utilisateur spécifique
     */
    public function checkUserReminders(User $user)
    {
        Log::info('🔔 Vérification des rappels pour l\'utilisateur ' . $user->id);
        $this->checkAppointmentsForUser($user);
        $this->checkMedicationsForUser($user);
    }

    /**
     * Vérifier les rendez-vous pour un utilisateur spécifique
     */
    private function checkAppointmentsForUser(User $user)
    {
        try {
            $now = Carbon::now();
            $today = Carbon::today();

            $appointments = RendezVous::where('user_id', $user->id)
                ->whereDate('date_heure', $today)
                ->get();

            foreach ($appointments as $appointment) {
                $time = Carbon::parse($appointment->date_heure);
                $diffInMinutes = $now->diffInMinutes($time, false);

                if ($diffInMinutes <= 60 && $diffInMinutes >= 0) {
                    $this->sendAppointmentReminder($appointment);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur checkAppointmentsForUser: ' . $e->getMessage());
        }
    }

    /**
     * Vérifier les médicaments pour un utilisateur spécifique
     */
    private function checkMedicationsForUser(User $user)
    {
        try {
            $now = Carbon::now();

            $medications = Medicament::where('user_id', $user->id)
                ->where('statut', 'actif')
                ->whereNotNull('prochaine_prise')
                ->where('prochaine_prise', '<=', $now->copy()->addHours(2))
                ->where('prochaine_prise', '>=', $now->copy()->subMinutes(30))
                ->get();

            foreach ($medications as $medication) {
                $this->sendMedicationReminder($medication);
            }
        } catch (\Exception $e) {
            Log::error('Erreur checkMedicationsForUser: ' . $e->getMessage());
        }
    }

    // ==================== NOTIFICATIONS DE SCAN ====================

    /**
     * Notification pour un scan réussi
     */
    public function notifyScanSuccess($userId, $medicationName)
    {
        self::create(
            $userId,
            '✅ Scan réussi',
            "Le médicament \"$medicationName\" a été ajouté à votre liste",
            'success',
            null,
            null,
            ['medication' => $medicationName],
            '/medications'
        );
    }

    /**
     * Notification pour un scan non trouvé
     */
    public function notifyScanNotFound($userId, $barcode)
    {
        self::create(
            $userId,
            '❌ Scan non trouvé',
            "Aucun médicament trouvé pour le code-barres: $barcode",
            'warning',
            null,
            null,
            ['barcode' => $barcode],
            '/scan'
        );
    }

    // ==================== GESTION DES NOTIFICATIONS ====================

    /**
     * Marquer une notification comme lue
     */
    public static function markAsRead($notificationId)
    {
        try {
            $notification = Notification::find($notificationId);
            if ($notification) {
                $notification->lu = true;
                $notification->save();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Erreur marquage notification comme lue: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public static function markAllAsRead($userId)
    {
        try {
            Notification::where('user_id', $userId)
                ->where('lu', false)
                ->update(['lu' => true]);
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur marquage toutes les notifications comme lues: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer une notification
     */
    public static function delete($notificationId)
    {
        try {
            $notification = Notification::find($notificationId);
            if ($notification) {
                $notification->delete();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Erreur suppression notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les notifications non lues d'un utilisateur
     */
    public static function getUnread($userId)
    {
        try {
            return Notification::where('user_id', $userId)
                ->where('lu', false)
                ->orderBy('date_envoi', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Erreur récupération notifications non lues: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Récupérer toutes les notifications d'un utilisateur
     */
    public static function getAll($userId, $limit = 50)
    {
        try {
            return Notification::where('user_id', $userId)
                ->orderBy('date_envoi', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Erreur récupération notifications: ' . $e->getMessage());
            return collect();
        }
    }
}