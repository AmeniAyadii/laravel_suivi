<?php
// config/scan.php

return [
    'providers' => [
        'openfda' => [
            'enabled' => true,
            'priority' => 1,
            'base_url' => env('OPENFDA_BASE_URL', 'https://api.fda.gov/drug/ndc.json'),
            'timeout' => 30, // Augmenté de 10 à 30 secondes
        ],
        
        'openproducts' => [
            'enabled' => true,
            'priority' => 2,
            'base_url' => env('OPENPRODUCTS_BASE_URL', 'https://world.openproductsfacts.org/api/v0'),
            'timeout' => 20, // Augmenté de 10 à 20 secondes
        ],
        
        'universal_scan' => [
            'enabled' => true,
            'priority' => 3,
            'base_url' => env('UNIVERSAL_SCAN_URL', 'https://world.openfoodfacts.org/api/v3/product'),
            'timeout' => 25, // Augmenté de 15 à 25 secondes
        ],
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
        'prefix' => 'scan_',
    ],
    
    'fallback' => [
        'nom' => 'Produit non trouvé',
        'manufacturer' => 'Inconnu',
        'category' => 'Non spécifié',
        'message' => 'Aucune information disponible pour ce code-barres',
        'suggestions' => [
            'Vérifiez que le code-barres est correct',
            'Essayez de scanner à nouveau',
            'Le produit peut ne pas être dans notre base de données',
        ],
    ],
];