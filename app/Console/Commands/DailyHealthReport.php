<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MedicationTaking;
use App\Models\EmergencyAlert;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DailyHealthReport extends Command
{
    protected $signature = 'health:daily-report';
    protected $description = 'Générer le rapport de santé quotidien';

    public function handle()
    {
        $this->info('📊 Génération du rapport de santé quotidien...');

        $today = Carbon::today();
        $report = [
            'date' => $today->format('d/m/Y'),
            'statistiques' => [
                'utilisateurs_actifs' => User::where('is_active', true)->count(),
                'nouveaux_utilisateurs' => User::whereDate('created_at', $today)->count(),
            ],
            'medicaments' => [
                'prises_effectuees' => MedicationTaking::whereDate('prise_reelle', $today)
                    ->where('statut', 'prise')
                    ->count(),
                'prises_oubliees' => MedicationTaking::whereDate('prise_prevue', $today)
                    ->where('statut', 'oubliee')
                    ->count(),
                'prises_a_venir' => MedicationTaking::whereDate('prise_prevue', $today)
                    ->where('statut', 'prevue')
                    ->count(),
            ],
            'urgences' => [
                'alertes' => EmergencyAlert::whereDate('created_at', $today)->count(),
                'alertes_en_cours' => EmergencyAlert::where('statut', 'en_cours')->count(),
            ],
            'systeme' => Cache::get('system_health', []),
        ];

        // Sauvegarder le rapport
        $filename = storage_path("logs/reports/health_{$today->format('Y-m-d')}.json");
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("✅ Rapport généré : {$filename}");
        return Command::SUCCESS;
    }
}