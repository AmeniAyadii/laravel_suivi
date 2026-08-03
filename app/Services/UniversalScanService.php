<?php
// app/Services/UniversalScanService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;  // ✅ IMPORTANT : Ajouter cette ligne

class UniversalScanService extends BaseApiService
{
    protected function setupConfig(): void
    {
        $config = config('scan.providers.universal_scan');
        $this->name = 'UniversalScan';
        $this->baseUrl = $config['base_url'];
        $this->timeout = $config['timeout'];
        $this->priority = $config['priority'] ?? 3;
    }

    public function search(string $barcode): ?array
    {
        Log::info("🔍 {$this->name}: Recherche du code-barres: $barcode");

        $url = $this->baseUrl . '/' . $barcode . '?product_type=all';
        
        try {
            $client = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders());

            $response = $client->get($url);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return null;
            }

            $product = $data['product'] ?? [];
            $productType = $product['product_type'] ?? 'product';

            // Utiliser le bon formateur selon le type
            switch ($productType) {
                case 'food':
                    return $this->formatFoodProduct($product);
                case 'beauty':
                    return $this->formatBeautyProduct($product);
                case 'petfood':
                    return $this->formatPetFoodProduct($product);
                default:
                    return $this->formatGenericProduct($product);
            }

        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: " . $e->getMessage());
            return null;
        }
    }

    private function formatFoodProduct(array $data): array
    {
        $ingredients = [];
        if (isset($data['ingredients'])) {
            foreach ($data['ingredients'] as $ing) {
                if (isset($ing['text'])) {
                    $ingredients[] = $ing['text'];
                }
            }
        }

        return [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['product_name'] ?? 'Aliment inconnu',
            'manufacturer' => $data['brands'] ?? null,
            'category' => $data['categories'] ?? 'Alimentaire',
            'sub_category' => $data['categories_tags'][0] ?? null,
            'dosage' => null,
            'image_url' => $data['image_front_url'] ?? null,
            'source' => 'UniversalScan',
            'product_type' => 'food',
            'product_type_label' => 'Alimentaire',
            'ingredients' => $ingredients,
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            'details' => [
                'nutrition_grade' => $data['nutrition_grades'] ?? null,
                'nutriments' => $data['nutriments'] ?? [],
                'countries' => $data['countries'] ?? null,
            ],
        ];
    }

    private function formatBeautyProduct(array $data): array
    {
        $ingredients = [];
        if (isset($data['ingredients'])) {
            foreach ($data['ingredients'] as $ing) {
                if (isset($ing['text'])) {
                    $ingredients[] = $ing['text'];
                }
            }
        }

        return [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['product_name'] ?? 'Cosmétique inconnu',
            'manufacturer' => $data['brands'] ?? null,
            'category' => $data['categories'] ?? 'Cosmétique',
            'sub_category' => $data['categories_tags'][0] ?? null,
            'dosage' => null,
            'image_url' => $data['image_front_url'] ?? null,
            'source' => 'UniversalScan',
            'product_type' => 'beauty',
            'product_type_label' => 'Cosmétique',
            'ingredients' => $ingredients,
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            'details' => [
                'cosmetics_tags' => $data['cosmetics_tags'] ?? [],
                'countries' => $data['countries'] ?? null,
            ],
        ];
    }

    private function formatPetFoodProduct(array $data): array
    {
        return [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['product_name'] ?? 'Aliment animal inconnu',
            'manufacturer' => $data['brands'] ?? null,
            'category' => $data['categories'] ?? 'Alimentation animale',
            'sub_category' => $data['categories_tags'][0] ?? null,
            'dosage' => null,
            'image_url' => $data['image_front_url'] ?? null,
            'source' => 'UniversalScan',
            'product_type' => 'petfood',
            'product_type_label' => 'Alimentation animale',
            'ingredients' => [],
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            'details' => [
                'petfood_tags' => $data['petfood_tags'] ?? [],
                'countries' => $data['countries'] ?? null,
            ],
        ];
    }

    private function formatGenericProduct(array $data): array
    {
        return [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['product_name'] ?? 'Produit inconnu',
            'manufacturer' => $data['brands'] ?? null,
            'category' => $data['categories'] ?? 'Produit',
            'sub_category' => null,
            'dosage' => null,
            'image_url' => $data['image_front_url'] ?? null,
            'source' => 'UniversalScan',
            'product_type' => 'product',
            'product_type_label' => 'Produit',
            'ingredients' => [],
            'indications' => [],
            'contre_indications' => [],
            'effets_secondaires' => [],
            'posologie' => null,
            'conservation' => null,
            'details' => [
                'countries' => $data['countries'] ?? null,
                'url' => $data['url'] ?? null,
            ],
        ];
    }

    public function formatResponse(array $data): array
    {
        return $data;
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get($this->baseUrl . '/3400934234502?product_type=all');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}