<?php

namespace App\Console\Commands;

use App\Models\Medicament;
use App\Models\MedicationTaking;
use App\Models\EmergencyAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanExpiredData extends Command
{
    protected $signature = 'data:clean-expired';
    protected $description = 'Nettoyer les données expirées';

    public function handle()
    {
        $this->info('🧹 Nettoyage des données expirées...');

        // 1. Nettoyer les médicaments périmés
        $expiredMedications = Medicament::where('expiry_date', '<', now()->subMonth())
            ->update(['statut' => 'termine']);
        
        $this->line("✅ Médicaments périmés désactivés : {$expiredMedications}");

        // 2. Archiver les anciennes prises (plus de 6 mois)
        $oldTakings = MedicationTaking::where('prise_prevue', '<', now()->subMonths(6))
            ->where('statut', 'prise')
            ->update(['notes' => 'Archivé']);

        $this->line("✅ Anciennes prises archivées : {$oldTakings}");

        // 3. Supprimer les alertes d'urgence résolues (plus de 30 jours)
        $deletedAlerts = EmergencyAlert::where('statut', 'terminee')
            ->where('resolue_a', '<', now()->subDays(30))
            ->delete();

        $this->line("✅ Alertes d'urgence supprimées : {$deletedAlerts}");

        // 4. Nettoyer les logs de plus de 30 jours
        $logFiles = glob(storage_path('logs/*.log'));
        $now = Carbon::now();
        $deletedLogs = 0;

        foreach ($logFiles as $file) {
            if (is_file($file)) {
                $fileTime = Carbon::createFromTimestamp(filemtime($file));
                if ($fileTime->diffInDays($now) > 30) {
                    unlink($file);
                    $deletedLogs++;
                }
            }
        }

        $this->line("✅ Logs supprimés : {$deletedLogs}");

        $this->info('✅ Nettoyage terminé avec succès !');
        return Command::SUCCESS;
    }
}