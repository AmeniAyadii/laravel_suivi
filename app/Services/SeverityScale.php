<?php

namespace App\Services;

class SeverityScale
{
    const LEVELS = [
        'green' => [
            'color' => '#4CAF50',
            'label' => 'Autosoins',
            'action' => 'self_care',
            'advice' => 'Repos, hydratation, exercices de respiration',
            'suggest_doctor' => false,
            'emergency' => false,
        ],
        'orange' => [
            'color' => '#FF9800',
            'label' => 'Consultation Programmable',
            'action' => 'schedule_visit',
            'advice' => 'Prenez rendez-vous dans les 48h',
            'suggest_doctor' => true,
            'emergency' => false,
        ],
        'red' => [
            'color' => '#F44336',
            'label' => 'Urgence',
            'action' => 'emergency',
            'advice' => 'Appelez immédiatement le 15',
            'suggest_doctor' => true,
            'emergency' => true,
        ],
    ];

    public function determineSeverity(array $symptoms, array $context): array
    {
        $score = $this->calculateSeverityScore($symptoms, $context);
        
        if ($score >= 70) {
            return $this->getSeverityLevel('red');
        } elseif ($score >= 40) {
            return $this->getSeverityLevel('orange');
        } else {
            return $this->getSeverityLevel('green');
        }
    }

    private function calculateSeverityScore(array $symptoms, array $context): int
    {
        $score = 0;
        
        // Facteurs de sévérité
        $factors = [
            'duration' => $context['duration_hours'] ?? 0,
            'intensity' => $symptoms['intensity'] ?? 0,
            'comorbidities' => $context['comorbidity_count'] ?? 0,
            'age' => $context['age'] ?? 0,
            'emergency_keywords' => $this->countEmergencyKeywords($symptoms['text'] ?? ''),
        ];
        
        // Duration
        if ($factors['duration'] > 72) $score += 25;
        elseif ($factors['duration'] > 24) $score += 20;
        elseif ($factors['duration'] > 6) $score += 15;
        elseif ($factors['duration'] > 1) $score += 10;
        
        // Intensity
        $score += $factors['intensity'] * 2;
        
        // Comorbidities
        if ($factors['comorbidities'] > 0) $score += $factors['comorbidities'] * 5;
        
        // Age
        if ($factors['age'] > 65) $score += 10;
        elseif ($factors['age'] < 5) $score += 10;
        
        // Emergency keywords
        $score += min($factors['emergency_keywords'] * 10, 30);
        
        return min($score, 100);
    }

    private function countEmergencyKeywords(string $text): int
    {
        $keywords = [
            'urgence', 'immédiat', '15', 'samu', 'douleur intense',
            'mal à la poitrine', 'difficulté respiratoire', 'perte connaissance',
            'saignement abondant', 'paralysie', 'crise cardiaque'
        ];
        
        $count = 0;
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        
        return $count;
    }

    private function getSeverityLevel(string $level): array
    {
        $levelData = self::LEVELS[$level] ?? self::LEVELS['green'];
        
        return array_merge(
            $levelData,
            [
                'level' => $level,
                'score' => $score ?? 0,
            ]
        );
    }
}
