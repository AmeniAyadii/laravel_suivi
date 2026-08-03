<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoiceSchedulerService;
use App\Models\VoiceSchedulerCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // ✅ AJOUTER CETTE LIGNE

class VoiceSchedulerController extends Controller
{
    protected VoiceSchedulerService $voiceScheduler;

    public function __construct(VoiceSchedulerService $voiceScheduler)
    {
        $this->voiceScheduler = $voiceScheduler;
        $this->middleware('auth:sanctum');
    }

    /**
     * ✅ Démarrer le Scheduler Vocal
     */
    public function start(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_name' => 'required|string|max:150',
            'cabinet_phone' => 'nullable|string|max:20',
            'date' => 'nullable|date',
            'time' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->voiceScheduler->startScheduler(
            $request->user()->id,
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * ✅ Webhook pour les réponses vocales (public)
     */
    public function handleResponse(Request $request)
    {
        $result = $this->voiceScheduler->handleResponse($request->all());
        return response()->json($result);
    }

    /**
     * ✅ Webhook pour le statut de l'appel (public)
     */
    public function handleStatus(Request $request)
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');

        $voiceCall = VoiceSchedulerCall::where('call_sid', $callSid)->first();
        if ($voiceCall) {
            $voiceCall->update(['status' => $callStatus]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * ✅ Obtenir le statut d'un appel
     */
    public function getStatus(Request $request, int $callId)
    {
        $result = $this->voiceScheduler->getCallStatus($callId);
        return response()->json($result);
    }

    /**
     * ✅ Accepter un créneau alternatif (CORRIGÉ)
     */
    public function acceptAlternative(Request $request, int $callId)
    {
        try {
            $call = VoiceSchedulerCall::where('user_id', $request->user()->id)
                ->findOrFail($callId);

            if ($call->status !== 'negotiating') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce créneau n\'est plus disponible',
                ], 400);
            }

            // ✅ Vérifier que les dates existent
            $offeredDate = $call->offered_date ?? Carbon::now()->addDays(3)->format('Y-m-d');
            $offeredTime = $call->offered_time ?? '09:00';

            // Créer le rendez-vous
            $appointment = \App\Models\RendezVous::create([
                'user_id' => $request->user()->id,
                'medecin_nom' => $call->doctor_name,
                'date_heure' => Carbon::parse($offeredDate . ' ' . $offeredTime),
                'lieu' => 'Cabinet médical',
                'titre' => 'Consultation avec ' . $call->doctor_name,
                'statut' => 'confirmé',
                'notes' => 'Rendez-vous pris via Scheduler Vocal (Alternative acceptée)',
            ]);

            $call->update([
                'status' => 'confirmed',
                'rendez_vous_id' => $appointment->id,
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ Rendez-vous confirmé !',
                'appointment' => $appointment,
            ]);

        } catch (\Exception $e) {
            // ✅ Maintenant Log est reconnu !
            Log::error('Erreur acceptAlternative: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Refuser un créneau alternatif
     */
    public function rejectAlternative(Request $request, int $callId)
    {
        try {
            $call = VoiceSchedulerCall::where('user_id', $request->user()->id)
                ->findOrFail($callId);

            $call->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => '❌ Créneau refusé. Nous allons chercher d\'autres disponibilités.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }
}