<?php

namespace App\Services;

use App\Models\Symptome;
use Carbon\Carbon;

class HealthSnapshot
{
    public function createSnapshot(int $userId, array $symptoms, array $analysis): array
    {
        $snapshot = [
            'id' => uniqid('snapshot_'),
            'date' => Carbon::now()->toISO8601String(),
            'patient_id' => $userId,
            'symptoms' => $symptoms,
            'analysis' => $analysis,
            'summary' => $this->generateSummary($symptoms, $analysis),
            'severity' => $analysis['severity'],
            'timestamp' => now(),
        ];

        // Sauvegarder en base de données
        $this->saveSnapshot($userId, $snapshot);

        return $snapshot;
    }

    private function generateSummary(array $symptoms, array $analysis): string
    {
        $summary = "📋 **Résumé de l'épisode**\n\n";
        $summary .= "📅 Date: " . now()->format('d/m/Y H:i') . "\n";
        $summary .= "🩺 Symptômes: " . implode(', ', $symptoms) . "\n";
        $summary .= "📊 Gravité: " . $analysis['severity_label'] . "\n";
        
        if ($analysis['severity'] === 'red') {
            $summary .= "🚨 **URGENCE MÉDICALE**\n";
            $summary .= "📞 Contactez le 15 immédiatement\n";
        }
        
        if ($analysis['suggest_doctor'] ?? false) {
            $summary .= "👨‍⚕️ Consultation recommandée\n";
        }
        
        $summary .= "\n💡 Recommandations:\n" . ($analysis['advice'] ?? '');
        
        return $summary;
    }

    private function saveSnapshot(int $userId, array $snapshot): void
    {
        // Sauvegarder dans la table symptomes
        Symptome::create([
            'user_id' => $userId,
            'description' => $snapshot['summary'],
            'niveau' => $this->severityToLevel($snapshot['severity']),
            'date_enregistrement' => now(),
            'notes' => json_encode($snapshot),
        ]);
    }

    private function severityToLevel(string $severity): int
    {
        $map = [
            'green' => 3,
            'orange' => 6,
            'red' => 9,
        ];
        
        return $map[$severity] ?? 3;
    }
}
