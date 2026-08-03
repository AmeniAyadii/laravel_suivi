<?php

namespace App\Services;

use Carbon\Carbon;

class SymptomAnalyzer
{
    /**
     * Détecter la temporalité dans le message
     */
    public function detectTimeContext(string $message): array
    {
        $patterns = [
            'depuis' => '/depuis\s+([\d]+)\s*(heure|h|jour|j|minute|min|mois|semaine|s|an|année)/i',
            'il_y_a' => '/il\s*y\s*a\s+([\d]+)\s*(heure|h|jour|j|minute|min|mois|semaine|s|an|année)/i',
            'pendant' => '/pendant\s+([\d]+)\s*(heure|h|jour|j|minute|min|mois|semaine|s|an|année)/i',
            'aujourd\'hui' => '/aujourd\'hui/i',
            'ce_matin' => '/ce\s*matin/i',
            'cette_nuit' => '/cette\s*nuit/i',
            'hier' => '/hier/i',
            'semaine_derniere' => '/semaine\s*dernière|la\s*semaine\s*passée/i',
        ];

        $result = [
            'has_time' => false,
            'duration' => null,
            'unit' => null,
            'timestamp' => null,
            'human_readable' => null,
            'severity_multiplier' => 1,
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $result['has_time'] = true;
                
                if (in_array($key, ['depuis', 'il_y_a', 'pendant'])) {
                    $duration = (int) $matches[1];
                    $unit = $matches[2];
                    $result['duration'] = $duration;
                    $result['unit'] = $unit;
                    
                    // Calculer la sévérité en fonction de la durée
                    $result['severity_multiplier'] = $this->calculateSeverityMultiplier($duration, $unit);
                }
                
                if (in_array($key, ['aujourd\'hui', 'ce_matin', 'cette_nuit', 'hier', 'semaine_derniere'])) {
                    $result['timestamp'] = $this->parseRelativeTime($key);
                    $result['human_readable'] = $this->getHumanReadableTime($key);
                }
                
                break;
            }
        }

        return $result;
    }

    /**
     * Calculer le multiplicateur de sévérité basé sur la durée
     */
    private function calculateSeverityMultiplier(int $duration, string $unit): float
    {
        $hours = $this->convertToHours($duration, $unit);
        
        if ($hours <= 1) return 1.0;      // Faible
        if ($hours <= 6) return 1.5;      // Modéré
        if ($hours <= 24) return 2.0;     // Élevé
        if ($hours <= 72) return 3.0;     // Très élevé
        return 4.0;                        // Critique
    }

    private function convertToHours(int $duration, string $unit): int
    {
        $unit = strtolower($unit);
        $map = [
            'minute' => 1/60,
            'min' => 1/60,
            'heure' => 1,
            'h' => 1,
            'jour' => 24,
            'j' => 24,
            'semaine' => 168,
            's' => 168,
            'mois' => 720,
            'an' => 8760,
            'année' => 8760,
        ];
        
        return (int) ($duration * ($map[$unit] ?? 1));
    }

    private function parseRelativeTime(string $key): string
    {
        $now = Carbon::now();
        
        switch ($key) {
            case 'aujourd\'hui':
                return $now->toDateString();
            case 'ce_matin':
                return $now->startOfDay()->toDateTimeString();
            case 'cette_nuit':
                return $now->subDay()->startOfDay()->toDateTimeString();
            case 'hier':
                return $now->subDay()->toDateString();
            case 'semaine_derniere':
                return $now->subWeek()->startOfWeek()->toDateString();
            default:
                return $now->toDateTimeString();
        }
    }

    private function getHumanReadableTime(string $key): string
    {
        $map = [
            'aujourd\'hui' => "aujourd'hui",
            'ce_matin' => "ce matin",
            'cette_nuit' => "cette nuit",
            'hier' => "hier",
            'semaine_derniere' => "la semaine dernière",
        ];
        
        return $map[$key] ?? $key;
    }
}