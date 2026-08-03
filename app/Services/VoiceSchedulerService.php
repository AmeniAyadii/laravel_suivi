<?php

namespace App\Services;

use App\Models\VoiceSchedulerCall;
use App\Models\RendezVous;
use App\Models\User;
use App\Models\Doctor;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VoiceSchedulerService
{
    protected $twilioClient;
    protected $twilioPhoneNumber;
    protected $webhookUrl;

    public function __construct()
    {
        // ✅ Ne pas initialiser Twilio en mode test pour éviter les erreurs
        if (!$this->isSimulationMode()) {
            $this->twilioClient = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );
        }
        $this->twilioPhoneNumber = config('services.twilio.phone_number');
        $this->webhookUrl = config('services.twilio.webhook_url');
    }

    /**
     * ✅ Vérifier si on est en mode simulation
     */
    protected function isSimulationMode(): bool
    {
        // ✅ Mode simulation si :
        // 1. Le numéro Twilio est le numéro de test
        // 2. OU en environnement local
        // 3. OU si la variable SIMULATION_MODE est définie
        $isTestPhone = config('services.twilio.phone_number') === '+15005550006';
        $isLocal = config('app.env') === 'local';
        $isSimulation = config('services.twilio.simulation_mode', false);
        
        return $isTestPhone || $isLocal || $isSimulation;
    }

    /**
     * ✅ Lancer le Scheduler Vocal
     */
    public function startScheduler(int $userId, array $data): array
    {
        try {
            $doctorName = $data['doctor_name'];
            $cabinetPhone = $data['cabinet_phone'] ?? $this->getDoctorPhone($doctorName);
            
            if (!$cabinetPhone) {
                return [
                    'success' => false,
                    'message' => '❌ Numéro de téléphone non trouvé pour ce médecin.',
                    'status' => 'failed',
                ];
            }

            $voiceCall = VoiceSchedulerCall::create([
                'user_id' => $userId,
                'cabinet_phone' => $cabinetPhone,
                'doctor_name' => $doctorName,
                'preferred_date' => $data['date'] ?? null,
                'preferred_time' => $data['time'] ?? null,
                'status' => 'pending',
            ]);

            // ✅ Vérifier le mode
            if ($this->isSimulationMode()) {
                return $this->simulateCall($voiceCall);
            }

            return $this->makeCall($voiceCall);

        } catch (\Exception $e) {
            Log::error('Voice Scheduler Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '❌ Erreur: ' . $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    /**
     * ✅ SIMULATION d'appel (sans Twilio)
     */
    protected function simulateCall(VoiceSchedulerCall $voiceCall): array
    {
        Log::info('🧪 SIMULATION D\'APPEL VOCAL', [
            'doctor' => $voiceCall->doctor_name,
            'phone' => $voiceCall->cabinet_phone,
            'date' => $voiceCall->preferred_date,
            'time' => $voiceCall->preferred_time
        ]);

        // ✅ Simuler un délai d'appel
        sleep(2);

        // ✅ Simuler une réponse du secrétariat
        $simulatedResponse = $this->simulateSecretaryResponse();

        // ✅ Mettre à jour le statut
        $voiceCall->update([
            'status' => 'negotiating',
            'offered_date' => $simulatedResponse['date'],
            'offered_time' => $simulatedResponse['time'],
            'conversation_log' => "🧪 Simulation d'appel pour Dr {$voiceCall->doctor_name}",
        ]);

        return [
            'success' => true,
            'message' => '🧪 [SIMULATION] Appel simulé vers le cabinet...',
            'call_id' => $voiceCall->id,
            'call_sid' => 'SIM_' . uniqid(),
            'status' => 'negotiating',
            'mode' => 'simulation',
            'alternative' => [
                'date' => $simulatedResponse['date'],
                'time' => $simulatedResponse['time'],
            ],
            'action_required' => true,
        ];
    }

    /**
     * ✅ Simuler une réponse du secrétariat
     */
    protected function simulateSecretaryResponse(): array
    {
        // ✅ Dates aléatoires dans les 7 prochains jours
        $dates = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = Carbon::now()->addDays($i);
            // Éviter le week-end
            if (!$date->isWeekend()) {
                $dates[] = $date;
            }
        }

        if (empty($dates)) {
            $dates = [Carbon::now()->addDays(3)];
        }

        $selectedDate = $dates[array_rand($dates)];
        $hours = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
        $selectedHour = $hours[array_rand($hours)];

        return [
            'date' => $selectedDate->format('Y-m-d'),
            'time' => $selectedHour,
        ];
    }

    /**
     * ✅ Effectuer l'appel téléphonique (version réelle)
     */
    protected function makeCall(VoiceSchedulerCall $voiceCall): array
    {
        try {
            $script = $this->generateScript($voiceCall);
            $twiml = $this->generateTwiml($script, $voiceCall->id);

            $call = $this->twilioClient->calls->create(
                $voiceCall->cabinet_phone,
                $this->twilioPhoneNumber,
                [
                    'twiml' => $twiml,
                    'statusCallback' => $this->webhookUrl . '/status',
                    'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                    'statusCallbackMethod' => 'POST',
                    'timeout' => 30,
                    'record' => true,
                ]
            );

            $voiceCall->update([
                'call_sid' => $call->sid,
                'status' => 'calling',
                'called_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => '📞 J\'appelle le secrétariat du Dr ' . $voiceCall->doctor_name . '...',
                'call_id' => $voiceCall->id,
                'call_sid' => $call->sid,
                'status' => 'calling',
                'mode' => 'production'
            ];

        } catch (\Exception $e) {
            $voiceCall->update(['status' => 'failed']);
            Log::error('❌ Erreur appel: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '❌ Erreur lors de l\'appel: ' . $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    /**
     * ✅ Générer le script vocal
     */
    protected function generateScript(VoiceSchedulerCall $call): string
    {
        $doctorName = $call->doctor_name;
        $user = $call->user;
        $patientName = $user->prenom ?? $user->nom ?? 'le patient';
        
        $preferredDate = $call->preferred_date 
            ? Carbon::parse($call->preferred_date)->format('d/m/Y') 
            : 'dès que possible';
        $preferredTime = $call->preferred_time ?? '';

        $isSimulation = $this->isSimulationMode();
        $prefix = $isSimulation ? "🧪 [SIMULATION] " : "";

        $script = $prefix . "Bonjour, je suis l'assistant vocal du Dr {$doctorName}. ";
        $script .= "Je vous appelle de la part de {$patientName} pour un rendez-vous. ";
        
        if ($preferredDate && $preferredTime) {
            $script .= "Le patient souhaiterait consulter le {$preferredDate} à {$preferredTime}. ";
        } elseif ($preferredDate) {
            $script .= "Le patient souhaiterait consulter le {$preferredDate}. ";
        } else {
            $script .= "Le patient souhaiterait consulter dès que possible. ";
        }
        
        $script .= "Pouvez-vous me proposer un créneau ? ";
        $script .= "Dites OUI pour confirmer, ou proposez une alternative.";

        return $script;
    }

    /**
     * ✅ Générer le TwiML pour Twilio
     */
    protected function generateTwiml(string $script, int $callId): string
    {
        return "
        <Response>
            <Say voice='Polly.Joanna' language='fr-FR'>
                {$script}
            </Say>
            <Pause length='2'/>
            <Gather 
                input='speech' 
                action='{$this->webhookUrl}/response'
                method='POST'
                timeout='10'
                speechTimeout='auto'
                language='fr-FR'
                speechModel='phone_call'
                enhanced='true'
            >
                <Say voice='Polly.Joanna' language='fr-FR'>
                    Je vous écoute.
                </Say>
            </Gather>
            <Say voice='Polly.Joanna' language='fr-FR'>
                Je n'ai pas compris. Merci de rappeler.
            </Say>
            <Hangup/>
        </Response>
        ";
    }

    /**
     * ✅ Traiter la réponse du cabinet (Webhook)
     */
    public function handleResponse(array $data): array
    {
        try {
            $callSid = $data['CallSid'] ?? null;
            $speechResult = $data['SpeechResult'] ?? null;

            if (!$callSid) {
                return ['success' => false, 'message' => 'Call SID manquant'];
            }

            $voiceCall = VoiceSchedulerCall::where('call_sid', $callSid)->first();
            if (!$voiceCall) {
                // ✅ En mode simulation, accepter les réponses simulées
                if ($this->isSimulationMode()) {
                    return $this->handleSimulatedResponse($data);
                }
                return ['success' => false, 'message' => 'Appel non trouvé'];
            }

            if ($speechResult) {
                return $this->processSpeechResult($voiceCall, $speechResult);
            }

            return ['success' => true, 'message' => 'En attente de réponse...'];

        } catch (\Exception $e) {
            Log::error('Erreur traitement réponse: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Gérer une réponse simulée
     */
    protected function handleSimulatedResponse(array $data): array
    {
        Log::info('🧪 Réponse simulée reçue', $data);

        // ✅ Simuler une réponse positive
        $date = Carbon::now()->addDays(3)->format('Y-m-d');
        $time = '14:00';

        return [
            'success' => true,
            'message' => '✅ [SIMULATION] Rendez-vous confirmé !',
            'appointment' => [
                'medecin_nom' => 'Dr Martin (SIM)',
                'date_heure' => $date . ' ' . $time . ':00',
                'lieu' => 'Cabinet médical (SIMULATION)',
            ],
            'call_id' => 'SIM_' . uniqid(),
            'mode' => 'simulation'
        ];
    }

    /**
     * ✅ Traiter le résultat de la reconnaissance vocale
     */
    protected function processSpeechResult(VoiceSchedulerCall $voiceCall, string $speech): array
    {
        $speechLower = strtolower($speech);
        Log::info('🎤 Réponse reçue: ' . $speech);

        $confirmKeywords = ['oui', 'ok', 'd\'accord', 'parfait', 'bien', 'je confirme'];
        foreach ($confirmKeywords as $keyword) {
            if (str_contains($speechLower, $keyword)) {
                return $this->confirmAppointment($voiceCall, $this->extractDateTime($speech));
            }
        }

        $alternative = $this->extractDateTime($speech);
        if ($alternative['date'] || $alternative['time']) {
            return $this->proposeAlternative($voiceCall, $alternative);
        }

        $refuseKeywords = ['non', 'pas', 'refuse', 'impossible'];
        foreach ($refuseKeywords as $keyword) {
            if (str_contains($speechLower, $keyword)) {
                return $this->handleRefusal($voiceCall);
            }
        }

        return $this->askForClarification($voiceCall);
    }

    /**
     * ✅ Extraire la date et l'heure de la réponse
     */
    protected function extractDateTime(string $speech): array
    {
        $result = ['date' => null, 'time' => null];

        if (preg_match('/(\d{1,2})\s*[/-]\s*(\d{1,2})/', $speech, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = Carbon::now()->year;
            $result['date'] = "{$year}-{$month}-{$day}";
        }

        if (preg_match('/(\d{1,2})\s*[:h]\s*(\d{2})?/', $speech, $matches)) {
            $hour = $matches[1];
            $minute = $matches[2] ?? '00';
            $result['time'] = "{$hour}:{$minute}";
        }

        return $result;
    }

    /**
     * ✅ Confirmer le rendez-vous
     */
    protected function confirmAppointment(VoiceSchedulerCall $voiceCall, array $data): array
    {
        $userId = $voiceCall->user_id;
        
        // ✅ En mode simulation
        if ($this->isSimulationMode()) {
            return [
                'success' => true,
                'message' => '✅ [SIMULATION] Rendez-vous confirmé !',
                'appointment' => [
                    'medecin_nom' => $voiceCall->doctor_name . ' (SIM)',
                    'date_heure' => ($data['date'] ?? Carbon::now()->addDays(3)->format('Y-m-d')) . ' ' . ($data['time'] ?? '14:00') . ':00',
                    'lieu' => 'Cabinet médical (SIMULATION)',
                ],
                'call_id' => $voiceCall->id,
                'mode' => 'simulation'
            ];
        }

        $appointment = RendezVous::create([
            'user_id' => $userId,
            'medecin_nom' => $voiceCall->doctor_name,
            'date_heure' => Carbon::parse($data['date'] . ' ' . ($data['time'] ?? '09:00')),
            'lieu' => 'Cabinet médical',
            'titre' => 'Consultation avec ' . $voiceCall->doctor_name,
            'statut' => 'confirmé',
            'notes' => 'Rendez-vous pris via Scheduler Vocal',
        ]);

        $voiceCall->update([
            'status' => 'confirmed',
            'offered_date' => $data['date'],
            'offered_time' => $data['time'],
            'rendez_vous_id' => $appointment->id,
            'completed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => '✅ Rendez-vous confirmé !',
            'appointment' => $appointment,
            'call_id' => $voiceCall->id,
        ];
    }

    /**
     * ✅ Proposer un créneau alternatif
     */
    protected function proposeAlternative(VoiceSchedulerCall $voiceCall, array $data): array
    {
        $voiceCall->update([
            'status' => 'negotiating',
            'offered_date' => $data['date'],
            'offered_time' => $data['time'],
        ]);

        $user = $voiceCall->user;
        $this->sendUserNotification($user, [
            'title' => '📞 Nouveau créneau proposé !',
            'body' => "Le secrétariat du Dr {$voiceCall->doctor_name} propose le " . 
                      ($data['date'] ?? '') . " à " . ($data['time'] ?? '') . ".",
            'call_id' => $voiceCall->id,
        ]);

        return [
            'success' => true,
            'message' => '📞 Créneau alternatif proposé.',
            'alternative' => $data,
            'call_id' => $voiceCall->id,
            'action_required' => true,
        ];
    }

    /**
     * ✅ Gérer le refus
     */
    protected function handleRefusal(VoiceSchedulerCall $voiceCall): array
    {
        $voiceCall->update(['status' => 'cancelled']);

        return [
            'success' => true,
            'message' => '❌ Aucun créneau disponible.',
            'call_id' => $voiceCall->id,
        ];
    }

    /**
     * ✅ Demander une clarification
     */
    protected function askForClarification(VoiceSchedulerCall $voiceCall): array
    {
        return $this->makeCall($voiceCall);
    }

    /**
     * ✅ Obtenir le numéro d'un médecin
     */
    protected function getDoctorPhone(string $doctorName): ?string
    {
        $cleanName = preg_replace('/^(Dr|Docteur|Dr\.)\s*/i', '', $doctorName);
        $cleanName = trim($cleanName);
        
        $doctor = Doctor::where('nom', 'LIKE', "%{$cleanName}%")
            ->orWhere('prenom', 'LIKE', "%{$cleanName}%")
            ->first();
        
        return $doctor ? $doctor->cabinet_phone : null;
    }

    /**
     * ✅ Envoyer une notification
     */
    protected function sendUserNotification($user, array $data)
    {
        Log::info('🔔 Notification utilisateur', [
            'user_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'call_id' => $data['call_id'] ?? null,
        ]);
    }

    /**
     * ✅ Vérifier le statut d'un appel
     */
    public function getCallStatus(int $callId): array
    {
        $call = VoiceSchedulerCall::find($callId);
        if (!$call) {
            return ['success' => false, 'message' => 'Appel non trouvé'];
        }

        return [
            'success' => true,
            'status' => $call->status,
            'doctor_name' => $call->doctor_name,
            'offered_date' => $call->offered_date,
            'offered_time' => $call->offered_time,
            'called_at' => $call->called_at,
            'answered_at' => $call->answered_at,
            'completed_at' => $call->completed_at,
        ];
    }
}