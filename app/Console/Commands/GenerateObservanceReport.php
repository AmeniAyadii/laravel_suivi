<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MedicationTaking;
use App\Services\MedicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateObservanceReport extends Command
{
    protected $signature = 'medication:observance-report';
    protected $description = 'Générer le rapport d\'observance hebdomadaire';

    protected MedicationService $medicationService;

    public function __construct(MedicationService $medicationService)
    {
        parent::__construct();
        $this->medicationService = $medicationService;
    }

    public function handle()
    {
        $this->info('📈 Génération du rapport d\'observance...');

        $users = User::where('is_active', true)->get();
        $count = 0;

        foreach ($users as $user) {
            try {
                $summary = $this->medicationService->getObservanceSummary(
                    $user->id,
                    'week'
                );

                // Enregistrer le rapport
                $report = [
                    'user_id' => $user->id,
                    'user_name' => $user->nom . ' ' . $user->prenom,
                    'period' => 'week',
                    'generated_at' => now()->toDateTimeString(),
                    'data' => $summary,
                ];

                // Sauvegarder le rapport dans un fichier
                $filename = storage_path("logs/reports/observance_{$user->id}_{$summary['taux_observance']}.json");
                file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));

                $count++;
                $this->line("✅ Rapport généré pour {$user->nom} - Taux: {$summary['taux_observance']}%");

            } catch (\Exception $e) {
                Log::error('Erreur génération rapport: ' . $e->getMessage());
                $this->error("❌ Erreur pour l'utilisateur {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ {$count} rapports générés avec succès !");
        return Command::SUCCESS;
    }
}