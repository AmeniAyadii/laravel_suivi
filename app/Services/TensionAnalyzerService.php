<?php

namespace App\Services;

use App\Models\TensionMeasure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TensionAnalyzerService
{
    protected $aiApiUrl;

    public function __construct()
    {
        $this->aiApiUrl = env('AI_API_URL', 'http://localhost:8000');
    }

    /**
     * Analyser une mesure individuelle
     */
    public function analyzeSingleMeasure(TensionMeasure $measure)
    {
        try {
            $response = Http::timeout(5)->post("{$this->aiApiUrl}/analyze/single", [
                'systolic' => $measure->systolic,
                'diastolic' => $measure->diastolic,
                'heart_rate' => $measure->heart_rate,
                'measure_date' => $measure->measure_date->format('Y-m-d H:i:s'),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            // Fallback si l'API IA est hors ligne
            return $this->getFallbackSingleAnalysis($measure);

        } catch (\Exception $e) {
            Log::error("Erreur IA (single): " . $e->getMessage());
            return $this->getFallbackSingleAnalysis($measure);
        }
    }

    /**
     * Analyser les tendances
     */
    public function analyzeTrends($measures)
    {
        try {
            $data = $measures->map(function ($measure) {
                return [
                    'date' => $measure->measure_date->format('Y-m-d'),
                    'systolic' => $measure->systolic,
                    'diastolic' => $measure->diastolic,
                    'heart_rate' => $measure->heart_rate,
                ];
            });

            $response = Http::timeout(10)->post("{$this->aiApiUrl}/analyze/trends", [
                'measures' => $data,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return $this->getFallbackTrendAnalysis($measures);

        } catch (\Exception $e) {
            Log::error("Erreur IA (trends): " . $e->getMessage());
            return $this->getFallbackTrendAnalysis($measures);
        }
    }

    /**
     * Analyse de secours (hors ligne)
     */
    private function getFallbackSingleAnalysis($measure)
    {
        $systolic = $measure->systolic;
        $diastolic = $measure->diastolic;

        if ($systolic < 90 || $diastolic < 60) {
            return [
                'summary' => "⚠️ Tension basse détectée ({$systolic}/{$diastolic})",
                'recommendation' => "Buvez de l'eau, mangez quelque chose de salé, consultez si vous avez des vertiges.",
                'severity_level' => 'warning',
                'details' => ['category' => 'low']
            ];
        } elseif ($systolic > 180 || $diastolic > 120) {
            return [
                'summary' => "🚨 CRISE HYPERTENSIVE ! ({$systolic}/{$diastolic})",
                'recommendation' => "⚠️ URGENCE MÉDICALE ! Consultez immédiatement un médecin ou appelez le SAMU.",
                'severity_level' => 'danger',
                'details' => ['category' => 'crisis']
            ];
        } elseif ($systolic > 140 || $diastolic > 90) {
            return [
                'summary' => "⚠️ Hypertension détectée ({$systolic}/{$diastolic})",
                'recommendation' => "Surveillez régulièrement votre tension. Réduisez le sel et le stress. Consultez si cela persiste.",
                'severity_level' => 'warning',
                'details' => ['category' => 'high']
            ];
        } else {
            return [
                'summary' => "✅ Tension normale ({$systolic}/{$diastolic})",
                'recommendation' => "Continuez à surveiller votre tension régulièrement. Bonne santé cardiovasculaire !",
                'severity_level' => 'normal',
                'details' => ['category' => 'normal']
            ];
        }
    }

    private function getFallbackTrendAnalysis($measures)
    {
        $avgSystolic = $measures->avg('systolic');
        $avgDiastolic = $measures->avg('diastolic');
        $count = $measures->count();

        $summary = "📊 Analyse de vos {$count} dernières mesures\n";
        $summary .= "Moyenne: " . round($avgSystolic) . "/" . round($avgDiastolic) . "\n";

        $recommendation = "Continuez à suivre votre tension quotidiennement.";
        $severity = 'normal';
        $details = [];

        // Détection de tendance
        $first = $measures->first();
        $last = $measures->last();
        
        if ($last && $first) {
            $trend = $last->systolic - $first->systolic;
            if ($trend > 10) {
                $summary .= "📈 Tendance à la hausse de la systolique (+" . round($trend) . " mmHg)";
                $recommendation = "⚠️ Votre tension augmente. Consultez votre médecin.";
                $severity = 'warning';
            } elseif ($trend < -10) {
                $summary .= "📉 Tendance à la baisse de la systolique (" . round($trend) . " mmHg)";
                $recommendation = "Votre tension diminue, c'est bon signe. Continuez le suivi.";
            } else {
                $summary .= "➡️ Tension stable dans le temps.";
            }
        }

        // Vérifier les valeurs extrêmes
        if ($avgSystolic > 160 || $avgDiastolic > 100) {
            $severity = 'danger';
            $recommendation = "🚨 URGENCE : Consultez un médecin rapidement.";
        } elseif ($avgSystolic > 140 || $avgDiastolic > 90) {
            $severity = 'warning';
            $recommendation = "Consultez votre médecin pour un bilan cardiovasculaire.";
        }

        $details = [
            'avg_systolic' => round($avgSystolic, 1),
            'avg_diastolic' => round($avgDiastolic, 1),
            'measure_count' => $count,
        ];

        return [
            'summary' => $summary,
            'recommendation' => $recommendation,
            'severity_level' => $severity,
            'details' => $details,
        ];
    }
}