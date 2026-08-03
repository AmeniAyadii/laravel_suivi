<?php

namespace App\Services;

use App\Models\Medicament;
use App\Models\MedicationTaking;
use App\Models\MedicationInteraction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MedicationService
{
    protected $interactions = [];

    public function __construct()
    {
        // Base de données d'interactions intégrée
        $this->interactions = [
            'doliprane' => [
                'aspirine' => [
                    'severity' => 'moderate',
                    'description' => 'Risque d\'interaction entre le paracétamol et l\'aspirine.',
                    'recommendation' => 'Espacez les prises de 4 heures.',
                    'spacing_hours' => 4,
                ],
                'ibuprofène' => [
                    'severity' => 'moderate',
                    'description' => 'Risque d\'interaction entre le paracétamol et l\'ibuprofène.',
                    'recommendation' => 'Espacez les prises de 4 heures.',
                    'spacing_hours' => 4,
                ],
            ],
            'ibuprofène' => [
                'aspirine' => [
                    'severity' => 'high',
                    'description' => 'Interaction entre anti-inflammatoires.',
                    'recommendation' => 'Ne prenez pas ces deux médicaments ensemble. Consultez votre médecin.',
                    'spacing_hours' => 8,
                ],
                'spasfon' => [
                    'severity' => 'low',
                    'description' => 'Interaction possible.',
                    'recommendation' => 'Pas d\'interaction majeure.',
                    'spacing_hours' => 0,
                ],
            ],
        ];
    }

    /**
     * Ajouter un médicament avec reconnaissance vocale avancée
     */
    public function addMedication(array $data, int $userId): array
    {
        try {
            DB::beginTransaction();

            // Extraire les informations du texte
            if (isset($data['text'])) {
                $extracted = $this->extractMedicationInfo($data['text']);
                $data = array_merge($data, $extracted);
            }

            // Créer le médicament
            $medicament = Medicament::create([
                'user_id' => $userId,
                'nom' => $data['nom'] ?? $data['name'] ?? null,
                'nom_generique' => $data['nom_generique'] ?? null,
                'dosage' => $data['dosage'] ?? null,
                'forme' => $data['forme'] ?? 'comprimé',
                'voie_administration' => $data['voie_administration'] ?? 'orale',
                'laboratoire' => $data['laboratoire'] ?? null,
                'frequence' => $data['frequence'] ?? 'quotidien',
                'horaires_prises' => $data['horaires_prises'] ?? ['08:00'],
                'quantite_par_prise' => $data['quantite_par_prise'] ?? 1,
                'duree_traitement_jours' => $data['duree_traitement_jours'] ?? null,
                'date_debut' => $data['date_debut'] ?? now()->toDateString(),
                'date_fin' => $data['date_fin'] ?? null,
                'prochaine_prise' => $data['prochaine_prise'] ?? $this->calculateNextDose($data['horaires_prises'] ?? ['08:00']),
                'stock_actuel' => $data['stock_actuel'] ?? 30,
                'seuil_alerte_stock' => $data['seuil_alerte_stock'] ?? 5,
                'unite_stock' => $data['unite_stock'] ?? 'comprimé(s)',
                'notes' => $data['notes'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'effets_secondaires' => $data['effets_secondaires'] ?? null,
                'contre_indications' => $data['contre_indications'] ?? null,
                'interactions' => $data['interactions'] ?? null,
                'prescrit_par' => $data['prescrit_par'] ?? null,
                'date_prescription' => $data['date_prescription'] ?? now()->toDateString(),
                'statut' => 'actif',
                'rappel_actif' => true,
                'barcode' => $data['barcode'] ?? null,
                'code_type' => $data['code_type'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'category' => $data['category'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'scanned' => $data['scanned'] ?? false,
            ]);

            // Créer les prises planifiées
            $this->createScheduledTakings($medicament);

            // Vérifier les interactions
            $interactions = $this->checkInteractions($medicament, $userId);

            DB::commit();

            return [
                'success' => true,
                'medicament' => $medicament,
                'interactions' => $interactions,
                'message' => $this->buildSuccessMessage($medicament, $interactions),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur ajout médicament: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extraction avancée des informations médicamenteuses
     */
    public function extractMedicationInfo(string $text): array
    {
        $text = strtolower($text);
        $result = [];

        // 1. Détection du nom
        $commonMedications = [
            'doliprane' => ['nom' => 'Doliprane', 'dosage' => '1000mg', 'forme' => 'comprimé'],
            'ibuprofène' => ['nom' => 'Ibuprofène', 'dosage' => '400mg', 'forme' => 'comprimé'],
            'paracétamol' => ['nom' => 'Paracétamol', 'dosage' => '500mg', 'forme' => 'comprimé'],
            'aspirine' => ['nom' => 'Aspirine', 'dosage' => '100mg', 'forme' => 'comprimé'],
            'spasfon' => ['nom' => 'Spasfon', 'dosage' => '80mg', 'forme' => 'comprimé'],
            'amoxicilline' => ['nom' => 'Amoxicilline', 'dosage' => '500mg', 'forme' => 'gélule'],
            'ventoline' => ['nom' => 'Ventoline', 'dosage' => '100µg', 'forme' => 'spray'],
            'imodium' => ['nom' => 'Imodium', 'dosage' => '2mg', 'forme' => 'gélule'],
        ];

        foreach ($commonMedications as $key => $med) {
            if (str_contains($text, $key)) {
                $result['nom'] = $med['nom'];
                $result['dosage'] = $med['dosage'];
                $result['forme'] = $med['forme'];
                break;
            }
        }

        // 2. Détection du dosage (ex: "1000mg", "500 mg")
        if (preg_match('/(\d+)\s*(mg|g|ml|µg)/i', $text, $matches)) {
            $result['dosage'] = $matches[1] . $matches[2];
        }

        // 3. Détection de la fréquence
        if (str_contains($text, 'matin')) {
            $result['horaires_prises'] = ['08:00'];
            $result['frequence'] = 'quotidien';
        } elseif (str_contains($text, 'midi')) {
            $result['horaires_prises'] = ['12:00'];
            $result['frequence'] = 'quotidien';
        } elseif (str_contains($text, 'soir')) {
            $result['horaires_prises'] = ['20:00'];
            $result['frequence'] = 'quotidien';
        } elseif (str_contains($text, '8h')) {
            $result['horaires_prises'] = ['08:00', '16:00', '00:00'];
            $result['frequence'] = 'toutes les 8h';
        } elseif (str_contains($text, '12h')) {
            $result['horaires_prises'] = ['08:00', '20:00'];
            $result['frequence'] = 'toutes les 12h';
        }

        // 4. Détection de la quantité
        if (preg_match('/(\d+)\s*(comprimés?|gélules?|cachets?)/i', $text, $matches)) {
            $result['stock_actuel'] = (int) $matches[1];
        }

        // 5. Détection des instructions
        if (str_contains($text, 'repas')) {
            $result['instructions'] = 'À prendre pendant ou après le repas.';
        } elseif (str_contains($text, 'jeun')) {
            $result['instructions'] = 'À prendre à jeun.';
        }

        return $result;
    }

    /**
     * Calculer la prochaine prise
     */
    public function calculateNextDose(array $horaires): ?string
    {
        if (empty($horaires)) return null;

        $now = now();
        $today = $now->format('Y-m-d');

        foreach ($horaires as $horaire) {
            $dateTime = Carbon::parse($today . ' ' . $horaire);
            if ($dateTime > $now) {
                return $dateTime->toDateTimeString();
            }
        }

        // Toutes les prises du jour sont passées
        $firstTime = $horaires[0];
        return Carbon::parse(now()->addDay()->format('Y-m-d') . ' ' . $firstTime)->toDateTimeString();
    }

    /**
     * Créer les prises planifiées
     */
    public function createScheduledTakings(Medicament $medicament): void
    {
        $horaires = $medicament->horaires_prises ?? ['08:00'];
        $startDate = $medicament->date_debut ?? now()->toDateString();
        $endDate = $medicament->date_fin ?? now()->addDays(30)->toDateString();

        $currentDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($currentDate <= $end) {
            foreach ($horaires as $horaire) {
                $prisePrevue = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $horaire);
                
                // Ne créer que les prises futures ou aujourd'hui
                if ($prisePrevue >= now()->subDay()) {
                    MedicationTaking::create([
                        'medicament_id' => $medicament->id,
                        'user_id' => $medicament->user_id,
                        'prise_prevue' => $prisePrevue,
                        'statut' => $prisePrevue < now() ? 'oubliee' : 'prevue',
                    ]);
                }
            }
            $currentDate->addDay();
        }
    }

    /**
     * Vérifier les interactions médicamenteuses
     */
    public function checkInteractions(Medicament $newMedicament, int $userId): array
    {
        $interactions = [];
        $newMedName = strtolower($newMedicament->nom);

        // Récupérer tous les médicaments actifs de l'utilisateur
        $existingMedications = Medicament::where('user_id', $userId)
            ->where('statut', 'actif')
            ->where('id', '!=', $newMedicament->id)
            ->get();

        foreach ($existingMedications as $existing) {
            $existingName = strtolower($existing->nom);
            
            // Vérifier dans les deux sens
            if (isset($this->interactions[$newMedName][$existingName])) {
                $interaction = $this->interactions[$newMedName][$existingName];
                $interactions[] = [
                    'medicament_a' => $newMedicament->nom,
                    'medicament_b' => $existing->nom,
                    'severity' => $interaction['severity'],
                    'description' => $interaction['description'],
                    'recommendation' => $interaction['recommendation'],
                    'spacing_hours' => $interaction['spacing_hours'],
                    'medicament_b_id' => $existing->id,
                ];
            } elseif (isset($this->interactions[$existingName][$newMedName])) {
                $interaction = $this->interactions[$existingName][$newMedName];
                $interactions[] = [
                    'medicament_a' => $existing->nom,
                    'medicament_b' => $newMedicament->nom,
                    'severity' => $interaction['severity'],
                    'description' => $interaction['description'],
                    'recommendation' => $interaction['recommendation'],
                    'spacing_hours' => $interaction['spacing_hours'],
                    'medicament_b_id' => $existing->id,
                ];
            }
        }

        return $interactions;
    }

    /**
     * Construire le message de succès
     */
    public function buildSuccessMessage(Medicament $medicament, array $interactions): string
    {
        $message = "✅ Médicament ajouté avec succès !\n\n";
        $message .= "🩺 **{$medicament->nom}** ({$medicament->dosage})\n";
        $message .= "📅 Prise : " . implode(', ', $medicament->horaires_prises ?? ['08:00']) . "\n";
        $message .= "💊 Stock : {$medicament->stock_actuel} {$medicament->unite_stock}\n";

        if (!empty($interactions)) {
            $message .= "\n⚠️ **Interactions détectées :**\n";
            foreach ($interactions as $interaction) {
                $severityEmoji = $interaction['severity'] === 'high' ? '🔴' : ($interaction['severity'] === 'moderate' ? '🟠' : '🟡');
                $message .= "{$severityEmoji} {$interaction['medicament_a']} ↔ {$interaction['medicament_b']}\n";
                $message .= "   💡 {$interaction['recommendation']}\n";
                if ($interaction['spacing_hours'] > 0) {
                    $message .= "   ⏰ Espacez de {$interaction['spacing_hours']} heures\n";
                }
            }
        }

        return $message;
    }

    /**
     * Vérifier les alertes de stock
     */
    public function checkStockAlerts(int $userId): array
    {
        $alerts = [];
        $medications = Medicament::where('user_id', $userId)
            ->where('statut', 'actif')
            ->get();

        foreach ($medications as $medicament) {
            $joursRestants = $medicament->jours_restants;
            
            if ($joursRestants <= 3 && $joursRestants > 0) {
                $alerts[] = [
                    'type' => 'stock_low',
                    'medicament_id' => $medicament->id,
                    'medicament_nom' => $medicament->nom,
                    'jours_restants' => $joursRestants,
                    'stock_actuel' => $medicament->stock_actuel,
                    'message' => "⚠️ Stock faible : Vous n'avez plus de {$medicament->nom} pour {$joursRestants} jours.",
                    'action' => 'renew_prescription',
                    'severity' => 'warning',
                ];
            } elseif ($joursRestants <= 0) {
                $alerts[] = [
                    'type' => 'stock_empty',
                    'medicament_id' => $medicament->id,
                    'medicament_nom' => $medicament->nom,
                    'message' => "🚨 Stock épuisé : Vous n'avez plus de {$medicament->nom}. Veuillez renouveler votre ordonnance.",
                    'action' => 'renew_prescription',
                    'severity' => 'danger',
                ];
            }

            // Alerte péremption
            if ($medicament->expiry_date && $medicament->expiry_date < now()->addMonth()) {
                $alerts[] = [
                    'type' => 'expiry_soon',
                    'medicament_id' => $medicament->id,
                    'medicament_nom' => $medicament->nom,
                    'expiry_date' => $medicament->expiry_date->format('d/m/Y'),
                    'message' => "📅 Péremption proche : {$medicament->nom} expire le {$medicament->expiry_date->format('d/m/Y')}.",
                    'action' => 'check_stock',
                    'severity' => 'warning',
                ];
            }
        }

        return $alerts;
    }

    /**
     * Enregistrer une prise (observance)
     */
    public function recordTaking(int $takingId, bool $taken, string $source = 'manual'): array
    {
        try {
            $taking = MedicationTaking::findOrFail($takingId);
            
            if ($taken) {
                $taking->update([
                    'statut' => 'prise',
                    'prise_reelle' => now(),
                ]);
            } else {
                $taking->update([
                    'statut' => 'oubliee',
                ]);
            }

            // Mettre à jour le stock si pris
            if ($taken) {
                $medicament = $taking->medicament;
                if ($medicament->stock_actuel > 0) {
                    $medicament->decrement('stock_actuel');
                    
                    // Mettre à jour la prochaine prise
                    $medicament->update([
                        'prochaine_prise' => $this->calculateNextDose($medicament->horaires_prises ?? ['08:00']),
                    ]);
                }
            }

            return [
                'success' => true,
                'taking' => $taking,
                'message' => $taken ? '✅ Prise enregistrée avec succès !' : '❌ Prise marquée comme oubliée.',
            ];

        } catch (\Exception $e) {
            Log::error('Erreur enregistrement prise: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtenir le résumé d'observance
     */
    public function getObservanceSummary(int $userId, string $period = 'week'): array
    {
        $startDate = $period === 'week' ? now()->subWeek() : now()->subMonth();
        
        $takings = MedicationTaking::where('user_id', $userId)
            ->where('prise_prevue', '>=', $startDate)
            ->get();

        $total = $takings->count();
        $prises = $takings->where('statut', 'prise')->count();
        $oubliees = $takings->where('statut', 'oubliee')->count();
        $prevues = $takings->where('statut', 'prevue')->count();

        return [
            'total' => $total,
            'prises' => $prises,
            'oubliees' => $oubliees,
            'prevues' => $prevues,
            'taux_observance' => $total > 0 ? round(($prises / $total) * 100, 2) : 0,
            'details_par_medicament' => $this->getObservanceByMedication($userId, $startDate),
        ];
    }

    /**
     * Observance par médicament
     */
    public function getObservanceByMedication(int $userId, $startDate): array
    {
        $medications = Medicament::where('user_id', $userId)
            ->where('statut', 'actif')
            ->with(['prises' => function ($query) use ($startDate) {
                $query->where('prise_prevue', '>=', $startDate);
            }])
            ->get();

        $result = [];
        foreach ($medications as $medicament) {
            $total = $medicament->prises->count();
            $prises = $medicament->prises->where('statut', 'prise')->count();
            
            $result[] = [
                'medicament_id' => $medicament->id,
                'medicament_nom' => $medicament->nom,
                'total' => $total,
                'prises' => $prises,
                'taux' => $total > 0 ? round(($prises / $total) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    /**
     * Générer un message de renouvellement
     */
    public function generateRenewalMessage(int $medicamentId): string
    {
        $medicament = Medicament::findOrFail($medicamentId);
        
        $message = "Bonjour Docteur,\n\n";
        $message .= "Je souhaiterais renouveler mon ordonnance pour **{$medicament->nom}** ({$medicament->dosage}).\n\n";
        $message .= "📅 Date de début du traitement : {$medicament->date_debut->format('d/m/Y')}\n";
        $message .= "💊 Stock restant : {$medicament->stock_actuel} {$medicament->unite_stock}\n";
        $message .= "📋 Posologie : " . implode(', ', $medicament->horaires_prises ?? ['08:00']) . "\n";
        
        if ($medicament->date_fin) {
            $message .= "📅 Date de fin prévue : {$medicament->date_fin->format('d/m/Y')}\n";
        }
        
        $message .= "\nCordialement,\n";
        $message .= "Votre patient.";

        return $message;
    }

    /**
     * Obtenir les prises du jour
     */
    public function getTodayTakings(int $userId): array
    {
        $today = now()->format('Y-m-d');
        
        $takings = MedicationTaking::where('user_id', $userId)
            ->whereDate('prise_prevue', $today)
            ->where('statut', 'prevue')
            ->with('medicament')
            ->orderBy('prise_prevue')
            ->get();

        return $takings->toArray();
    }

    /**
     * Vérifier et envoyer les rappels (pour le cron)
     */
    public function sendReminders(): void
    {
        $now = now();
        $startBuffer = $now->copy()->addMinutes(15);
        $endBuffer = $now->copy()->addMinutes(30);

        $takings = MedicationTaking::where('statut', 'prevue')
            ->whereBetween('prise_prevue', [$startBuffer, $endBuffer])
            ->with(['medicament', 'user'])
            ->get();

        foreach ($takings as $taking) {
            // Logique d'envoi de notification
            // À implémenter avec votre système de notifications
            Log::info('Rappel de médicament', [
                'user_id' => $taking->user_id,
                'medicament' => $taking->medicament->nom,
                'time' => $taking->prise_prevue->format('H:i'),
            ]);
        }
    }
}