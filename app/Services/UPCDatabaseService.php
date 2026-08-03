<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPCDatabaseService
{
    private $apiKey;
    private $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.upcdatabase.api_key');
        $this->apiUrl = config('services.upcdatabase.api_url');
    }

    /**
     * Rechercher un produit par code-barres
     */
    public function searchProduct($barcode)
    {
        if (empty($this->apiKey)) {
            Log::warning('UPCDatabase API key manquante');
            return null;
        }

        try {
            $url = $this->apiUrl . $barcode . '?apikey=' . $this->apiKey;
            Log::info('📡 Recherche UPCDatabase: ' . $url);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('📥 Réponse UPCDatabase: ' . json_encode($data));

                // Vérifier si le produit existe
                if (!$this->hasError($data)) {
                    return $this->formatProduct($data, $barcode);
                } else {
                    Log::info('❌ Produit non trouvé dans UPCDatabase');
                }
            } else {
                Log::warning('⚠️ Erreur HTTP UPCDatabase: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('❌ Erreur UPCDatabase: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Vérifier si la réponse contient une erreur
     */
    private function hasError($data)
    {
        return isset($data['error']) || isset($data['success']) && $data['success'] === false;
    }

    /**
     * Formater les données du produit
     */
    private function formatProduct($data, $barcode)
    {
        // Nettoyer les champs
        $name = $this->cleanText($data['name'] ?? null);
        $manufacturer = $this->cleanText($data['manufacturer'] ?? null);
        $category = $this->cleanText($data['category'] ?? null);
        $size = $this->cleanText($data['size'] ?? null);
        
        // Extraire l'image
        $imageUrl = null;
        if (isset($data['images']) && is_array($data['images']) && count($data['images']) > 0) {
            $imageUrl = $data['images'][0]['url'] ?? null;
        }

        return [
            'barcode' => $barcode,
            'nom' => $name ?: 'Produit inconnu',
            'manufacturer' => $manufacturer ?: null,
            'category' => $category ?: null,
            'dosage' => $size ?: null,
            'image_url' => $imageUrl,
            'source' => 'UPCDatabase'
        ];
    }

    /**
     * Nettoyer le texte
     */
    private function cleanText($text)
    {
        if (!$text) return null;
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }

    /**
     * Vérifier si l'API est configurée
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }
}