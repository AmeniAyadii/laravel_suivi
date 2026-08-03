<?php

namespace App\Console\Commands;

use App\Models\RendezVous;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'notify:appointments';
    protected $description = 'Envoyer les rappels de rendez-vous';

    public function handle()
    {
        $tomorrow = now()->addDay();
        $rendezvous = RendezVous::whereBetween('date_heure', [
            $tomorrow->startOfDay(),
            $tomorrow->endOfDay()
        ])->get();

        $count = 0;
        foreach ($rendezvous as $rdv) {
            NotificationService::create(
                $rdv->user_id,
                '🔔 Rendez-vous demain',
                "Vous avez un rendez-vous avec Dr. {$rdv->medecin_nom} demain à " . Carbon::parse($rdv->date_heure)->format('H:i'),
                'warning',
                'calendar',
                '#F59E0B',
                ['rendezvous_id' => $rdv->id],
                '/rendezvous'
            );
            $count++;
        }

        $this->info("✅ $count rappels de rendez-vous envoyés");
    }
}