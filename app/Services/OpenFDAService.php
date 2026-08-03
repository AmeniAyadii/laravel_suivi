<?php
// app/Services/OpenFDAService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenFDAService extends BaseApiService
{
    protected function setupConfig(): void
    {
        $config = config('scan.providers.openfda');
        $this->name = 'OpenFDA';
        // ✅ Utiliser ndc.json car il contient active_ingredients
        $this->baseUrl = 'https://api.fda.gov/drug/ndc.json';
        $this->timeout = $config['timeout'] ?? 30;
        $this->priority = $config['priority'] ?? 1;
    }

    public function search(string $barcode): ?array
    {
        Log::info("🔍 {$this->name}: Recherche du code-barres: $barcode");

        $originalBarcode = $barcode;
        $barcode = $this->cleanBarcode($barcode);

        Log::info("📤 {$this->name}: Code-barres original: $originalBarcode");
        Log::info("📤 {$this->name}: Code-barres nettoyé: $barcode");

        if (empty($barcode)) {
            Log::warning("⚠️ {$this->name}: Code-barres vide après nettoyage");
            return null;
        }

        // 1. Recherche avec le format original (avec tiret)
        $params = [
            'search' => 'product_ndc:"' . $originalBarcode . '"',
            'limit' => 1,
        ];

        $response = $this->request('', $params);

        if ($response && !empty($response['results'])) {
            Log::info("✅ {$this->name}: Trouvé par NDC (original)");
            $result = $this->formatResponse($response['results'][0]);
            
            // ✅ Ajouter les logs pour déboguer
            Log::info("📊 Données formatées: " . json_encode($result));
            
            return $result;
        }

        // 2. Recherche sans tiret
        $params = [
            'search' => 'product_ndc:"' . $barcode . '"',
            'limit' => 1,
        ];

        $response = $this->request('', $params);

        if ($response && !empty($response['results'])) {
            Log::info("✅ {$this->name}: Trouvé par NDC (sans tiret)");
            $result = $this->formatResponse($response['results'][0]);
            Log::info("📊 Données formatées: " . json_encode($result));
            return $result;
        }

        // 3. Essayer avec label.json pour plus de détails
        $labelResult = $this->searchLabel($originalBarcode, $barcode);
        if ($labelResult) {
            return $labelResult;
        }

        Log::warning("⚠️ {$this->name}: Aucun résultat pour $barcode");
        return null;
    }

    /**
     * Rechercher dans label.json pour plus de détails
     */
    private function searchLabel(string $originalBarcode, string $barcode): ?array
    {
        $labelUrl = 'https://api.fda.gov/drug/label.json';
        
        // Essayer avec le format original
        $params = [
            'search' => 'openfda.product_ndc:"' . $originalBarcode . '"',
            'limit' => 1,
        ];

        $response = $this->requestBase($labelUrl, $params);

        if ($response && !empty($response['results'])) {
            Log::info("✅ {$this->name}: Détails trouvés dans label.json");
            $data = $response['results'][0];
            
            // Fusionner avec les données ndc
            return $this->formatResponseWithLabel($data);
        }

        return null;
    }

    /**
     * Requête HTTP de base
     */
    private function requestBase(string $url, array $params): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }
            Log::warning("⚠️ {$this->name}: HTTP " . $response->status() . " pour $url");
            return null;
        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Formater la réponse avec les données de ndc.json
     */
    public function formatResponse(array $data): array
    {
        $openfda = $data['openfda'] ?? [];

        // ✅ Récupérer les ingrédients actifs
        $ingredients = [];
        if (isset($data['active_ingredients'])) {
            foreach ($data['active_ingredients'] as $ing) {
                if (isset($ing['name'])) {
                    $ingredients[] = $ing['name'];
                }
            }
        } elseif (isset($openfda['ingredient'])) {
            $ingredients = is_array($openfda['ingredient']) 
                ? $openfda['ingredient'] 
                : [$openfda['ingredient']];
        }

        // ✅ Récupérer les indications
        $indications = [];
        if (isset($data['indications_and_usage'])) {
            $indications = is_array($data['indications_and_usage']) 
                ? $data['indications_and_usage'] 
                : [$data['indications_and_usage']];
        }

        // ✅ Récupérer les contre-indications
        $contreIndications = [];
        if (isset($data['contraindications'])) {
            $contreIndications = is_array($data['contraindications']) 
                ? $data['contraindications'] 
                : [$data['contraindications']];
        }

        // ✅ Récupérer les effets secondaires
        $effetsSecondaires = [];
        if (isset($data['adverse_reactions'])) {
            $effetsSecondaires = is_array($data['adverse_reactions']) 
                ? $data['adverse_reactions'] 
                : [$data['adverse_reactions']];
        }

        // ✅ Récupérer la posologie
        $posologie = null;
        if (isset($data['dosage_and_administration'])) {
            $posologie = is_array($data['dosage_and_administration']) 
                ? implode(' ', $data['dosage_and_administration']) 
                : $data['dosage_and_administration'];
        }

        // ✅ Récupérer la conservation
        $conservation = null;
        if (isset($data['storage_and_handling'])) {
            $conservation = is_array($data['storage_and_handling']) 
                ? implode(' ', $data['storage_and_handling']) 
                : $data['storage_and_handling'];
        }

        // ✅ Récupérer la catégorie pharmaceutique
        $pharmClass = [];
        if (isset($data['pharm_class'])) {
            $pharmClass = is_array($data['pharm_class']) 
                ? $data['pharm_class'] 
                : [$data['pharm_class']];
        }

        // Déterminer le nom
        $nom = $openfda['brand_name'][0] ?? 
               $data['brand_name'] ?? 
               $openfda['generic_name'][0] ?? 
               $data['generic_name'] ?? 
               'Médicament inconnu';

        return [
            'barcode' => $data['product_ndc'] ?? null,
            'nom' => $nom,
            'manufacturer' => $openfda['manufacturer_name'][0] ?? $data['labeler_name'] ?? null,
            'category' => $data['product_type'] ?? 'Médicament',
            'sub_category' => $openfda['route'][0] ?? $data['route'] ?? null,
            'dosage' => $openfda['dosage_form'][0] ?? $data['dosage_form'] ?? null,
            'image_url' => null,
            'source' => 'OpenFDA',
            'product_type' => 'medicine',
            'product_type_label' => 'Médicament',
            // ✅ Données détaillées
            'ingredients' => $ingredients,
            'indications' => $indications,
            'contre_indications' => $contreIndications,
            'effets_secondaires' => $effetsSecondaires,
            'posologie' => $posologie,
            'conservation' => $conservation,
            'pharm_class' => $pharmClass,
            'details' => [
                'product_ndc' => $data['product_ndc'] ?? null,
                'generic_name' => $data['generic_name'] ?? null,
                'brand_name' => $data['brand_name'] ?? null,
                'manufacturer_name' => $openfda['manufacturer_name'][0] ?? $data['labeler_name'] ?? null,
                'product_type' => $data['product_type'] ?? null,
                'route' => $data['route'] ?? [],
                'marketing_category' => $data['marketing_category'] ?? null,
                'application_number' => $data['application_number'] ?? null,
            ],
        ];
    }

    /**
     * Formater la réponse avec les données de label.json
     */
    public function formatResponseWithLabel(array $data): array
    {
        $openfda = $data['openfda'] ?? [];

        // ✅ Récupérer les ingrédients
        $ingredients = [];
        if (isset($data['active_ingredient'])) {
            $ingredients = is_array($data['active_ingredient']) 
                ? $data['active_ingredient'] 
                : [$data['active_ingredient']];
        }

        // ✅ Récupérer les indications
        $indications = [];
        if (isset($data['indications_and_usage'])) {
            $indications = is_array($data['indications_and_usage']) 
                ? $data['indications_and_usage'] 
                : [$data['indications_and_usage']];
        }

        // ✅ Récupérer les contre-indications
        $contreIndications = [];
        if (isset($data['contraindications'])) {
            $contreIndications = is_array($data['contraindications']) 
                ? $data['contraindications'] 
                : [$data['contraindications']];
        }

        // ✅ Récupérer les effets secondaires
        $effetsSecondaires = [];
        if (isset($data['adverse_reactions'])) {
            $effetsSecondaires = is_array($data['adverse_reactions']) 
                ? $data['adverse_reactions'] 
                : [$data['adverse_reactions']];
        }

        // ✅ Récupérer la posologie
        $posologie = null;
        if (isset($data['dosage_and_administration'])) {
            $posologie = is_array($data['dosage_and_administration']) 
                ? implode(' ', $data['dosage_and_administration']) 
                : $data['dosage_and_administration'];
        }

        return [
            'barcode' => $openfda['product_ndc'][0] ?? null,
            'nom' => $openfda['brand_name'][0] ?? $openfda['generic_name'][0] ?? 'Médicament inconnu',
            'manufacturer' => $openfda['manufacturer_name'][0] ?? null,
            'category' => $data['product_type'] ?? 'Médicament',
            'sub_category' => $openfda['route'][0] ?? null,
            'dosage' => $openfda['dosage_form'][0] ?? null,
            'image_url' => null,
            'source' => 'OpenFDA',
            'product_type' => 'medicine',
            'product_type_label' => 'Médicament',
            'ingredients' => $ingredients,
            'indications' => $indications,
            'contre_indications' => $contreIndications,
            'effets_secondaires' => $effetsSecondaires,
            'posologie' => $posologie,
            'conservation' => $data['storage_and_handling'] ?? null,
            'details' => [
                'product_ndc' => $openfda['product_ndc'][0] ?? null,
                'generic_name' => $openfda['generic_name'][0] ?? null,
                'brand_name' => $openfda['brand_name'][0] ?? null,
                'manufacturer_name' => $openfda['manufacturer_name'][0] ?? null,
                'product_type' => $data['product_type'] ?? null,
            ],
        ];
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->request('', ['limit' => 1]);
            return $response !== null;
        } catch (\Exception $e) {
            Log::error("❌ {$this->name}: Erreur de disponibilité - " . $e->getMessage());
            return false;
        }
    }

    private function cleanBarcode(string $barcode): string
    {
        $barcode = trim($barcode);
        $barcode = preg_replace('/[^a-zA-Z0-9-]/', '', $barcode);
        return $barcode;
    }

    public function searchByBrandName(string $brandName): ?array
    {
        $params = [
            'search' => 'brand_name:"' . $brandName . '"',
            'limit' => 5,
        ];

        $response = $this->request('', $params);
        
        if (!$response || empty($response['results'])) {
            return null;
        }

        return array_map([$this, 'formatResponse'], $response['results']);
    }

    public function searchByGenericName(string $genericName): ?array
    {
        $params = [
            'search' => 'generic_name:"' . $genericName . '"',
            'limit' => 5,
        ];

        $response = $this->request('', $params);
        
        if (!$response || empty($response['results'])) {
            return null;
        }

        return array_map([$this, 'formatResponse'], $response['results']);
    }

    public function searchByManufacturer(string $manufacturer): ?array
    {
        $params = [
            'search' => 'openfda.manufacturer_name:"' . $manufacturer . '"',
            'limit' => 10,
        ];

        $response = $this->request('', $params);
        
        if (!$response || empty($response['results'])) {
            return null;
        }

        return array_map([$this, 'formatResponse'], $response['results']);
    }

    /**
 * Rechercher les détails complets dans label.json
 */
public function getDetailedInfo(string $barcode): ?array
{
    $labelUrl = 'https://api.fda.gov/drug/label.json';
    
    $params = [
        'search' => 'openfda.product_ndc:"' . $barcode . '"',
        'limit' => 1,
    ];

    try {
        $response = Http::timeout(30)->get($labelUrl, $params);
        
        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['results'])) {
                $result = $data['results'][0];
                return [
                    'indications' => $result['indications_and_usage'] ?? [],
                    'contre_indications' => $result['contraindications'] ?? [],
                    'effets_secondaires' => $result['adverse_reactions'] ?? [],
                    'posologie' => $result['dosage_and_administration'] ?? null,
                    'conservation' => $result['storage_and_handling'] ?? null,
                    'warnings' => $result['warnings'] ?? [],
                    'precautions' => $result['precautions'] ?? [],
                ];
            }
        }
        return null;
    } catch (\Exception $e) {
        Log::error("❌ Erreur label.json: " . $e->getMessage());
        return null;
    }
}
}