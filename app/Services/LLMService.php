<?php

namespace App\Services;

use Cloudstudio\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Log;

class LLMService
{
    /**
     * Envoyer un message à l'IA et obtenir une réponse
     */
    public function chat(string $message): array
    {
        try {
            // Récupérer le prompt système par défaut
            $defaultPrompt = config('ollama.default_prompt', 'Vous êtes un assistant médical préventif. Vous analysez les symptômes, proposez des causes possibles avec un niveau de gravité. Vous rappelez toujours que vous ne remplacez pas un avis médical. Répondez en français.');
            #$defaultPrompt = require app_path('AI/prompts/health_system_prompt.php');
            $response = Ollama::agent($defaultPrompt)
                ->prompt($message)
                ->model(config('ollama.model', 'llama3.2:3b'))
                ->options([
                    'temperature' => 0.3,
                    'num_predict' => 500,
                ])
                ->ask();

            return [
                'content' => $response['response'] ?? 'Je n\'ai pas pu générer de réponse.',
                'success' => true,
                'tokens' => $response['eval_count'] ?? 0,
            ];

        } catch (\Exception $e) {
            Log::error('Ollama Error: ' . $e->getMessage());
            return [
                'content' => 'Désolé, une erreur est survenue avec l\'assistant IA. Veuillez réessayer.',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyser des symptômes médicaux
     */
    // app/Services/LLMService.php - Méthode analyzeSymptoms améliorée

public function analyzeSymptoms(string $symptoms): array
{
    $prompt = "Analyse ces symptômes : " . $symptoms . ". 
    
    Retourne UNIQUEMENT un objet JSON valide avec cette structure exacte :
    {
        \"severity\": \"low|moderate|high|emergency\",
        \"possible_causes\": [\"cause1\", \"cause2\", \"cause3\"],
        \"advice\": \"Conseils pratiques détaillés\",
        \"suggest_doctor\": true|false,
        \"suggest_medication\": true|false,
        \"emergency_alert\": true|false,
        \"message\": \"Message complet pour l'utilisateur\"
    }
    
    Ne retourne AUCUN texte en dehors du JSON.";

    $response = $this->chat($prompt);
    
    if ($response['success']) {
        // Extraire le JSON
        preg_match('/\{.*\}/s', $response['content'], $matches);
        if (!empty($matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return array_merge($response, ['parsed' => $parsed]);
            }
        }
    }
    
    return $response;
}

    /**
     * Extraire des informations d'un message (médicaments, rendez-vous, etc.)
     */
    public function extractInfo(string $message, string $type): array
    {
        $prompt = "Extrais les informations suivantes du message de l'utilisateur : " . $message . "
        
        Type d'extraction : " . $type . "
        
        Retourne les données au format JSON valide, sans texte supplémentaire.";

        $response = $this->chat($prompt);
        
        // Essayer de parser le JSON
        if ($response['success']) {
            $json = json_decode($response['content'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        return ['error' => 'Impossible d\'extraire les informations'];
    }

    /**
     * Vérifier l'état du serveur Ollama
     */
    public function checkHealth(): bool
    {
        try {
            $response = Ollama::agent('Test')
                ->prompt('Bonjour')
                ->model(config('ollama.model', 'llama3.2:3b'))
                ->options(['temperature' => 0.1, 'num_predict' => 5])
                ->ask();
            
            return isset($response['response']);
        } catch (\Exception $e) {
            return false;
        }
    }

    // app/Services/LLMService.php - Ajouter cette méthode

/**
 * Détecter les intentions liées aux médicaments
 */
public function detectMedicationIntent(string $message): array
{
    $lower = strtolower($message);
    
    // Mots-clés pour chaque action
    $keywords = [
        'ajouter' => ['ajouter', 'ajoute', 'nouveau', 'prendre', 'commencer'],
        'liste' => ['liste', 'mes', 'médicaments', 'traitements', 'quoi'],
        'prise' => ['pris', 'prise', 'prendre', 'doliprane', 'ibuprofène'],
        'observance' => ['observance', 'suivi', 'checklist', 'vérifier'],
        'interaction' => ['interaction', 'interagit', 'danger', 'attention'],
        'renouvellement' => ['renouveler', 'renouvellement', 'ordonnance', 'stock'],
    ];

    foreach ($keywords as $action => $words) {
        foreach ($words as $word) {
            if (str_contains($lower, $word)) {
                return [
                    'detected' => true,
                    'action' => $action,
                    'confidence' => 0.8,
                ];
            }
        }
    }

    return ['detected' => false];
}
}