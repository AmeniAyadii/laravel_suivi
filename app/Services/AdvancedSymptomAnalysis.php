<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdvancedSymptomAnalysis
{
    private SymptomAnalyzer $timeAnalyzer;
    private ComorbidityAnalyzer $comorbidityAnalyzer;
    private SeverityScale $severityScale;
    private HealthSnapshot $snapshot;

    public function __construct()
    {
        $this->timeAnalyzer = new SymptomAnalyzer();
        $this->comorbidityAnalyzer = new ComorbidityAnalyzer();
        $this->severityScale = new SeverityScale();
        $this->snapshot = new HealthSnapshot();
    }

    public function analyze(string $message): array
    {
        $user = Auth::user();
        
        // 1. Détection du contexte temporel
        $timeContext = $this->timeAnalyzer->detectTimeContext($message);
        
        // 2. Analyse des comorbidités
        $comorbidityAnalysis = $user ? 
            $this->comorbidityAnalyzer->analyzeComorbidities($user, $message) : 
            null;
        
        // 3. Détermination de la gravité
        $severity = $this->severityScale->determineSeverity(
            ['text' => $message, 'intensity' => $this->extractIntensity($message)],
            [
                'duration_hours' => $timeContext['duration'] ?? 0,
                'comorbidity_count' => count($comorbidityAnalysis['comorbidities'] ?? []),
                'age' => $user->age ?? null,
            ]
        );
        
        // 4. Générer une réponse adaptée
        $response = $this->generateResponse($message, $timeContext, $comorbidityAnalysis, $severity);
        
        // 5. Créer un snapshot santé (si utilisateur connecté)
        if ($user) {
            $snapshot = $this->snapshot->createSnapshot(
                $user->id,
                [$message],
                $response
            );
            $response['snapshot'] = $snapshot;
        }
        
        return $response;
    }

    private function extractIntensity(string $message): int
    {
        $intensityWords = [
            'légère' => 1,
            'modérée' => 3,
            'forte' => 6,
            'intense' => 8,
            'insoutenable' => 10,
            'léger' => 2,
            'modéré' => 4,
            'fort' => 7,
        ];
        
        foreach ($intensityWords as $word => $intensity) {
            if (stripos($message, $word) !== false) {
                return $intensity;
            }
        }
        
        return 3; // Par défaut
    }

    private function generateResponse($message, $timeContext, $comorbidityAnalysis, $severity): array
    {
        $response = [
            'success' => true,
            'type' => 'symptom_analysis',
        ];
        
        // Ajouter le message de base
        $response['message'] = $this->buildBaseMessage($severity);
        
        // Ajouter le contexte temporel
        if ($timeContext['has_time']) {
            $response['time_context'] = $timeContext;
            $response['message'] .= "\n\n⏰ Contexte temporel: " . $timeContext['human_readable'] ?? 'Détecté';
            if ($timeContext['duration']) {
                $response['message'] .= "\nDurée: {$timeContext['duration']} {$timeContext['unit']}";
            }
        }
        
        // Ajouter les comorbidités
        if ($comorbidityAnalysis && !empty($comorbidityAnalysis['comorbidities'])) {
            $response['message'] .= "\n\n📋 Contexte médical pris en compte:";
            if (!empty($comorbidityAnalysis['comorbidities']['allergies'])) {
                $response['message'] .= "\n- Allergies: " . implode(', ', $comorbidityAnalysis['comorbidities']['allergies']);
            }
            if (!empty($comorbidityAnalysis['comorbidities']['chronic_conditions'])) {
                $response['message'] .= "\n- Maladies chroniques: " . implode(', ', $comorbidityAnalysis['comorbidities']['chronic_conditions']);
            }
        }
        
        // Ajouter les recommandations de sévérité
        $response['message'] .= "\n\n" . $this->getSeverityAdvice($severity);
        
        // Ajouter les actions
        $response['actions'] = $this->getActions($severity);
        
        return array_merge($response, $severity);
    }

    private function buildBaseMessage(array $severity): string
    {
        $messages = [
            'green' => "🟢 **Analyse des symptômes - Niveau Autosoins**\n\nVos symptômes semblent être de faible gravité.",
            'orange' => "🟡 **Analyse des symptômes - Consultation recommandée**\n\nVos symptômes nécessitent une attention médicale.",
            'red' => "🔴 **URGENCE MÉDICALE**\n\nVos symptômes sont critiques. Veuillez agir immédiatement.",
        ];
        
        return $messages[$severity['level']] ?? $messages['green'];
    }

    private function getSeverityAdvice(array $severity): string
    {
        $advice = [
            'green' => "💡 **Conseils:**\n- Reposez-vous et hydratez-vous\n- Surveillez l'évolution des symptômes\n- Pratiquez des exercices de respiration",
            'orange' => "💡 **Recommandations:**\n- 📅 Prenez rendez-vous avec votre médecin dans les 48h\n- 📝 Notez vos symptômes\n- 💊 Suivez les traitements prescrits",
            'red' => "🚨 **URGENCE:**\n- 📞 Appelez le 15 (SAMU) immédiatement\n- 📍 Activez votre position GPS\n- 🆘 Alertez vos contacts d'urgence",
        ];
        
        return $advice[$severity['level']] ?? $advice['green'];
    }

    private function getActions(array $severity): array
    {
        $actions = [
            'green' => ['Self care', 'Suivre les symptômes', 'Se reposer'],
            'orange' => ['Prendre RDV', 'Noter les symptômes', 'Poser une question'],
            'red' => ['Appeler le 15', 'Partager la position', 'Contacter un proche'],
        ];
        
        return $actions[$severity['level']] ?? $actions['green'];
    }
}