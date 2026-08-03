<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LLMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    protected LLMService $llmService;

    public function __construct(LLMService $llmService)
    {
        $this->llmService = $llmService;
    }

    /**
     * Endpoint principal de chat
     * POST /api/chat
     */
    public function chat(Request $request)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'history' => 'nullable|array',
                'context' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // 1. Analyser les symptômes avec l'IA
            $analysis = $this->llmService->analyzeSymptoms($request->input('message'));

            if (!$analysis['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'analyse',
                    'error' => $analysis['error'] ?? null
                ], 500);
            }

            $parsed = $analysis['parsed'];
            
            // 2. Formater la réponse pour l'utilisateur
            $userMessage = $this->llmService->formatResponseForUser($parsed);
            $shortMessage = $this->llmService->formatResponseShort($parsed);

            // 3. Construire la réponse complète
            $response = [
                // Message lisible pour l'utilisateur
                'message' => $userMessage,
                'short_message' => $shortMessage,
                
                // Données structurées pour le traitement
                'symptomes' => $parsed['symptomes'] ?? [],
                'questions' => $parsed['questions'] ?? [],
                'gravite' => $parsed['gravite'] ?? 'vert',
                'urgence' => $parsed['urgence'] ?? false,
                'confiance' => $parsed['confiance'] ?? 0,
                'resume' => $parsed['resume'] ?? '',
                'recommandation' => $parsed['recommandation'] ?? '',
                'snapshot' => $parsed['snapshot'] ?? null,
                
                // Métadonnées
                'metadata' => [
                    'timestamp' => now()->toIso8601String(),
                    'model' => $analysis['model'] ?? config('ollama.model'),
                    'tokens' => $analysis['tokens'] ?? 0,
                    'confidence_display' => $this->getConfidenceLevel($parsed['confiance'] ?? 0)
                ],
                
                // Actions rapides suggérées
                'quick_actions' => $this->buildQuickActions($parsed)
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Chat API Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Réponse de fallback
            return response()->json([
                'success' => true,
                'data' => $this->fallbackResponse($request->input('message'))
            ]);
        }
    }

    /**
     * Construire les actions rapides en fonction de l'analyse
     */
    protected function buildQuickActions(array $parsed): array
    {
        $actions = [];
        
        // Action urgence
        if ($parsed['urgence'] === true) {
            $actions[] = [
                'label' => '🚨 Appeler le 15',
                'icon' => 'emergency',
                'type' => 'emergency',
                'priority' => 1
            ];
        }
        
        // Action prendre rendez-vous
        if ($parsed['gravite'] === 'orange' || $parsed['gravite'] === 'rouge') {
            $actions[] = [
                'label' => '📅 Prendre rendez-vous',
                'icon' => 'calendar_month',
                'type' => 'appointment',
                'priority' => 2
            ];
        }
        
        // Action enregistrer les symptômes
        if (!empty($parsed['symptomes'])) {
            $actions[] = [
                'label' => '💾 Enregistrer ces symptômes',
                'icon' => 'save',
                'type' => 'save_symptoms',
                'priority' => 3
            ];
        }
        
        // Action médicament
        if ($parsed['gravite'] === 'vert') {
            $actions[] = [
                'label' => '💊 Gérer mes médicaments',
                'icon' => 'medication',
                'type' => 'medication',
                'priority' => 4
            ];
        }
        
        // Trier par priorité
        usort($actions, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        return $actions;
    }

    /**
     * Niveau de confiance en texte
     */
    private function getConfidenceLevel(int $score): string
    {
        if ($score >= 80) return 'Élevée';
        if ($score >= 50) return 'Moyenne';
        return 'Faible';
    }

    /**
     * Réponse de fallback (si l'IA est indisponible)
     */
    private function fallbackResponse(string $message): array
    {
        $lowerMessage = strtolower($message);
        
        // Détection basique de mots-clés
        $symptoms = [];
        if (str_contains($lowerMessage, 'tête') || str_contains($lowerMessage, 'migraine')) {
            $symptoms[] = 'maux de tête';
        }
        if (str_contains($lowerMessage, 'fièvre')) {
            $symptoms[] = 'fièvre';
        }
        if (str_contains($lowerMessage, 'toux')) {
            $symptoms[] = 'toux';
        }
        if (str_contains($lowerMessage, 'ventre') || str_contains($lowerMessage, 'estomac')) {
            $symptoms[] = 'douleurs abdominales';
        }

        $gravite = count($symptoms) > 2 ? 'orange' : 'vert';
        $isUrgent = str_contains($lowerMessage, 'douleur thoracique') || str_contains($lowerMessage, 'essoufflement');
        
        if ($isUrgent) {
            $gravite = 'rouge';
        }

        $parsed = [
            'symptomes' => $symptoms,
            'questions' => count($symptoms) > 0 ? ['Depuis combien de temps ?'] : ['Pouvez-vous décrire précisément vos symptômes ?'],
            'gravite' => $gravite,
            'urgence' => $isUrgent,
            'confiance' => count($symptoms) > 0 ? 60 : 30,
            'resume' => count($symptoms) > 0 
                ? 'Symptômes détectés : ' . implode(', ', $symptoms)
                : 'Aucun symptôme clairement identifié',
            'recommandation' => $isUrgent 
                ? '⚠️ Consultez immédiatement un médecin ou appelez le 15 !'
                : 'Surveillez vos symptômes. Reposez-vous et hydratez-vous. Consultez si nécessaire.',
            'snapshot' => [
                'date' => now()->toDateTimeString(),
                'symptomes' => $symptoms,
                'niveau' => $gravite,
                'resume' => count($symptoms) > 0 
                    ? 'Symptômes détectés : ' . implode(', ', $symptoms)
                    : 'Aucun symptôme clairement identifié'
            ]
        ];

        $message = $this->llmService->formatResponseForUser($parsed);

        return [
            'message' => $message,
            'short_message' => $this->llmService->formatResponseShort($parsed),
            'symptomes' => $parsed['symptomes'],
            'questions' => $parsed['questions'],
            'gravite' => $parsed['gravite'],
            'urgence' => $parsed['urgence'],
            'confiance' => $parsed['confiance'],
            'resume' => $parsed['resume'],
            'recommandation' => $parsed['recommandation'],
            'snapshot' => $parsed['snapshot'],
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'fallback' => true,
            ],
            'quick_actions' => $this->buildQuickActions($parsed)
        ];
    }
}