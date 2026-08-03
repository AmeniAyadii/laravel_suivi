<?php

namespace App\Console\Commands;

use App\Models\Medicament;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendMedicationReminders extends Command
{
    protected $signature = 'notify:medications';
    protected $description = 'Envoyer les rappels de médicaments';

    public function handle()
    {
        $medicaments = Medicament::where('prochaine_prise', '<=', now()->addHour())
            ->where('prochaine_prise', '>=', now())
            ->get();

        $count = 0;
        foreach ($medicaments as $medicament) {
            NotificationService::create(
                $medicament->user_id,
                '⏰ Rappel de médicament',
                "N'oubliez pas de prendre {$medicament->nom} ({$medicament->dosage})",
                'warning',
                'alarm',
                '#F59E0B',
                ['medicament_id' => $medicament->id],
                '/medicaments'
            );
            $count++;
        }

        $this->info("✅ $count rappels de médicaments envoyés");
    }
}