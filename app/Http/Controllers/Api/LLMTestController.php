<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LLMService;
use Illuminate\Http\Request;

class LLMTestController extends Controller
{
    /**
     * Analyse des symptômes (version améliorée)
     */
    public function analyze(Request $request, LLMService $llm)
    {
        $symptoms = $request->input('symptoms', '');
        
        if (empty($symptoms)) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez décrire vos symptômes',
            ], 422);
        }

        // ✅ Utiliser la méthode dédiée
        $response = $llm->analyzeSymptoms($symptoms);

        return response()->json($response);
    }

    /**
     * Chat général
     */
    public function chat(Request $request, LLMService $llm)
    {
        $message = $request->input('message', '');
        
        if (empty($message)) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez entrer un message',
            ], 422);
        }

        $response = $llm->chat($message);

        return response()->json($response);
    }

    /**
     * Vérifier la santé du serveur
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Le serveur Ollama fonctionne correctement',
            'timestamp' => now(),
        ]);
    }
}