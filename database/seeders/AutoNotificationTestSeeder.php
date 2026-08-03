<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class AutoNotificationTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->error('Aucun utilisateur trouvé');
            return;
        }

        // ✅ Créer une instance du service
        $notificationService = app(NotificationService::class);

        // 1. Notification de bienvenue
        $notificationService->create(
            $user->id,
            '👋 Bienvenue sur Suivi Santé AI',
            'Votre application de suivi santé est prête ! Commencez à enregistrer vos médicaments.',
            'success',
            'health_and_safety',
            '#6C63FF',
            null,
            '/dashboard'
        );

        // 2. Conseil santé
        $notificationService->create(
            $user->id,
            '💡 Conseil santé du jour',
            'Pensez à boire 2 litres d\'eau par jour pour rester hydraté.',
            'info',
            'water',
            '#3B82F6'
        );

        // 3. Rappel test
        $notificationService->create(
            $user->id,
            '⏰ Test rappel automatique',
            'Ceci est une notification de test du système automatique.',
            'warning',
            'alarm',
            '#F59E0B'
        );

        $this->command->info('✅ Notifications de test créées automatiquement !');
    }
}