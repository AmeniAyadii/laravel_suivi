<?php
// app/Services/FrenchMedicationApi.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrenchMedicationApi extends BaseApiService
{
    protected function setupConfig(): void
    {
        $this->name = 'FrenchMedicationAPI';
        $this->baseUrl = 'https://medicaments.api.gouv.fr/api';
        $this->timeout = 15;
        $this->priority = 0; // Priorité maximale
    }

    public function search(string $barcode): ?array
    {
        Log::info("🔍 {$this->name}: Recherche du code-barres: $barcode");

        try {
            // API des médicaments français
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/medicaments", [
                    'code' => $barcode,
                    'limit' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['data'])) {
                    Log::info("✅ {$this->name}: Médicament trouvé");
                    return $this->formatResponse($data['data'][0]);
                }
            }

            // Essayer une autre API
            $response = Http::timeout($this->timeout)
                ->get("https://api.openmedicines.org/v1/product/{$barcode}");

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true) {
                    Log::info("✅ {$this->name}: Médicament trouvé via OpenMedicines");
                    return $this->formatOpenMedicinesResponse($data['data'] ?? $data);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: " . $e->getMessage());
            return null;
        }
    }

    public function formatResponse(array $data): array
    {
        return [
            'barcode' => $data['code'] ?? $data['barcode'] ?? null,
            'nom' => $data['nom'] ?? $data['name'] ?? 'Médicament',
            'manufacturer' => $data['titulaire'] ?? $data['laboratory'] ?? null,
            'category' => $data['classe_therapeutique'] ?? $data['category'] ?? null,
            'sub_category' => $data['substance'] ?? null,
            'dosage' => $data['dosage'] ?? $data['forme'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'source' => $this->name,
            'product_type' => 'medicine',
            'product_type_label' => 'Médicament',
            'ingredients' => $data['substances_actives'] ?? [],
            'indications' => $data['indications'] ?? [],
            'contre_indications' => $data['contre_indications'] ?? [],
            'effets_secondaires' => $data['effets_indesirables'] ?? [],
            'posologie' => $data['posologie'] ?? null,
            'conservation' => $data['conservation'] ?? null,
        ];
    }

    private function formatOpenMedicinesResponse(array $data): array
    {
        return [
            'barcode' => $data['code'] ?? null,
            'nom' => $data['name'] ?? $data['nom'] ?? 'Médicament',
            'manufacturer' => $data['manufacturer'] ?? $data['laboratory'] ?? null,
            'category' => $data['category'] ?? null,
            'sub_category' => null,
            'dosage' => $data['dosage'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'source' => 'OpenMedicines',
            'product_type' => 'medicine',
            'product_type_label' => 'Médicament',
            'ingredients' => $data['ingredients'] ?? [],
            'indications' => $data['indications'] ?? [],
            'contre_indications' => $data['contraindications'] ?? [],
            'effets_secondaires' => $data['side_effects'] ?? [],
            'posologie' => $data['posology'] ?? null,
            'conservation' => $data['storage'] ?? null,
        ];
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/medicaments", ['limit' => 1]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}