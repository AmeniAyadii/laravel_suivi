<?php
// app/Services/BaseApiService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

abstract class BaseApiService
{
    protected $name;
    protected $baseUrl;
    protected $timeout;
    protected $cacheTtl;
    protected $priority;

    public function __construct()
    {
        $this->cacheTtl = config('scan.cache.ttl', 86400);
        $this->setupConfig();
    }

    abstract protected function setupConfig(): void;
    abstract public function search(string $barcode): ?array;
    abstract public function formatResponse(array $data): array;
    abstract public function isAvailable(): bool;

    protected function request(string $endpoint, array $params = [], string $method = 'GET'): ?array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);
        
        // Vérifier le cache
        if (config('scan.cache.enabled', true)) {
            if ($cached = Cache::get($cacheKey)) {
                Log::info("✅ {$this->name}: Données du cache");
                return $cached;
            }
        }

        try {
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
            Log::info("🔍 {$this->name}: Requête vers $url");

            // Utiliser le HTTP Client de Laravel
            $client = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders());

            $response = $client->$method($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                
                // Mettre en cache
                if (config('scan.cache.enabled', true)) {
                    Cache::put($cacheKey, $data, $this->cacheTtl);
                }
                
                Log::info("✅ {$this->name}: Requête réussie");
                return $data;
            }

            Log::warning("⚠️ {$this->name}: HTTP " . $response->status());
            return null;

        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: " . $e->getMessage());
            return null;
        }
    }

    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'ScanApp/1.0',
        ];
    }

    protected function getCacheKey(string $endpoint, array $params): string
    {
        $prefix = config('scan.cache.prefix', 'scan_');
        $key = $this->name . '_' . $endpoint . '_' . md5(json_encode($params));
        return $prefix . $key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}