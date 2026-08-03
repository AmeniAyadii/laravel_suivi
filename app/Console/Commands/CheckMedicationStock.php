<?php

namespace App\Console\Commands;

use App\Models\Medicament;
use App\Notifications\StockAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CheckMedicationStock extends Command
{
    protected $signature = 'medication:check-stock';
    protected $description = 'Vérifier les stocks de médicaments et envoyer des alertes';

    public function handle()
    {
        $this->info('📊 Vérification des stocks de médicaments...');

        // ✅ Vérifier que la table existe
        if (!Schema::hasTable('medicaments')) {
            $this->error('❌ La table medicaments n\'existe pas !');
            return Command::FAILURE;
        }

        // ✅ Récupérer les médicaments actifs
        try {
            $medications = Medicament::where('statut', 'actif')
                ->where('stock_actuel', '>', 0)
                ->get();
            
            $this->info("📋 {$medications->count()} médicaments actifs trouvés.");
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la récupération des médicaments: ' . $e->getMessage());
            
            // Fallback: récupérer tous les médicaments
            $medications = Medicament::all();
            $this->warn('⚠️ Utilisation du fallback: ' . $medications->count() . ' médicaments');
        }

        if ($medications->isEmpty()) {
            $this->info('✅ Aucun médicament actif trouvé.');
            return Command::SUCCESS;
        }

        $alerts = [];
        $stockLowCount = 0;
        $stockEmptyCount = 0;

        foreach ($medications as $medicament) {
            // ✅ Calculer les jours restants
            $joursRestants = $this->calculateDaysRemaining($medicament);
            
            // ✅ Stock bas (entre 1 et 3 jours)
            if ($joursRestants <= 3 && $joursRestants > 0) {
                $alerts[] = $medicament;
                $stockLowCount++;
                $this->warn("⚠️ Stock bas : {$medicament->nom} - {$joursRestants} jours restants");
            } 
            // ✅ Stock épuisé (0 jour restant)
            elseif ($joursRestants <= 0 && $medicament->stock_actuel > 0) {
                $alerts[] = $medicament;
                $stockEmptyCount++;
                $this->error("🚨 Stock critique : {$medicament->nom} - Plus de traitement disponible");
            }
        }

        // ✅ Résumé des alertes
        if (count($alerts) === 0) {
            $this->info('✅ Aucune alerte de stock ! Tous les médicaments sont bien approvisionnés.');
        } else {
            $this->line('');
            $this->line('📊 RÉSUMÉ DES ALERTES:');
            $this->line("   • Stock bas : {$stockLowCount} médicament(s)");
            $this->line("   • Stock épuisé : {$stockEmptyCount} médicament(s)");
            $this->line('');

            // ✅ Vérifier si la notification existe
            if (class_exists(StockAlertNotification::class)) {
                $this->info('📧 Envoi des notifications...');
                
                // Envoyer les alertes
                foreach ($alerts as $medicament) {
                    try {
                        $user = $medicament->user;
                        if ($user) {
                            $user->notify(new StockAlertNotification($medicament));
                            $this->line("✅ Alerte envoyée pour {$medicament->nom}");
                        } else {
                            $this->warn("⚠️ Aucun utilisateur associé au médicament {$medicament->nom}");
                        }
                    } catch (\Exception $e) {
                        Log::error('Erreur envoi alerte stock: ' . $e->getMessage());
                        $this->error("❌ Erreur pour {$medicament->nom}: " . $e->getMessage());
                    }
                }
            } else {
                $this->warn('⚠️ La classe StockAlertNotification n\'existe pas. Les alertes ne sont pas envoyées.');
                $this->line('💡 Créez la notification avec: php artisan make:notification StockAlertNotification');
            }
        }

        // ✅ Afficher la liste des médicaments à risque
        if (!empty($alerts)) {
            $this->line('');
            $this->info('📋 Liste des médicaments à renouveler:');
            foreach ($alerts as $index => $medicament) {
                $jours = $this->calculateDaysRemaining($medicament);
                $status = $jours <= 0 ? '🔴 ÉPUISÉ' : '🟠 FAIBLE';
                $this->line("   " . ($index + 1) . ". {$status} {$medicament->nom} - Stock: {$medicament->stock_actuel} - {$jours} jours restants");
            }
        }

        $this->line('');
        $this->info("✅ Commande terminée avec succès !");
        return Command::SUCCESS;
    }

    /**
     * Calculer les jours restants de traitement
     */
    private function calculateDaysRemaining($medicament): int
    {
        if ($medicament->stock_actuel <= 0) {
            return 0;
        }

        // ✅ Déterminer le nombre de prises par jour
        $prisesParJour = 1; // Par défaut

        // Essayer d'utiliser la colonne 'frequence'
        if (isset($medicament->frequence) && !empty($medicament->frequence)) {
            $frequence = strtolower($medicament->frequence);
            
            if (str_contains($frequence, '8h') || str_contains($frequence, '8 heures')) {
                $prisesParJour = 3; // toutes les 8h = 3 fois par jour
            } elseif (str_contains($frequence, '12h') || str_contains($frequence, '12 heures')) {
                $prisesParJour = 2; // toutes les 12h = 2 fois par jour
            } elseif (str_contains($frequence, '24h') || str_contains($frequence, '24 heures') || str_contains($frequence, 'jour')) {
                $prisesParJour = 1; // 1 fois par jour
            } elseif (str_contains($frequence, 'matin') || str_contains($frequence, 'soir')) {
                // Vérifier si c'est matin ET soir ou juste matin/soir
                if (str_contains($frequence, 'matin') && str_contains($frequence, 'soir')) {
                    $prisesParJour = 2;
                } else {
                    $prisesParJour = 1;
                }
            }
        }

        // ✅ Si 'horaires_prises' existe (colonnes ajoutées plus tard)
        if (isset($medicament->horaires_prises) && !empty($medicament->horaires_prises)) {
            try {
                $horaires = is_array($medicament->horaires_prises) 
                    ? $medicament->horaires_prises 
                    : json_decode($medicament->horaires_prises, true);
                
                if (is_array($horaires) && count($horaires) > 0) {
                    $prisesParJour = count($horaires);
                }
            } catch (\Exception $e) {
                // Ignorer l'erreur
            }
        }

        // ✅ Calculer les jours restants
        $joursRestants = intval($medicament->stock_actuel / $prisesParJour);
        
        // ✅ Vérifier si une date de fin est définie
        if (isset($medicament->date_fin) && !empty($medicament->date_fin)) {
            try {
                $dateFin = Carbon::parse($medicament->date_fin);
                $joursJusquaFin = Carbon::now()->diffInDays($dateFin, false);
                
                // Prendre le plus petit des deux
                if ($joursJusquaFin > 0 && $joursJusquaFin < $joursRestants) {
                    $joursRestants = $joursJusquaFin;
                }
            } catch (\Exception $e) {
                // Ignorer l'erreur
            }
        }

        return max(0, $joursRestants);
    }
}