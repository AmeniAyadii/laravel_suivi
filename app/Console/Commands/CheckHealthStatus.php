<?php

namespace App\Console\Commands;

use App\Services\LLMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckHealthStatus extends Command
{
    protected $signature = 'health:check';
    protected $description = 'Vérifier la santé du système';

    public function handle()
    {
        $this->info('🩺 Vérification de la santé du système...');

        $status = [
            'timestamp' => now()->toDateTimeString(),
            'services' => [],
            'overall' => 'healthy',
        ];

        // 1. Vérifier la base de données
        try {
            DB::connection()->getPdo();
            $status['services']['database'] = 'healthy';
            $this->line('✅ Base de données : OK');
        } catch (\Exception $e) {
            $status['services']['database'] = 'unhealthy';
            $status['overall'] = 'unhealthy';
            $this->error('❌ Base de données : ERR');
        }

        // 2. Vérifier Ollama/IA
        try {
            $llmService = app(LLMService::class);
            $isHealthy = $llmService->checkHealth();
            $status['services']['ollama'] = $isHealthy ? 'healthy' : 'unhealthy';
            $this->line($isHealthy ? '✅ Ollama : OK' : '⚠️ Ollama : Dégradé');
            
            if (!$isHealthy) {
                $status['overall'] = 'degraded';
            }
        } catch (\Exception $e) {
            $status['services']['ollama'] = 'unhealthy';
            $status['overall'] = 'unhealthy';
            $this->error('❌ Ollama : ERR');
        }

        // 3. Vérifier le cache
        try {
            Cache::put('health_check_test', 'ok', 60);
            $cached = Cache::get('health_check_test') === 'ok';
            $status['services']['cache'] = $cached ? 'healthy' : 'unhealthy';
            $this->line($cached ? '✅ Cache : OK' : '❌ Cache : ERR');
            
            if (!$cached) {
                $status['overall'] = 'unhealthy';
            }
        } catch (\Exception $e) {
            $status['services']['cache'] = 'unhealthy';
            $status['overall'] = 'unhealthy';
            $this->error('❌ Cache : ERR');
        }

        // 4. Vérifier l'espace disque
        $freeSpace = disk_free_space(base_path()) / 1024 / 1024 / 1024;
        $status['services']['disk'] = $freeSpace > 1 ? 'healthy' : 'warning';
        $status['disk_free_gb'] = round($freeSpace, 2);
        
        if ($freeSpace < 1) {
            $status['overall'] = 'warning';
            $this->warn("⚠️ Espace disque faible: {$freeSpace} Go");
        } else {
            $this->line("✅ Espace disque : {$freeSpace} Go");
        }

        // Enregistrer le statut dans le cache
        Cache::put('system_health', $status, 3600);

        // Enregistrer les anomalies
        if ($status['overall'] !== 'healthy') {
            Log::warning('Système en état dégradé', $status);
            
            // Envoyer une alerte si critique
            if ($status['overall'] === 'unhealthy') {
                // TODO: Envoyer une notification à l'admin
                $this->error('🚨 ALERTE : Système en état critique !');
            }
        }

        $this->info("✅ Vérification terminée. Statut global: {$status['overall']}");
        return Command::SUCCESS;
    }
}