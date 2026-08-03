<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppointmentService;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Ajouter un rendez-vous
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medecin_nom' => 'required|string|max:150',
            'date_heure' => 'required|date|after:now',
            'duration' => 'nullable|integer|min:15|max:120',
            'lieu' => 'nullable|string|max:255',
            'type' => 'nullable|in:presentiel,visio,telephone',
            'motif' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->appointmentService->addAppointment(
            $request->all(),
            $request->user()->id
        );

        if (!$result['success']) {
            if ($result['has_conflict'] ?? false) {
                return response()->json([
                    'success' => false,
                    'has_conflict' => true,
                    'conflicts' => $result['conflicts'],
                    'message' => $result['message'],
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du rendez-vous',
                'error' => $result['error'] ?? null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['appointment'],
        ]);
    }

    /**
     * Vérifier les conflits avant ajout
     */
    public function checkConflicts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_heure' => 'required|date',
            'duration' => 'nullable|integer|min:15|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $dateHeure = \Carbon\Carbon::parse($request->date_heure);
        $duration = $request->duration ?? 30;
        $dateFin = $dateHeure->copy()->addMinutes($duration);

        $conflicts = $this->appointmentService->detectConflicts(
            $request->user()->id,
            $dateHeure,
            $dateFin
        );

        return response()->json([
            'success' => true,
            'has_conflict' => !empty($conflicts),
            'conflicts' => $conflicts,
            'message' => !empty($conflicts) 
                ? $this->appointmentService->buildConflictMessage($conflicts)
                : null,
        ]);
    }

    /**
     * Suggérer des rendez-vous à partir des symptômes
     */
    public function suggestFromSymptoms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symptoms' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $suggestions = $this->appointmentService->suggestAppointmentsFromSymptoms(
            $request->user()->id,
            $request->symptoms
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Générer un briefing pour un rendez-vous
     */
    public function generateBriefing(Request $request, int $id)
    {
        $appointment = RendezVous::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $briefing = $this->appointmentService->generateBriefing($id);

        return response()->json([
            'success' => true,
            'data' => $briefing,
        ]);
    }

    /**
     * Obtenir les rendez-vous du jour
     */
    public function today(Request $request)
    {
        $appointments = $this->appointmentService->getTodayAppointments(
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    /**
     * Obtenir les rendez-vous à venir
     */
    public function upcoming(Request $request)
    {
        $appointments = $this->appointmentService->getUpcomingAppointments(
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    /**
     * Obtenir la liste des rendez-vous
     */
    public function index(Request $request)
    {
        $appointments = RendezVous::where('user_id', $request->user()->id)
            ->orderBy('date_heure', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    /**
     * Obtenir un rendez-vous spécifique
     */
    public function show(Request $request, int $id)
    {
        $appointment = RendezVous::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $appointment,
        ]);
    }

    /**
     * Mettre à jour un rendez-vous
     */
    public function update(Request $request, int $id)
    {
        $appointment = RendezVous::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'medecin_nom' => 'sometimes|string|max:150',
            'date_heure' => 'sometimes|date|after:now',
            'lieu' => 'nullable|string|max:255',
            'type' => 'nullable|in:presentiel,visio,telephone',
            'motif' => 'nullable|string',
            'notes' => 'nullable|string',
            'statut' => 'sometimes|in:à_venir,confirmé,en_cours,passé,annulé,reporté',
        ]);

        // ✅ Vérifier les conflits si la date change
        if (isset($validated['date_heure'])) {
            $dateHeure = \Carbon\Carbon::parse($validated['date_heure']);
            $duration = $request->duration ?? 30;
            $dateFin = $dateHeure->copy()->addMinutes($duration);

            $conflicts = $this->appointmentService->detectConflicts(
                $request->user()->id,
                $dateHeure,
                $dateFin
            );

            // Exclure le rendez-vous actuel des conflits
            $conflicts = array_filter($conflicts, function ($c) use ($id) {
                return $c['id'] != $id;
            });

            if (!empty($conflicts)) {
                return response()->json([
                    'success' => false,
                    'has_conflict' => true,
                    'conflicts' => $conflicts,
                    'message' => $this->appointmentService->buildConflictMessage($conflicts),
                ], 409);
            }
        }

        $appointment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous mis à jour avec succès',
            'data' => $appointment,
        ]);
    }

    /**
     * Annuler un rendez-vous
     */
    public function destroy(Request $request, int $id)
    {
        $result = $this->appointmentService->cancelAppointment(
            $id,
            $request->user()->id
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
                'error' => $result['error'] ?? null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    /**
     * Confirmer un rendez-vous
     */
    public function confirm(Request $request, int $id)
    {
        $appointment = RendezVous::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $appointment->update(['statut' => 'confirmé']);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous confirmé',
            'data' => $appointment,
        ]);
    }
}