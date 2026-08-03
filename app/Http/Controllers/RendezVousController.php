<?php
// app/Http/Controllers/RendezVousController.php

namespace App\Http\Controllers;

use App\Models\RendezVous;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RendezVousController extends Controller
{
    /**
     * Liste des rendez-vous de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $statut = $request->input('statut');
            $type = $request->input('type');
            $date = $request->input('date');

            $query = RendezVous::where('user_id', $userId);

            if ($statut) {
                $query->where('statut', $statut);
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($date) {
                $query->whereDate('date_heure', $date);
            }

            $rendezVous = $query->orderBy('date_heure', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rendez-vous du jour
     */
    public function today(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->whereDate('date_heure', now()->toDateString())
                ->whereIn('statut', ['à_venir', 'confirmé', 'en_cours'])
                ->orderBy('date_heure', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur today rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prochain rendez-vous
     */
    public function next(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->whereIn('statut', ['à_venir', 'confirmé'])
                ->where('date_heure', '>=', now())
                ->orderBy('date_heure', 'asc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur next rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter un nouveau rendez-vous
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'motif' => 'nullable|string',
            'medecin_nom' => 'nullable|string|max:100',
            'medecin_specialite' => 'nullable|string|max:100',
            'medecin_telephone' => 'nullable|string|max:20',
            'medecin_email' => 'nullable|email|max:100',
            'date_heure' => 'required|date|after:now',
            'date_fin' => 'nullable|date|after:date_heure',
            'lieu' => 'nullable|string|max:200',
            'adresse' => 'nullable|string|max:255',
            'ville' => 'nullable|string|max:100',
            'type' => 'nullable|in:presentiel,visio,telephone',
            'lien_visio' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'rappel_minutes' => 'nullable|integer|in:15,30,60,120,1440',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->id;
            $dateHeure = Carbon::parse($request->date_heure);
            
            $rappelMinutes = $request->rappel_minutes ?? 30;
            $rappelAt = $dateHeure->copy()->subMinutes($rappelMinutes);

            $data = $request->all();
            $data['user_id'] = $userId;
            $data['statut'] = 'à_venir';
            $data['rappel_envoye'] = false;
            $data['rappel_envoye_a'] = $rappelAt;

            $rendezVous = RendezVous::create($data);

            // Notification immédiate
            NotificationService::create(
                $userId,
                '📅 Rendez-vous ajouté',
                "Rendez-vous \"{$request->titre}\" prévu le " . $dateHeure->format('d/m/Y à H:i'),
                'success',
                null,
                null,
                ['rendezvous_id' => $rendezVous->id],
                '/appointments'
            );

            // Si le rappel est imminent, envoyer immédiatement
            if ($rappelAt->diffInMinutes(now()) <= 15) {
                $this->sendAppointmentReminder($rendezVous);
            }

            Log::info('📅 Rendez-vous créé', ['id' => $rendezVous->id]);

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous ajouté avec succès',
                'data' => $rendezVous
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur store rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un rendez-vous
     */
    public function update(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$rendezVous) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rendez-vous non trouvé'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'titre' => 'sometimes|string|max:255',
                'motif' => 'nullable|string',
                'medecin_nom' => 'nullable|string|max:100',
                'medecin_specialite' => 'nullable|string|max:100',
                'medecin_telephone' => 'nullable|string|max:20',
                'medecin_email' => 'nullable|email|max:100',
                'date_heure' => 'sometimes|date|after:now',
                'date_fin' => 'nullable|date|after:date_heure',
                'statut' => 'nullable|in:à_venir,confirmé,en_cours,passé,annulé,reporté',
                'type' => 'nullable|in:presentiel,visio,telephone',
                'rappel_minutes' => 'nullable|integer|in:15,30,60,120,1440',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Si la date change, recalculer le rappel
            if ($request->has('date_heure')) {
                $dateHeure = Carbon::parse($request->date_heure);
                $rappelMinutes = $request->rappel_minutes ?? 30;
                $rappelAt = $dateHeure->copy()->subMinutes($rappelMinutes);
                
                $data['rappel_envoye'] = false;
                $data['rappel_envoye_a'] = $rappelAt;
            }

            $rendezVous->update($data);

            // Notification de modification
            NotificationService::create(
                $userId,
                '📅 Rendez-vous modifié',
                "Le rendez-vous \"{$rendezVous->titre}\" a été modifié",
                'info',
                null,
                null,
                ['rendezvous_id' => $rendezVous->id],
                '/appointments'
            );

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous mis à jour',
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur update rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmer un rendez-vous
     */
    public function confirm(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$rendezVous) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rendez-vous non trouvé'
                ], 404);
            }

            $rendezVous->statut = 'confirmé';
            $rendezVous->save();

            NotificationService::create(
                $userId,
                '✅ Rendez-vous confirmé',
                "Rendez-vous \"{$rendezVous->titre}\" confirmé pour le " . $rendezVous->date_heure->format('d/m/Y à H:i'),
                'success',
                null,
                null,
                ['rendezvous_id' => $rendezVous->id],
                '/appointments'
            );

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous confirmé',
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur confirm rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler un rendez-vous
     */
    public function cancel(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$rendezVous) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rendez-vous non trouvé'
                ], 404);
            }

            $rendezVous->statut = 'annulé';
            $rendezVous->save();

            NotificationService::create(
                $userId,
                '🚫 Rendez-vous annulé',
                "Le rendez-vous \"{$rendezVous->titre}\" a été annulé",
                'warning',
                null,
                null,
                ['rendezvous_id' => $rendezVous->id],
                '/appointments'
            );

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous annulé',
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur cancel rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un rendez-vous
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $rendezVous = RendezVous::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$rendezVous) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rendez-vous non trouvé'
                ], 404);
            }

            $titre = $rendezVous->titre;
            $rendezVous->delete();

            NotificationService::create(
                $userId,
                '🗑️ Rendez-vous supprimé',
                "Le rendez-vous \"{$titre}\" a été supprimé",
                'warning',
                null,
                null,
                [],
                '/appointments'
            );

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous supprimé'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur destroy rendez-vous: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier les rappels (appelé par le scheduler)
     */
    public function checkReminders(Request $request)
    {
        try {
            Log::info('🔔 Vérification des rappels de rendez-vous...');

            $appointments = RendezVous::rappelNonEnvoye()
                ->with('user')
                ->get();

            Log::info('📊 ' . $appointments->count() . ' rappels à envoyer');

            foreach ($appointments as $appointment) {
                $this->sendAppointmentReminder($appointment);
            }

            return response()->json([
                'success' => true,
                'message' => $appointments->count() . ' rappels envoyés'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur checkReminders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer un rappel pour un rendez-vous
     */
    private function sendAppointmentReminder($appointment)
    {
        try {
            $user = $appointment->user;
            $dateHeure = $appointment->date_heure;
            $timeLeft = now()->diffInMinutes($dateHeure);

            // Déterminer le message selon le temps restant
            if ($timeLeft <= 15) {
                $title = '🔔 Rappel rendez-vous (15 min)';
                $body = "Rendez-vous \"{$appointment->titre}\" dans moins de 15 minutes !";
            } elseif ($timeLeft <= 30) {
                $title = '🔔 Rappel rendez-vous (30 min)';
                $body = "Rendez-vous \"{$appointment->titre}\" dans 30 minutes !";
            } elseif ($timeLeft <= 60) {
                $title = '🔔 Rappel rendez-vous (1 heure)';
                $body = "Rendez-vous \"{$appointment->titre}\" dans 1 heure !";
            } else {
                $title = '📅 Rappel rendez-vous';
                $body = "Rendez-vous \"{$appointment->titre}\" prévu le " . $dateHeure->format('d/m/Y à H:i');
            }

            // Ajouter les détails
            if ($appointment->medecin_nom) {
                $body .= " avec Dr. {$appointment->medecin_nom}";
            }
            if ($appointment->lieu) {
                $body .= " à {$appointment->lieu}";
            }
            if ($appointment->type === 'visio' && $appointment->lien_visio) {
                $body .= " (Visio: {$appointment->lien_visio})";
            }

            NotificationService::create(
                $user->id,
                $title,
                $body,
                'appointment',
                null,
                null,
                [
                    'rendezvous_id' => $appointment->id,
                    'time_left' => $timeLeft,
                    'medecin' => $appointment->medecin_nom,
                    'lieu' => $appointment->lieu,
                    'type' => $appointment->type,
                    'lien_visio' => $appointment->lien_visio,
                ],
                '/appointments'
            );

            $appointment->rappel_envoye = true;
            $appointment->save();

            Log::info('✅ Rappel envoyé pour le rendez-vous ' . $appointment->id);

        } catch (\Exception $e) {
            Log::error('Erreur envoi rappel: ' . $e->getMessage());
        }
    }
}