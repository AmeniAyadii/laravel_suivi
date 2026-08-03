<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MedicamentController;
use App\Http\Controllers\RendezVousController;
use App\Http\Controllers\SymptomeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\HealthAssessmentController;
use App\Http\Controllers\ScanController;

use App\Http\Controllers\Api\MedicationTakingController;
use App\Http\Controllers\Api\EmergencyAlertController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ChatbotConversationController;
use App\Http\Controllers\Api\UserHealthMetricController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MedicationController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\VoiceSchedulerController;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\StatistiqueController;

use App\Http\Controllers\Api\FCMController;

use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\AchievementController;

use App\Http\Controllers\Api\ConstantesVitalesController;
use App\Http\Controllers\Api\AlerteController;

use App\Http\Controllers\Api\TensionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Route de test
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API Laravel fonctionne correctement',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// ==================== ROUTES PUBLIQUES ====================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ==================== ROUTES PROTÉGÉES ====================

Route::middleware('auth:sanctum')->group(function () {

    // Utilisateur
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Médicaments
    Route::get('/medicaments', [MedicamentController::class, 'index']);
    Route::get('/medicaments/next', [MedicamentController::class, 'next']);
    Route::post('/medicaments', [MedicamentController::class, 'store']);
    Route::put('/medicaments/{id}', [MedicamentController::class, 'update']);
    Route::delete('/medicaments/{id}', [MedicamentController::class, 'destroy']);
    Route::post('/medicaments/{id}/taken', [MedicamentController::class, 'markAsTaken']); // ✅ Nouvelle route
    Route::get('/medicaments/check-reminders', [MedicamentController::class, 'checkReminders']); // ✅ Nouvelle route

    // Rendez-vous
    Route::get('/rendezvous', [RendezVousController::class, 'index']);
    Route::get('/rendezvous/next', [RendezVousController::class, 'next']);
    Route::post('/rendezvous', [RendezVousController::class, 'store']);
    Route::put('/rendezvous/{id}', [RendezVousController::class, 'update']);
    Route::delete('/rendezvous/{id}', [RendezVousController::class, 'destroy']);
    Route::post('/rendezvous/{id}/confirm', [RendezVousController::class, 'confirm']); // ✅ Nouvelle route
    Route::post('/rendezvous/{id}/cancel', [RendezVousController::class, 'cancel']);
    Route::get('/rendezvous/check-reminders', [RendezVousController::class, 'checkReminders']); // ✅ Nouvelle route

    // Symptômes
    Route::get('/symptomes', [SymptomeController::class, 'index']);
    Route::get('/symptomes/last', [SymptomeController::class, 'last']);
    Route::post('/symptomes', [SymptomeController::class, 'store']);
    Route::delete('/symptomes/{id}', [SymptomeController::class, 'destroy']);
    Route::put('/symptomes/{id}', [SymptomeController::class, 'update']);  // ✅ AJOUTER CETTE LIGNE
    Route::patch('/symptomes/{id}', [SymptomeController::class, 'update']); // ✅ AJOUTER CETTE LIGNE
    
    Route::delete('/symptomes/{id}', [SymptomeController::class, 'destroy']);
    Route::post('/symptomes/analyze', [SymptomeController::class, 'analyze']);
    Route::get('/symptomes/stats', [SymptomeController::class, 'stats']);


    // ========== PARAMÈTRES ==========
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Rapports
    Route::post('/reports/generate', [ReportController::class, 'generate']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);
    Route::delete('/reports/{id}', [ReportController::class, 'destroy']);

    // Bilan de santé
    Route::get('/health-assessment/latest', [HealthAssessmentController::class, 'latest']);
    Route::get('/health-assessment/history', [HealthAssessmentController::class, 'history']);
    Route::post('/health-assessment', [HealthAssessmentController::class, 'store']);
    Route::put('/health-assessment/{id}', [HealthAssessmentController::class, 'update']);

    // Scanner
    Route::post('/scan', [ScanController::class, 'scan']);
    Route::post('/scan/store', [ScanController::class, 'store']);
    Route::get('/scan/history', [ScanController::class, 'history']);
    Route::get('/scan/test', [ScanController::class, 'test']);


     
    // ============================================
    // 4. PRISES DE MÉDICAMENTS (NOUVEAU)
    // ============================================
    Route::get('medication-takings', [MedicationTakingController::class, 'index']);
    Route::post('medication-takings', [MedicationTakingController::class, 'store']);
    Route::put('medication-takings/{id}/take', [MedicationTakingController::class, 'take']);
    Route::put('medication-takings/{id}/miss', [MedicationTakingController::class, 'miss']);
    Route::get('medication-takings/statistics', [MedicationTakingController::class, 'statistics']);
    
    // ============================================
    // 5. ALERTES D'URGENCE (NOUVEAU)
    // ============================================
    Route::post('emergency-alerts', [EmergencyAlertController::class, 'store']);
    Route::put('emergency-alerts/{id}/status', [EmergencyAlertController::class, 'updateStatus']);
    Route::get('emergency-alerts', [EmergencyAlertController::class, 'index']);
    Route::get('emergency-alerts/{id}', [EmergencyAlertController::class, 'show']);
    
    // ============================================
    // 6. NOTIFICATIONS (NOUVEAU)
    // ============================================
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

    // ✅ AJOUTER LA ROUTE DE TEST
    Route::post('/notifications/test', [NotificationController::class, 'sendTest']);
    
    // ============================================
    // 7. CONVERSATIONS CHATBOT (NOUVEAU)
    // ============================================
    Route::post('chatbot/conversations/start', [ChatbotConversationController::class, 'start']);
    Route::post('chatbot/conversations/{id}/message', [ChatbotConversationController::class, 'sendMessage']);
    Route::get('chatbot/conversations', [ChatbotConversationController::class, 'index']);
    Route::get('chatbot/conversations/{id}/messages', [ChatbotConversationController::class, 'messages']);
    Route::put('chatbot/conversations/{id}/end', [ChatbotConversationController::class, 'end']);
    
    // ============================================
    // 8. MÉTRIQUES DE SANTÉ (NOUVEAU)
    // ============================================
    Route::get('health-metrics', [UserHealthMetricController::class, 'index']);
    Route::post('health-metrics', [UserHealthMetricController::class, 'store']);
    Route::get('health-metrics/latest', [UserHealthMetricController::class, 'latest']);
    Route::get('health-metrics/statistics', [UserHealthMetricController::class, 'statistics']);
    Route::get('health-metrics/{id}', [UserHealthMetricController::class, 'show']);
    Route::put('health-metrics/{id}', [UserHealthMetricController::class, 'update']);
    Route::delete('health-metrics/{id}', [UserHealthMetricController::class, 'destroy']);
    
    // ============================================
    // 9. ÉVALUATIONS DE SANTÉ
    // ============================================
    Route::apiResource('health-assessments', HealthAssessmentController::class);
    Route::get('health-assessments/latest/result', [HealthAssessmentController::class, 'latest']);
    Route::get('health-assessments/statistics', [HealthAssessmentController::class, 'statistics']);

});

// routes/api.php - AJOUTER EN HAUT
Route::get('/health-check', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()
    ]);
});

// routes/api.php
Route::post('/llm/chat', [App\Http\Controllers\Api\LLMTestController::class, 'chat']);
Route::post('/llm/analyze', [App\Http\Controllers\Api\LLMTestController::class, 'analyze']);
Route::get('/llm/health', [App\Http\Controllers\Api\LLMTestController::class, 'health']);

Route::prefix('v1')->group(function () {
    // Routes Chat
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/extract', [ChatController::class, 'extract']);
    Route::get('/health', [ChatController::class, 'health']);
    
    // Routes sécurisées (authentification requise)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/history', [ChatController::class, 'history']);
        Route::delete('/history/{id}', [ChatController::class, 'deleteHistory']);
    });
});

// routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('medications')->group(function () {
        
        // ✅ Routes spécifiques (AVANT la route {id})
        Route::post('/check-interactions', [MedicationController::class, 'checkInteractions']);
        Route::get('/stock-alerts', [MedicationController::class, 'checkStockAlerts']);
        Route::post('/record-taking', [MedicationController::class, 'recordTaking']);
        Route::get('/observance-summary', [MedicationController::class, 'getObservanceSummary']);
        Route::post('/renewal-message', [MedicationController::class, 'generateRenewalMessage']);
        
        // ✅ AJOUTER CETTE ROUTE
        Route::get('/today-takings', [MedicationController::class, 'getTodayTakings']);
        
        Route::post('/mark-taken', [MedicationController::class, 'markTakingAsTaken']);
        
        // Routes CRUD (APRÈS)
        Route::get('/', [MedicationController::class, 'index']);
        Route::post('/', [MedicationController::class, 'store']);
        Route::get('/{id}', [MedicationController::class, 'show']);
        Route::put('/{id}', [MedicationController::class, 'update']);
        Route::delete('/{id}', [MedicationController::class, 'destroy']);
    });
});


// routes/api.php

// ✅ CORRECT - Placer les routes spécifiques AVANT la route {id}
Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    
    Route::prefix('medications')->group(function () {
        
        // ✅ Routes spécifiques (AVANT la route {id})
        Route::post('/check-interactions', [MedicationController::class, 'checkInteractions']);
        Route::get('/stock-alerts', [MedicationController::class, 'checkStockAlerts']);
        Route::post('/record-taking', [MedicationController::class, 'recordTaking']);
        Route::get('/observance-summary', [MedicationController::class, 'getObservanceSummary']);
        Route::post('/renewal-message', [MedicationController::class, 'generateRenewalMessage']);
        Route::get('/today-takings', [MedicationController::class, 'getTodayTakings']);  // ← ICI
        Route::post('/mark-taken', [MedicationController::class, 'markTakingAsTaken']);
        
        // ✅ Routes CRUD (APRÈS les routes spécifiques)
        Route::get('/', [MedicationController::class, 'index']);
        Route::post('/', [MedicationController::class, 'store']);
        Route::get('/{id}', [MedicationController::class, 'show']);
        Route::put('/{id}', [MedicationController::class, 'update']);
        Route::delete('/{id}', [MedicationController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    
    // Routes Rendez-vous
    Route::prefix('appointments')->group(function () {
        // CRUD
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{id}', [AppointmentController::class, 'show']);
        Route::put('/{id}', [AppointmentController::class, 'update']);
        Route::delete('/{id}', [AppointmentController::class, 'destroy']);
        
        // Fonctionnalités avancées
        Route::post('/check-conflicts', [AppointmentController::class, 'checkConflicts']);
        Route::post('/suggest-from-symptoms', [AppointmentController::class, 'suggestFromSymptoms']);
        Route::post('/{id}/briefing', [AppointmentController::class, 'generateBriefing']);
        Route::get('/today', [AppointmentController::class, 'today']);
        Route::get('/upcoming', [AppointmentController::class, 'upcoming']);
        Route::post('/{id}/confirm', [AppointmentController::class, 'confirm']);
    });
});

// ✅ Routes publiques pour Twilio
// ✅ BON - Une seule fois
// ✅ Routes webhooks (publiques - sans auth)
Route::prefix('voice-scheduler')->group(function () {
    Route::post('/webhook/response', [VoiceSchedulerController::class, 'handleResponse']);
    Route::post('/webhook/status', [VoiceSchedulerController::class, 'handleStatus']);
});

// ✅ Routes protégées (avec auth)
Route::middleware('auth:sanctum')->prefix('voice-scheduler')->group(function () {
    Route::post('/start', [VoiceSchedulerController::class, 'start']);
    Route::post('/{id}/accept', [VoiceSchedulerController::class, 'acceptAlternative']);
    Route::post('/{id}/reject', [VoiceSchedulerController::class, 'rejectAlternative']);
    Route::get('/{id}/status', [VoiceSchedulerController::class, 'getStatus']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    //Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/delete', [ProfileController::class, 'deleteAccount']);
});


Route::middleware('auth:sanctum')->group(function () {
    // Statistiques globales
    Route::get('/statistiques/global', [StatistiqueController::class, 'getGlobalStats']);
    Route::get('/statistiques/medicaments', [StatistiqueController::class, 'getMedicationStats']);
    Route::get('/statistiques/symptomes', [StatistiqueController::class, 'getSymptomStats']);
    Route::get('/statistiques/rendezvous', [StatistiqueController::class, 'getAppointmentStats']);
    Route::get('/statistiques/sante', [StatistiqueController::class, 'getHealthStats']);
    Route::get('/statistiques/activite', [StatistiqueController::class, 'getActivityStats']);
    
    // Export
    Route::get('/statistiques/export/pdf', [StatistiqueController::class, 'exportPDF']);
    Route::get('/statistiques/export/csv', [StatistiqueController::class, 'exportCSV']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/fcm/register', [FCMController::class, 'register']);
    Route::delete('/fcm/unregister', [FCMController::class, 'unregister']);
});

Route::middleware('auth:sanctum')->group(function () {
    
    // ✅ Routes Objectifs
    Route::prefix('goals')->group(function () {
        Route::get('/', [GoalController::class, 'index']);
        Route::post('/', [GoalController::class, 'store']);
        Route::get('/stats', [GoalController::class, 'stats']);
        Route::get('/{id}', [GoalController::class, 'show']);
        Route::put('/{id}', [GoalController::class, 'update']);
        Route::delete('/{id}', [GoalController::class, 'destroy']);
        Route::post('/{id}/progress', [GoalController::class, 'updateProgress']);
    });
    
    // ✅ Routes Défis
    Route::prefix('challenges')->group(function () {
        Route::get('/', [ChallengeController::class, 'index']);
        Route::post('/{id}/join', [ChallengeController::class, 'join']);
    });
    
    // ✅ Routes Récompenses
    Route::prefix('achievements')->group(function () {
        Route::get('/', [AchievementController::class, 'index']);
        Route::get('/stats', [AchievementController::class, 'stats']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Constantes Vitales
    Route::get('/constantes', [ConstantesVitalesController::class, 'index']);
    Route::get('/constantes/{type}', [ConstantesVitalesController::class, 'show']);
    Route::post('/constantes', [ConstantesVitalesController::class, 'store']);
    Route::put('/constantes/{id}', [ConstantesVitalesController::class, 'update']);
    Route::delete('/constantes/{id}', [ConstantesVitalesController::class, 'destroy']);
    
    // Seuils personnalisés
    Route::post('/seuils', [ConstantesVitalesController::class, 'setSeuils']);
    Route::get('/seuils', [ConstantesVitalesController::class, 'getSeuils']);
    
    // Corrélation
    Route::get('/correlation-symptomes', [ConstantesVitalesController::class, 'correlationSymptomes']);
    
    // Export
    Route::get('/export-constantes', [ConstantesVitalesController::class, 'exportCSV']);
    
    // Alertes
    Route::get('/alertes', [AlerteController::class, 'index']);
    Route::put('/alertes/{id}/lire', [AlerteController::class, 'marquerLue']);
    Route::put('/alertes/{id}/resoudre', [AlerteController::class, 'marquerResolue']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Gestion de la tension
    Route::prefix('tension')->group(function () {
        Route::get('/measures', [TensionController::class, 'index']);
        Route::post('/measures', [TensionController::class, 'store']);
        Route::get('/measures/{id}', [TensionController::class, 'show']);
        Route::put('/measures/{id}', [TensionController::class, 'update']);
        Route::delete('/measures/{id}', [TensionController::class, 'destroy']);
        // 🔥 Changer en POST pour accepter les données
        Route::post('/analyze/trends', [TensionController::class, 'analyzeTrends']);
        Route::post('/predict', [TensionController::class, 'predict']);
        
        // Analyse IA (Premium)
        //Route::get('/analyze/trends', [TensionController::class, 'analyzeTrends']);
        Route::get('/analyses', [TensionController::class, 'getAnalyses']);
        Route::get('/statistics', [TensionController::class, 'getStatistics']);
    });
});


