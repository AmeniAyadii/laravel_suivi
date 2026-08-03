<?php

namespace App\Services;

use App\Models\User;

class ComorbidityAnalyzer
{
    public function analyzeComorbidities(User $user, string $symptoms): array
    {
        $comorbidities = [
            'allergies' => $this->extractAllergies($user),
            'chronic_conditions' => $this->extractChronicConditions($user),
            'medications' => $this->extractCurrentMedications($user),
            'risk_factors' => $this->analyzeRiskFactors($user),
        ];

        // Vérifier les interactions entre symptômes et comorbidités
        $interactions = $this->checkInteractions($symptoms, $comorbidities);

        return [
            'comorbidities' => $comorbidities,
            'interactions' => $interactions,
            'risk_level' => $this->calculateRiskLevel($interactions),
            'recommendations' => $this->generateRecommendations($interactions),
        ];
    }

    private function extractAllergies(User $user): array
    {
        if (!$user->allergies) return [];
        return explode(',', $user->allergies);
    }

    private function extractChronicConditions(User $user): array
    {
        if (!$user->maladies_chroniques) return [];
        return explode(',', $user->maladies_chroniques);
    }

    private function extractCurrentMedications(User $user): array
    {
        return $user->medicaments()
            ->where('statut', 'actif')
            ->pluck('nom')
            ->toArray();
    }

    private function analyzeRiskFactors(User $user): array
    {
        $risks = [];
        
        if ($user->age > 65) {
            $risks[] = 'patient_âgé';
        }
        
        if ($user->poids && $user->taille) {
            $imc = $user->poids / (($user->taille / 100) ** 2);
            if ($imc > 30) {
                $risks[] = 'obésité';
            } elseif ($imc > 25) {
                $risks[] = 'surpoids';
            }
        }
        
        return $risks;
    }

    private function checkInteractions(string $symptoms, array $comorbidities): array
    {
        $interactions = [];
        
        // Vérifier les interactions médicamenteuses
        $symptomKeywords = ['fièvre', 'douleur', 'toux', 'maux'];
        foreach ($symptomKeywords as $keyword) {
            if (stripos($symptoms, $keyword) !== false) {
                foreach ($comorbidities['medications'] as $med) {
                    $interactions[] = "Symptôme '$keyword' avec médicament '$med'";
                }
            }
        }
        
        // Vérifier les allergies
        foreach ($comorbidities['allergies'] as $allergy) {
            if (stripos($symptoms, $allergy) !== false) {
                $interactions[] = "Allergie détectée: $allergy";
            }
        }
        
        return $interactions;
    }

    private function calculateRiskLevel(array $interactions): string
    {
        if (empty($interactions)) return 'low';
        if (count($interactions) <= 2) return 'moderate';
        if (count($interactions) <= 4) return 'high';
        return 'critical';
    }

    private function generateRecommendations(array $interactions): array
    {
        $recommendations = [];
        
        foreach ($interactions as $interaction) {
            if (stripos($interaction, 'allergie') !== false) {
                $recommendations[] = "⚠️ Évitez tout contact avec l'allergène détecté.";
            }
            if (stripos($interaction, 'médicament') !== false) {
                $recommendations[] = "💊 Vérifiez les interactions avec vos médicaments actuels.";
            }
        }
        
        return $recommendations;
    }
}