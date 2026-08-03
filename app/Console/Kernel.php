<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commandes personnalisées existantes
        Commands\SendMedicationReminders::class,
        Commands\CheckMedicationStock::class,
        Commands\GenerateObservanceReport::class,
        Commands\CleanExpiredData::class,
        Commands\SendAppointmentReminders::class,
        Commands\CheckHealthStatus::class,
        
        // ✅ Nouvelle commande pour les défis
        Commands\GenerateDailyChallenges::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // ============================================
        // 1. DÉFIS QUOTIDIENS
        // ============================================
        
        // ✅ Générer 7 jours de défis chaque jour à minuit
        $schedule->command('challenges:generate-daily 7')
                 ->daily()
                 ->at('00:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/challenges.log'));
        
        // ✅ Générer 7 jours de défis en cas d'échec (à 6h)
        $schedule->command('challenges:generate-daily 7')
                 ->daily()
                 ->at('06:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/challenges-retry.log'));

        // ============================================
        // 2. MÉDICAMENTS - RAPPELS ET SUIVI
        // ============================================
        
        // Envoyer les rappels de médicaments toutes les 15 minutes
        $schedule->command('medication:send-reminders')
            ->everyFifteenMinutes()
            ->between('6:00', '22:00') // Uniquement entre 6h et 22h
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/medication-reminders.log'));
        
        // Vérifier les stocks de médicaments chaque jour à 8h
        $schedule->command('medication:check-stock')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/medication-stock.log'));
        
        // Générer le rapport d'observance chaque semaine (dimanche à 20h)
        $schedule->command('medication:observance-report')
            ->weekly()
            ->sundays()
            ->at('20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/observance-report.log'));

        // ============================================
        // 3. RENDEZ-VOUS - RAPPELS
        // ============================================
        
        // Envoyer les rappels de rendez-vous 24h avant
        $schedule->command('appointment:send-reminders')
            ->everyThirtyMinutes()
            ->between('8:00', '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/appointment-reminders.log'));
        
        // Nettoyer les rendez-vous passés (tous les jours à 2h)
        $schedule->command('appointment:clean-past')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/appointment-clean.log'));

        // ============================================
        // 4. NETTOYAGE ET MAINTENANCE
        // ============================================
        
        // Nettoyer les données expirées (tous les jours à 3h)
        $schedule->command('data:clean-expired')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup.log'));
        
        // Nettoyer les tokens expirés (tous les jours à 4h)
        $schedule->command('tokens:clean-expired')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tokens-clean.log'));

        // ============================================
        // 5. SANTÉ ET SURVEILLANCE
        // ============================================
        
        // Vérifier la santé du système toutes les heures
        $schedule->command('health:check')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/health-check.log'));
        
        // Générer un rapport de santé global chaque jour à 23h
        $schedule->command('health:daily-report')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/health-daily-report.log'));

        // ============================================
        // 6. BASE DE DONNÉES - SAUVEGARDE
        // ============================================
        
        // Sauvegarde automatique de la base de données chaque nuit
        $schedule->command('backup:run --only-db')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup.log'));
        
        // Nettoyer les anciennes sauvegardes (tous les dimanches à 2h)
        $schedule->command('backup:clean')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup-clean.log'));

        // ============================================
        // 7. STATISTIQUES ET ANALYTIQUES
        // ============================================
        
        // Calculer les statistiques d'utilisation (tous les jours à 23h30)
        $schedule->command('analytics:calculate')
            ->dailyAt('23:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/analytics.log'));
        
        // Calculer les statistiques des objectifs (tous les jours à 22h)
        $schedule->command('goals:calculate-stats')
            ->dailyAt('22:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/goals-stats.log'));

        // ============================================
        // 8. SYSTÈME - SURVEILLANCE
        // ============================================
        
        // Vérifier que les queues fonctionnent (toutes les 5 minutes)
        $schedule->command('queue:check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/queue-check.log'));
        
        // Nettoyer les fichiers temporaires (tous les jours à 5h)
        $schedule->command('storage:clean-temp')
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/storage-clean.log'));
        
        // Optimiser la base de données (tous les dimanches à 3h)
        $schedule->command('db:optimize')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/db-optimize.log'));

        // ============================================
        // 9. NOTIFICATIONS PUSH
        // ============================================
        
        // Envoyer les notifications push en attente (toutes les 10 minutes)
        $schedule->command('notifications:send-push')
            ->everyTenMinutes()
            ->between('7:00', '22:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/push-notifications.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        return 'Europe/Paris';
    }
}