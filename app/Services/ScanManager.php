<?php
// app/Services/ScanManager.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScanManager
{
    protected $providers = [];
    protected $fallbackData;

    public function __construct()
    {
        $this->loadProviders();
        $this->fallbackData = config('scan.fallback', []);
    }

    private function loadProviders(): void
    {
        $providersConfig = config('scan.providers', []);
        $this->providers = [];

        foreach ($providersConfig as $name => $config) {
            if (!$config['enabled']) {
                continue;
            }

            $className = $this->getProviderClassName($name);
            
            if (class_exists($className)) {
                $provider = new $className();
                $this->providers[] = $provider;
                Log::info("✅ Provider chargé: {$provider->getName()}");
            }
        }

        // Trier par priorité
        usort($this->providers, function ($a, $b) {
            return $a->getPriority() - $b->getPriority();
        });
    }

    private function getProviderClassName(string $name): string
    {
        $map = [
            'openfda' => 'App\\Services\\OpenFDAService',
            'openproducts' => 'App\\Services\\OpenProductsFactsService',
            'universal_scan' => 'App\\Services\\UniversalScanService',
        ];

        return $map[$name] ?? 'App\\Services\\' . ucfirst($name) . 'Service';
    }

    /**
     * Rechercher un produit par code-barres
     */
    public function search(string $barcode): array
    {
        Log::info('🔍 ScanManager: Recherche du code-barres: ' . $barcode);

        // 1. Vérifier le cache
        $cacheKey = 'scan_' . $barcode;
        if ($cached = Cache::get($cacheKey)) {
            Log::info('✅ ScanManager: Données du cache');
            return $cached;
        }

        // 2. Rechercher avec les providers
        foreach ($this->providers as $provider) {
            Log::info("📤 ScanManager: Tentative avec {$provider->getName()}");
            
            try {
                $result = $provider->search($barcode);
                
                if ($result) {
                    Log::info("✅ ScanManager: Trouvé avec {$provider->getName()}");
                    
                    // Ajouter des métadonnées
                    $result['_meta'] = [
                        'source' => $provider->getName(),
                        'timestamp' => now()->toISOString(),
                        'barcode' => $barcode,
                        'found' => true,
                    ];
                    
                    // Mettre en cache
                    Cache::put($cacheKey, $result, config('scan.cache.ttl', 86400));
                    
                    return $result;
                }
            } catch (\Exception $e) {
                Log::error("❌ ScanManager: Erreur avec {$provider->getName()}: " . $e->getMessage());
                continue;
            }
        }

        // 3. Retourner les données de fallback
        Log::warning('❌ ScanManager: Aucun produit trouvé');
        
        $fallback = [
            'barcode' => $barcode,
            'nom' => $this->fallbackData['nom'] ?? 'Produit non trouvé',
            'manufacturer' => $this->fallbackData['manufacturer'] ?? 'Inconnu',
            'category' => $this->fallbackData['category'] ?? 'Non spécifié',
            'sub_category' => null,
            'dosage' => null,
            'image_url' => null,
            'source' => 'ScanManager',
            'product_type' => 'unknown',
            'product_type_label' => 'Non identifié',
            'ingredients' => [],
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            '_meta' => [
                'source' => 'ScanManager (Fallback)',
                'timestamp' => now()->toISOString(),
                'barcode' => $barcode,
                'found' => false,
                'message' => $this->fallbackData['message'] ?? 'Aucune information disponible',
                'suggestions' => $this->fallbackData['suggestions'] ?? [],
            ],
        ];

        // Mettre en cache même les données de fallback (TTL plus court)
        Cache::put($cacheKey, $fallback, 3600); // 1 heure

        return $fallback;
    }

    /**
     * Récupérer tous les providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Tester la disponibilité de tous les providers
     */
    public function testProviders(): array
    {
        $results = [];

        foreach ($this->providers as $provider) {
            try {
                $available = $provider->isAvailable();
                $results[$provider->getName()] = [
                    'available' => $available,
                    'priority' => $provider->getPriority(),
                ];
            } catch (\Exception $e) {
                $results[$provider->getName()] = [
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Vider le cache
     */
    public function clearCache(string $barcode = null): void
    {
        if ($barcode) {
            Cache::forget('scan_' . $barcode);
            Log::info('🗑️ ScanManager: Cache vidé pour: ' . $barcode);
        } else {
            Log::warning('⚠️ ScanManager: Vider tout le cache est risqué');
        }
    }

    /**
     * Voir le contenu du cache
     */
    public function viewCache(string $barcode): ?array
    {
        $cacheKey = 'scan_' . $barcode;
        return Cache::get($cacheKey);
    }

    /**
     * Voir tous les caches
     */
    public function viewAllCache(): array
    {
        $cacheFiles = glob(storage_path('framework/cache/*'));
        $results = [];
        
        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                $content = file_get_contents($file);
                if (strpos($content, 'scan_') !== false) {
                    $results[] = basename($file);
                }
            }
        }
        
        return $results;
    }
}