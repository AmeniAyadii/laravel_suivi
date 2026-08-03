<?php

namespace App\Services;

use App\Models\RendezVous;
use App\Models\Symptome;
use App\Models\User;
use App\Models\Medicament;
use App\Models\MedicationTaking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /**
     * Ajouter un rendez-vous avec détection de conflit
     */
    public function addAppointment(array $data, int $userId): array
    {
        try {
            DB::beginTransaction();

            $dateHeure = Carbon::parse($data['date_heure']);
            $duration = $data['duration'] ?? 30; // minutes par défaut
            $dateFin = $dateHeure->copy()->addMinutes($duration);

            // ✅ Détection de conflit
            $conflicts = $this->detectConflicts($userId, $dateHeure, $dateFin);

            if (!empty($conflicts)) {
                return [
                    'success' => false,
                    'has_conflict' => true,
                    'conflicts' => $conflicts,
                    'message' => $this->buildConflictMessage($conflicts),
                ];
            }

            // ✅ Créer le rendez-vous
            $appointment = RendezVous::create([
                'user_id' => $userId,
                'medecin_nom' => $data['medecin_nom'],
                'medecin_specialite' => $data['medecin_specialite'] ?? null,
                'medecin_telephone' => $data['medecin_telephone'] ?? null,
                'medecin_email' => $data['medecin_email'] ?? null,
                'date_heure' => $dateHeure,
                'date_fin' => $dateFin,
                'lieu' => $data['lieu'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'code_postal' => $data['code_postal'] ?? null,
                'ville' => $data['ville'] ?? null,
                'type' => $data['type'] ?? 'presentiel',
                'lien_visio' => $data['lien_visio'] ?? null,
                'titre' => $data['titre'] ?? null,
                'motif' => $data['motif'] ?? null,
                'notes' => $data['notes'] ?? null,
                'statut' => 'à_venir',
            ]);

            // ✅ Créer les rappels
            $this->createReminders($appointment);

            DB::commit();

            return [
                'success' => true,
                'appointment' => $appointment,
                'message' => 'Rendez-vous ajouté avec succès !',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur ajout rendez-vous: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Détecter les conflits de rendez-vous
     */
    public function detectConflicts(int $userId, Carbon $start, Carbon $end): array
    {
        $conflicts = [];

        $appointments = RendezVous::where('user_id', $userId)
            ->where('statut', '!=', 'annulé')
            ->where('statut', '!=', 'passé')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('date_heure', [$start, $end])
                      ->orWhereBetween('date_fin', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('date_heure', '<=', $start)
                            ->where('date_fin', '>=', $end);
                      });
            })
            ->get();

        foreach ($appointments as $appointment) {
            $conflicts[] = [
                'id' => $appointment->id,
                'medecin_nom' => $appointment->medecin_nom,
                'date_heure' => $appointment->date_heure->format('d/m/Y H:i'),
                'date_fin' => $appointment->date_fin ? $appointment->date_fin->format('d/m/Y H:i') : null,
                'lieu' => $appointment->lieu,
                'statut' => $appointment->statut,
            ];
        }

        return $conflicts;
    }

    /**
     * Construire le message de conflit
     */
    public function buildConflictMessage(array $conflicts): string
    {
        $message = "⚠️ **Conflit de rendez-vous détecté !**\n\n";
        $message .= "Vous avez déjà un rendez-vous à cette heure :\n";

        foreach ($conflicts as $conflict) {
            $message .= "• **{$conflict['medecin_nom']}** le {$conflict['date_heure']}";
            if ($conflict['lieu']) {
                $message .= " ({$conflict['lieu']})";
            }
            $message .= "\n";
        }

        $message .= "\nSouhaitez-vous déplacer l'un de ces rendez-vous ?";

        return $message;
    }

    /**
     * Suggérer des rendez-vous en fonction des symptômes
     */
    public function suggestAppointmentsFromSymptoms(int $userId, array $symptoms): array
    {
        $suggestions = [];
        $doctor = $this->getDefaultDoctor($userId);

        // ✅ Proposer des créneaux
        $availableSlots = $this->getAvailableSlots($userId, 3);

        foreach ($availableSlots as $slot) {
            $suggestions[] = [
                'medecin_nom' => $doctor['nom'] ?? 'Votre médecin traitant',
                'medecin_specialite' => $doctor['specialite'] ?? 'Généraliste',
                'date_heure' => $slot['date'],
                'date_heure_display' => Carbon::parse($slot['date'])->format('l d/m/Y à H:i'),
                'motif' => $this->buildMotifFromSymptoms($symptoms),
                'symptoms' => $symptoms,
            ];
        }

        return $suggestions;
    }

    /**
     * Obtenir le médecin par défaut de l'utilisateur
     */
    protected function getDefaultDoctor(int $userId): array
    {
        $user = User::find($userId);
        
        if ($user && $user->medecin_traitant_id) {
            $doctor = User::find($user->medecin_traitant_id);
            if ($doctor) {
                return [
                    'nom' => $doctor->nom . ' ' . ($doctor->prenom ?? ''),
                    'specialite' => $doctor->specialite ?? 'Médecin traitant',
                    'telephone' => $doctor->telephone,
                    'email' => $doctor->email,
                ];
            }
        }

        return [
            'nom' => 'Dr Martin',
            'specialite' => 'Généraliste',
            'telephone' => '01 23 45 67 89',
            'email' => 'dr.martin@example.com',
        ];
    }

    /**
     * Obtenir les créneaux disponibles
     */
    protected function getAvailableSlots(int $userId, int $count = 3): array
    {
        $slots = [];
        $now = Carbon::now();
        $start = $now->copy()->addDay()->startOfDay();
        
        // ✅ Chercher les créneaux disponibles (9h-18h)
        for ($i = 0; $i < $count * 3; $i++) {
            $date = $start->copy()->addDays($i);
            
            // Éviter le week-end
            if ($date->isWeekend()) {
                continue;
            }

            // Créneaux possibles : 9h, 10h, 11h, 14h, 15h, 16h, 17h
            $possibleTimes = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
            
            foreach ($possibleTimes as $time) {
                $slotDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $time);
                
                // Vérifier si le créneau est libre
                $conflicts = $this->detectConflicts(
                    $userId,
                    $slotDateTime,
                    $slotDateTime->copy()->addMinutes(30)
                );

                if (empty($conflicts)) {
                    $slots[] = [
                        'date' => $slotDateTime->toDateTimeString(),
                        'day' => $date->format('l'),
                        'date_display' => $date->format('d/m/Y'),
                        'time' => $time,
                    ];
                }

                if (count($slots) >= $count) {
                    break 2;
                }
            }
        }

        return $slots;
    }

    /**
     * Construire le motif à partir des symptômes
     */
    protected function buildMotifFromSymptoms(array $symptoms): string
    {
        $symptomList = implode(', ', $symptoms);
        return "Consultation pour : $symptomList";
    }

    /**
     * Créer les rappels pour un rendez-vous
     */
    protected function createReminders(RendezVous $appointment): void
    {
        // ✅ Rappel 24h avant
        $this->createReminder($appointment, '24h', $appointment->date_heure->copy()->subDay());

        // ✅ Rappel 1h avant
        $this->createReminder($appointment, '1h', $appointment->date_heure->copy()->subHour());

        // ✅ Briefing (24h avant)
        $this->createReminder($appointment, 'briefing', $appointment->date_heure->copy()->subDay());
    }

    protected function createReminder(RendezVous $appointment, string $type, Carbon $sendAt): void
    {
        if ($sendAt->isPast()) {
            $sendAt = now();
        }

        \App\Models\AppointmentReminder::create([
            'rendez_vous_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'send_at' => $sendAt,
            'type' => $type,
            'is_sent' => false,
        ]);
    }

    /**
     * Générer un briefing pour un rendez-vous
     */
    public function generateBriefing(int $appointmentId): array
    {
        $appointment = RendezVous::with('user')->findOrFail($appointmentId);
        $userId = $appointment->user_id;

        // ✅ Récupérer les symptômes de la semaine
        $symptoms = Symptome::where('user_id', $userId)
            ->where('date_enregistrement', '>=', now()->subWeek())
            ->orderBy('date_enregistrement', 'desc')
            ->get();

        // ✅ Récupérer les médicaments actifs
        $medications = Medicament::where('user_id', $userId)
            ->where('statut', 'actif')
            ->get();

        // ✅ Récupérer l'observance
        $observance = MedicationTaking::where('user_id', $userId)
            ->where('prise_prevue', '>=', now()->subWeek())
            ->where('statut', 'prise')
            ->count();

        $totalTakings = MedicationTaking::where('user_id', $userId)
            ->where('prise_prevue', '>=', now()->subWeek())
            ->count();

        $observanceRate = $totalTakings > 0 ? round(($observance / $totalTakings) * 100, 2) : 0;

        // ✅ Construire le briefing
        $briefing = [
            'appointment' => [
                'id' => $appointment->id,
                'medecin_nom' => $appointment->medecin_nom,
                'date_heure' => $appointment->date_heure->format('d/m/Y H:i'),
                'lieu' => $appointment->lieu,
                'adresse' => $appointment->adresse,
            ],
            'symptoms' => $symptoms->map(function ($s) {
                return [
                    'description' => $s->description,
                    'niveau' => $s->niveau,
                    'date' => $s->date_enregistrement->format('d/m/Y'),
                ];
            }),
            'medications' => $medications->map(function ($m) {
                return [
                    'nom' => $m->nom,
                    'dosage' => $m->dosage,
                    'stock' => $m->stock_actuel,
                ];
            }),
            'observance_rate' => $observanceRate,
            'message' => $this->buildBriefingMessage($appointment, $symptoms, $medications, $observanceRate),
        ];

        // ✅ Marquer le briefing comme envoyé
        $appointment->update([
            'briefing_envoye' => true,
            'briefing_envoye_a' => now(),
        ]);

        return $briefing;
    }

    /**
     * Construire le message de briefing
     */
    protected function buildBriefingMessage($appointment, $symptoms, $medications, $observanceRate): string
    {
        $message = "📋 **Préparation du rendez-vous**\n\n";
        $message .= "🩺 **Rendez-vous avec Dr {$appointment->medecin_nom}**\n";
        $message .= "📅 Le {$appointment->date_heure->format('d/m/Y')} à {$appointment->date_heure->format('H:i')}\n";
        
        if ($appointment->lieu) {
            $message .= "📍 {$appointment->lieu}\n";
        }
        
        $message .= "\n---\n\n";
        $message .= "📝 **Pensez à :**\n";
        $message .= "• Apporter vos analyses\n";
        $message .= "• Votre carte vitale\n";
        $message .= "• La liste de vos médicaments actuels\n\n";

        // ✅ Symptômes de la semaine
        if ($symptoms->isNotEmpty()) {
            $message .= "🩺 **Symptômes enregistrés cette semaine :**\n";
            foreach ($symptoms as $symptom) {
                $emoji = $symptom->niveau === 'eleve' ? '🔴' : ($symptom->niveau === 'modere' ? '🟠' : '🟢');
                $message .= "• $emoji {$symptom->description} ({$symptom->niveau})\n";
            }
            $message .= "\n";
        } else {
            $message .= "✅ Aucun symptôme enregistré cette semaine.\n\n";
        }

        // ✅ Médicaments
        if ($medications->isNotEmpty()) {
            $message .= "💊 **Médicaments en cours :**\n";
            foreach ($medications as $med) {
                $message .= "• {$med->nom} ({$med->dosage}) - Stock: {$med->stock_actuel}\n";
            }
            $message .= "\n";
        }

        // ✅ Observance
        $emojiObs = $observanceRate >= 80 ? '✅' : ($observanceRate >= 50 ? '⚠️' : '❌');
        $message .= "📊 **Taux d'observance :** $emojiObs {$observanceRate}%\n\n";

        $message .= "---\n\n";
        $message .= "💡 **Conseil :** Préparez vos questions à l'avance pour optimiser votre consultation.";

        return $message;
    }

    /**
     * Obtenir les rendez-vous du jour
     */
    public function getTodayAppointments(int $userId): array
    {
        $appointments = RendezVous::where('user_id', $userId)
            ->whereDate('date_heure', now()->format('Y-m-d'))
            ->where('statut', '!=', 'annulé')
            ->orderBy('date_heure')
            ->get();

        return $appointments->toArray();
    }

    /**
     * Obtenir les rendez-vous à venir
     */
    public function getUpcomingAppointments(int $userId): array
    {
        $appointments = RendezVous::where('user_id', $userId)
            ->where('date_heure', '>=', now())
            ->where('statut', '!=', 'annulé')
            ->orderBy('date_heure')
            ->limit(10)
            ->get();

        return $appointments->toArray();
    }

    /**
     * Annuler un rendez-vous
     */
    public function cancelAppointment(int $appointmentId, int $userId): array
    {
        try {
            $appointment = RendezVous::where('user_id', $userId)
                ->findOrFail($appointmentId);

            $appointment->update([
                'statut' => 'annulé',
                'notes' => ($appointment->notes ? $appointment->notes . "\n" : '') 
                    . 'Annulé le ' . now()->format('d/m/Y H:i'),
            ]);

            return [
                'success' => true,
                'message' => 'Rendez-vous annulé avec succès',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}