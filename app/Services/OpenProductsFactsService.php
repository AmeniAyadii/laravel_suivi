<?php
// app/Services/OpenProductsFactsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenProductsFactsService extends BaseApiService
{
    protected function setupConfig(): void
    {
        $config = config('scan.providers.openproducts');
        $this->name = 'OpenProductsFacts';
        $this->baseUrl = $config['base_url'] ?? 'https://world.openproductsfacts.org/api/v0';
        $this->timeout = $config['timeout'] ?? 20;
        $this->priority = $config['priority'] ?? 2;
    }

    public function search(string $barcode): ?array
    {
        Log::info("🔍 {$this->name}: Recherche du code-barres: $barcode");

        $cleanBarcode = trim($barcode);
        Log::info("📤 {$this->name}: Code-barres utilisé: $cleanBarcode");

        // ✅ 1. Essayer avec OpenFoodFacts API v3 (plus fiable)
        try {
            $url = 'https://world.openfoodfacts.org/api/v3/product/' . $cleanBarcode . '.json';
            Log::info("📤 {$this->name}: Requête v3 vers: $url");
            
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'ScanApp/1.0',
                ])
                ->get($url);

            Log::info("📥 {$this->name}: Status v3: " . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                
                // ✅ Log de la réponse complète pour déboguer
                Log::info("📥 {$this->name}: Réponse v3: " . json_encode($data));
                
                // ✅ Vérifier le statut
                if (isset($data['status']) && $data['status'] == 1) {
                    Log::info("✅ {$this->name}: Produit trouvé via v3 (status 1)");
                    return $this->formatResponse($data['product']);
                }
                
                // ✅ Si status = 0 mais product existe (cas spécial)
                if (isset($data['status']) && $data['status'] == 0 && isset($data['product'])) {
                    Log::info("✅ {$this->name}: Produit trouvé via v3 (status 0 avec product)");
                    return $this->formatResponse($data['product']);
                }
                
                // ✅ Vérifier si le produit existe dans 'product' même sans status
                if (isset($data['product']) && !empty($data['product'])) {
                    Log::info("✅ {$this->name}: Produit trouvé via v3 (product existe)");
                    return $this->formatResponse($data['product']);
                }
            }

            // ✅ 2. Essayer avec l'API v0 (fallback)
            $v0Url = $this->baseUrl . '/product/' . $cleanBarcode . '.json';
            Log::info("📤 {$this->name}: Fallback v0 vers: $v0Url");
            
            $v0Response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'ScanApp/1.0',
                ])
                ->get($v0Url);

            Log::info("📥 {$this->name}: Status v0: " . $v0Response->status());

            if ($v0Response->successful()) {
                $data = $v0Response->json();
                Log::info("📥 {$this->name}: Réponse v0: " . json_encode($data));
                
                if (isset($data['status']) && $data['status'] == 1) {
                    Log::info("✅ {$this->name}: Produit trouvé via v0");
                    return $this->formatResponse($data['product']);
                }
                
                if (isset($data['status']) && $data['status'] == 0 && isset($data['product'])) {
                    Log::info("✅ {$this->name}: Produit trouvé via v0 (status 0)");
                    return $this->formatResponse($data['product']);
                }
            }

        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: Erreur - " . $e->getMessage());
        }

        Log::warning("⚠️ {$this->name}: Produit non trouvé pour $barcode");
        return null;
    }

    public function formatResponse(array $data): array
    {
        Log::info("📊 {$this->name}: Formatage du produit");

        // Extraire les ingrédients
        $ingredients = [];
        if (isset($data['ingredients'])) {
            foreach ($data['ingredients'] as $ing) {
                if (isset($ing['text'])) {
                    $ingredients[] = $ing['text'];
                }
            }
        }

        // Déterminer le type de produit
        $productType = $data['product_type'] ?? 'product';
        $typeLabels = [
            'food' => 'Alimentaire',
            'beauty' => 'Cosmétique',
            'petfood' => 'Alimentation animale',
            'product' => 'Produit',
        ];

        $result = [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['product_name_fr'] ?? 
                     $data['product_name'] ?? 
                     $data['generic_name'] ?? 
                     'Produit inconnu',
            'manufacturer' => $data['brands'] ?? null,
            'category' => $data['categories'] ?? null,
            'sub_category' => $data['categories_tags'][0] ?? null,
            'dosage' => $data['quantity'] ?? null,
            'image_url' => $data['image_front_url'] ?? null,
            'source' => 'OpenProductsFacts',
            'product_type' => $productType,
            'product_type_label' => $typeLabels[$productType] ?? 'Produit',
            'ingredients' => $ingredients,
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            'details' => [
                'brands_tags' => $data['brands_tags'] ?? [],
                'categories_tags' => $data['categories_tags'] ?? [],
                'countries' => $data['countries'] ?? null,
                'url' => $data['url'] ?? null,
                'nutriments' => $data['nutriments'] ?? [],
            ],
        ];

        Log::info("📊 {$this->name}: Résultat formaté: " . json_encode($result));
        return $result;
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get('https://world.openfoodfacts.org/api/v3/product/5449000000996.json');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}