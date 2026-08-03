<?php

namespace App\Http\Controllers;

use App\Models\HealthAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HealthAssessmentController extends Controller
{
    /**
     * Récupérer le dernier bilan
     */
    public function latest(Request $request)
    {
        $user = $request->user();
        
        $assessment = HealthAssessment::forUser($user->id)
            ->recent()
            ->first();

        if (!$assessment) {
            // Créer un bilan par défaut
            $assessment = $this->createDefaultAssessment($user->id);
        }

        return response()->json([
            'success' => true,
            'data' => $assessment
        ]);
    }

    /**
     * Récupérer l'historique des bilans
     */
    public function history(Request $request)
    {
        $user = $request->user();
        
        $assessments = HealthAssessment::forUser($user->id)
            ->recent()
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    /**
     * Créer un nouveau bilan
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'weight' => 'nullable|numeric|min:20|max:500',
            'height' => 'nullable|numeric|min:50|max:300',
            'blood_pressure_systolic' => 'nullable|integer|min:80|max:250',
            'blood_pressure_diastolic' => 'nullable|integer|min:40|max:150',
            'heart_rate' => 'nullable|integer|min:30|max:200',
            'temperature' => 'nullable|numeric|min:35|max:42',
            'blood_sugar' => 'nullable|numeric|min:0|max:500',
            'cholesterol' => 'nullable|numeric|min:0|max:500',
            'sleep_hours' => 'nullable|numeric|min:0|max:24',
            'exercise_minutes' => 'nullable|integer|min:0|max:1440',
            'water_intake' => 'nullable|integer|min:0|max:10000',
            'stress_level' => 'nullable|integer|min:0|max:10',
            'mood' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $data = $request->all();
        
        // Calculer l'IMC
        if (!empty($data['weight']) && !empty($data['height'])) {
            $heightInMeters = $data['height'] / 100;
            $data['bmi'] = round($data['weight'] / ($heightInMeters * $heightInMeters), 2);
        }

        // Calculer le score global
        $data['overall_score'] = $this->calculateOverallScore($data);
        
        // Générer des recommandations
        $data['recommendations'] = $this->generateRecommendations($data);

        $data['user_id'] = $user->id;
        $data['assessment_date'] = now();

        $assessment = HealthAssessment::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Bilan enregistré avec succès',
            'data' => $assessment
        ]);
    }

    /**
     * Mettre à jour un bilan
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        $assessment = HealthAssessment::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$assessment) {
            return response()->json([
                'success' => false,
                'error' => 'Bilan non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'weight' => 'nullable|numeric|min:20|max:500',
            'height' => 'nullable|numeric|min:50|max:300',
            'blood_pressure_systolic' => 'nullable|integer|min:80|max:250',
            'blood_pressure_diastolic' => 'nullable|integer|min:40|max:150',
            'heart_rate' => 'nullable|integer|min:30|max:200',
            'temperature' => 'nullable|numeric|min:35|max:42',
            'blood_sugar' => 'nullable|numeric|min:0|max:500',
            'cholesterol' => 'nullable|numeric|min:0|max:500',
            'sleep_hours' => 'nullable|numeric|min:0|max:24',
            'exercise_minutes' => 'nullable|integer|min:0|max:1440',
            'water_intake' => 'nullable|integer|min:0|max:10000',
            'stress_level' => 'nullable|integer|min:0|max:10',
            'mood' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        // Recalculer l'IMC
        if (!empty($data['weight']) && !empty($data['height'])) {
            $heightInMeters = $data['height'] / 100;
            $data['bmi'] = round($data['weight'] / ($heightInMeters * $heightInMeters), 2);
        }

        // Recalculer le score
        $data['overall_score'] = $this->calculateOverallScore($data);
        $data['recommendations'] = $this->generateRecommendations($data);

        $assessment->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Bilan mis à jour',
            'data' => $assessment
        ]);
    }

    /**
     * Calculer le score global
     */
    private function calculateOverallScore($data)
    {
        $score = 0;
        $count = 0;

        // IMC
        if (!empty($data['bmi'])) {
            $bmi = $data['bmi'];
            if ($bmi >= 18.5 && $bmi <= 24.9) $score += 20;
            elseif ($bmi >= 25 && $bmi <= 29.9) $score += 15;
            elseif ($bmi >= 30 && $bmi <= 34.9) $score += 10;
            elseif ($bmi >= 35) $score += 5;
            else $score += 10;
            $count++;
        }

        // Pression artérielle
        if (!empty($data['blood_pressure_systolic']) && !empty($data['blood_pressure_diastolic'])) {
            $sys = $data['blood_pressure_systolic'];
            $dia = $data['blood_pressure_diastolic'];
            if ($sys < 120 && $dia < 80) $score += 20;
            elseif ($sys < 130 && $dia < 80) $score += 15;
            elseif ($sys < 140 && $dia < 90) $score += 10;
            else $score += 5;
            $count++;
        }

        // Fréquence cardiaque
        if (!empty($data['heart_rate'])) {
            $hr = $data['heart_rate'];
            if ($hr >= 60 && $hr <= 100) $score += 15;
            elseif ($hr >= 50 && $hr <= 110) $score += 10;
            else $score += 5;
            $count++;
        }

        // Sommeil
        if (!empty($data['sleep_hours'])) {
            $sleep = $data['sleep_hours'];
            if ($sleep >= 7 && $sleep <= 9) $score += 15;
            elseif ($sleep >= 6 && $sleep <= 10) $score += 10;
            else $score += 5;
            $count++;
        }

        // Exercice
        if (!empty($data['exercise_minutes'])) {
            $exercise = $data['exercise_minutes'];
            if ($exercise >= 30) $score += 15;
            elseif ($exercise >= 15) $score += 10;
            else $score += 5;
            $count++;
        }

        // Stress
        if (!empty($data['stress_level'])) {
            $stress = $data['stress_level'];
            if ($stress <= 3) $score += 15;
            elseif ($stress <= 6) $score += 10;
            else $score += 5;
            $count++;
        }

        return $count > 0 ? round($score / $count * 10) : 50;
    }

    /**
     * Générer des recommandations personnalisées
     */
    private function generateRecommendations($data)
    {
        $recommendations = [];

        // IMC
        if (!empty($data['bmi'])) {
            $bmi = $data['bmi'];
            if ($bmi < 18.5) {
                $recommendations[] = 'Votre IMC indique une insuffisance pondérale. Consultez un nutritionniste.';
            } elseif ($bmi >= 25 && $bmi < 30) {
                $recommendations[] = 'Votre IMC est en surpoids. Une activité physique régulière est recommandée.';
            } elseif ($bmi >= 30) {
                $recommendations[] = '⚠️ Votre IMC indique une obésité. Consultez un médecin rapidement.';
            } else {
                $recommendations[] = '✅ Votre IMC est dans la normale. Continuez !';
            }
        }

        // Pression artérielle
        if (!empty($data['blood_pressure_systolic']) && !empty($data['blood_pressure_diastolic'])) {
            $sys = $data['blood_pressure_systolic'];
            $dia = $data['blood_pressure_diastolic'];
            if ($sys >= 140 || $dia >= 90) {
                $recommendations[] = '⚠️ Votre pression artérielle est élevée. Consultez un médecin.';
            } elseif ($sys >= 130 || $dia >= 80) {
                $recommendations[] = 'Votre pression artérielle est élevée. Surveillez votre alimentation.';
            } else {
                $recommendations[] = '✅ Votre pression artérielle est normale.';
            }
        }

        // Sommeil
        if (!empty($data['sleep_hours'])) {
            $sleep = $data['sleep_hours'];
            if ($sleep < 7) {
                $recommendations[] = 'Vous ne dormez pas assez. Essayez de dormir 7-8h par nuit.';
            } elseif ($sleep > 9) {
                $recommendations[] = 'Vous dormez beaucoup. Vérifiez votre qualité de sommeil.';
            } else {
                $recommendations[] = '✅ Votre sommeil est optimal.';
            }
        }

        // Exercice
        if (!empty($data['exercise_minutes'])) {
            $exercise = $data['exercise_minutes'];
            if ($exercise < 15) {
                $recommendations[] = '🚶‍♂️ Vous devriez faire plus d\'exercice. 30 min par jour sont recommandées.';
            } elseif ($exercise < 30) {
                $recommendations[] = '🏃 Essayez d\'augmenter votre activité physique à 30 min par jour.';
            } else {
                $recommendations[] = '✅ Vous faites assez d\'exercice. Continuez !';
            }
        }

        // Stress
        if (!empty($data['stress_level'])) {
            $stress = $data['stress_level'];
            if ($stress > 7) {
                $recommendations[] = '🧘 Votre niveau de stress est élevé. Essayez la méditation ou le yoga.';
            } elseif ($stress > 4) {
                $recommendations[] = '😌 Votre stress est modéré. Prenez des pauses régulières.';
            } else {
                $recommendations[] = '✅ Votre niveau de stress est bien géré.';
            }
        }

        return $recommendations;
    }

    /**
     * Créer un bilan par défaut
     */
    private function createDefaultAssessment($userId)
    {
        $user = \App\Models\User::find($userId);
        
        return HealthAssessment::create([
            'user_id' => $userId,
            'assessment_date' => now(),
            'weight' => $user->poids ?? null,
            'height' => $user->taille ?? null,
            'bmi' => $this->calculateBMI($user->poids ?? null, $user->taille ?? null),
            'overall_score' => 70,
            'status' => 'completed'
        ]);
    }

    private function calculateBMI($weight, $height)
    {
        if (!$weight || !$height) return null;
        $heightInMeters = $height / 100;
        return round($weight / ($heightInMeters * $heightInMeters), 2);
    }
}