<?php
// app/Console/Commands/SendNotifications.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log; // ✅ AJOUTER CETTE LIGNE

class SendNotifications extends Command
{
    protected $signature = 'notifications:send';
    protected $description = 'Envoyer les notifications de rappel';

    public function handle(NotificationService $notificationService)
    {
        $this->info('🔔 Envoi des notifications...');
        
        try {
            $notificationService->checkAppointments();
            $notificationService->checkMedications();
            
            $this->info('✅ Notifications envoyées !');
        } catch (\Exception $e) {
            $this->error('❌ Erreur: ' . $e->getMessage());
            Log::error('Erreur SendNotifications: ' . $e->getMessage());
        }
    }
}