<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Medicament;
use App\Models\RendezVous;
use App\Models\Symptome;
use App\Models\ConstanteVitale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
{
    // 1. Récupérer le rôle demandé
    $role = $request->input('role', 'patient');
    if (!in_array($role, ['patient', 'doctor', 'admin'])) {
        return response()->json([
            'success' => false,
            'error' => 'Rôle invalide',
        ], 422);
    }

    // 2. Règles de validation communes + spécifiques au rôle
    $commonRules = [
        'nom' => 'required|string|max:100',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6|confirmed',
        'telephone' => 'nullable|string|max:20',
    ];

    $roleRules = match ($role) {
        'patient' => [
            'age' => 'required|integer|min:0|max:150',
            'sexe' => 'required|in:M,F,A',
            'taille' => 'required|numeric|min:0|max:300',
            'poids' => 'required|numeric|min:0|max:500',
            'groupe_sanguin' => 'nullable|string|max:3',
            'allergies' => 'nullable|string',
            'maladies_chroniques' => 'nullable|string',
        ],
        'doctor' => [
            'specialite' => 'required|string|max:100',
            'numero_ordre' => 'required|string|max:50|unique:users,numero_ordre',
            'cabinet_phone' => 'nullable|string|max:20',
            'cabinet_address' => 'nullable|string|max:255',
        ],
        'admin' => [
            'admin_code' => 'required|string',
        ],
    };

    $validator = Validator::make($request->all(), array_merge($commonRules, $roleRules));

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    // 3. Vérification du code d'invitation pour admin
    if ($role === 'admin') {
        $expectedCode = config('services.admin.invite_code');
        if (empty($expectedCode) || $request->admin_code !== $expectedCode) {
            return response()->json([
                'success' => false,
                'error' => 'Code d\'invitation administrateur invalide.',
            ], 403);
        }
    }

    // 4. Construction du payload selon le rôle
    try {
        $userData = [
            'nom' => $request->nom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'telephone' => $request->telephone,
        ];

        switch ($role) {
            case 'patient':
                $userData = array_merge($userData, [
                    'age' => $request->age,
                    'sexe' => $request->sexe,
                    'taille' => $request->taille,
                    'poids' => $request->poids,
                    'groupe_sanguin' => $request->groupe_sanguin,
                    'allergies' => $request->allergies,
                    'maladies_chroniques' => $request->maladies_chroniques,
                ]);
                break;

            case 'doctor':
                $userData = array_merge($userData, [
                    'specialite' => $request->specialite,
                    'numero_ordre' => $request->numero_ordre,
                    'cabinet_phone' => $request->cabinet_phone,
                    'cabinet_address' => $request->cabinet_address,
                    // Valeurs par défaut pour colonnes NOT NULL de la table users
                    'age' => $request->input('age', 0),
                    'sexe' => $request->input('sexe', 'A'),
                    'taille' => $request->input('taille', 0),
                    'poids' => $request->input('poids', 0),
                ]);
                break;

            case 'admin':
                $userData = array_merge($userData, [
                    'age' => $request->input('age', 0),
                    'sexe' => $request->input('sexe', 'A'),
                    'taille' => $request->input('taille', 0),
                    'poids' => $request->input('poids', 0),
                ]);
                break;
        }

        $user = User::create($userData);
        $user->assignRole($role);

        // 5. Créer aussi une entrée dans la table `doctors` si c'est un médecin
        if ($role === 'doctor') {
            \App\Models\Doctor::create([
                'nom' => $request->nom,
                'prenom' => '', // optionnel, à compléter plus tard via profil
                'specialite' => $request->specialite,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'cabinet_phone' => $request->cabinet_phone ?? 'Non renseigné',
                'cabinet_address' => $request->cabinet_address,
                'horaires' => null,
                'accepts_voice_scheduler' => true,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'token' => $token,
            'user' => $this->buildUserPayload($user),
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de l\'inscription: ' . $e->getMessage(),
        ], 500);
    }
}

    
public function login(Request $request)
{
    $stage = 'validation';

    try {
        Log::info('LOGIN: Début de la tentative de connexion');

        // 1. Validation des données
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('LOGIN: Validation échouée');

            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2. Vérification des identifiants
        $stage = 'authentication';

        Log::info('LOGIN: Vérification des identifiants');

        if (!Auth::guard('web')->attempt(
            $request->only('email', 'password')
        )) {
            Log::warning('LOGIN: Identifiants incorrects');

            return response()->json([
                'success' => false,
                'error' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        $user = Auth::guard('web')->user();

        Log::info('LOGIN: Authentification réussie', [
            'user_id' => $user->id,
        ]);

        // 3. Création du token Sanctum
        $stage = 'token_creation';

        Log::info('LOGIN: Création du token Sanctum');

        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Construction des informations utilisateur
        $stage = 'user_payload';

        Log::info('LOGIN: Construction du profil utilisateur');

        $userPayload = $this->buildUserPayload($user);

        Log::info('LOGIN: Connexion terminée avec succès', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => $userPayload,
        ]);

    } catch (Throwable $e) {

        Log::error('LOGIN: ERREUR HTTP 500', [
            'stage' => $stage,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur interne lors de la connexion',
            'stage' => $stage,
        ], 500);
    }
}


    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => $this->buildUserPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès'
        ]);
    }

    public function updateCredentials(Request $request)
    {
        $user = $request->user();

        // 1. Validation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // 2. Vérifier l'ancien mot de passe si on change le mot de passe
        if ($request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Le mot de passe actuel est incorrect.'
                ], 401);
            }
            $user->password = $request->new_password; // Le cast 'hashed' fera le reste
        }

        // 3. Mettre à jour l'email
        $user->email = $request->email;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Identifiants mis à jour avec succès',
            'user' => $this->buildUserPayload($user)
        ]);
    }

    // ============================================================
    // ✅ NOUVELLE MÉTHODE UTILITAIRE
    // ============================================================
    private function buildUserPayload(User $user): array
    {
        // 1. Statistiques
        $medicamentsCount = Medicament::where('user_id', $user->id)->count();
        $rendezVousCount = RendezVous::where('user_id', $user->id)->count();

        // 2. Score de santé (0-100)
        $santeScore = 100;
        $dernieresConstantes = ConstanteVitale::where('user_id', $user->id)
            ->whereIn('type_constante', ['tension_systolique', 'frequence_cardiaque', 'temperature'])
            ->orderBy('date_mesure', 'desc')
            ->get()
            ->groupBy('type_constante');

        if (isset($dernieresConstantes['tension_systolique'])) {
            $tension = (float) $dernieresConstantes['tension_systolique']->first()->valeur;
            if ($tension > 160 || $tension < 90) $santeScore -= 20;
        }
        if (isset($dernieresConstantes['temperature'])) {
            $temp = (float) $dernieresConstantes['temperature']->first()->valeur;
            if ($temp > 38 || $temp < 35.5) $santeScore -= 20;
        }
        if (isset($dernieresConstantes['frequence_cardiaque'])) {
            $fc = (float) $dernieresConstantes['frequence_cardiaque']->first()->valeur;
            if ($fc > 110 || $fc < 50) $santeScore -= 15;
        }
        $santeScore = max(0, $santeScore);

        // 3. Points
        $points = (int) round(($medicamentsCount * 5) + ($rendezVousCount * 10) + ($santeScore / 2));

        return [
            'id' => $user->id,
            'nom' => $user->nom,
            'email' => $user->email,
            'age' => $user->age,
            'sexe' => $user->sexe,
            'taille' => $user->taille,
            'poids' => $user->poids,
            'groupe_sanguin' => $user->groupe_sanguin,
            'allergies' => $user->allergies,
            'maladies_chroniques' => $user->maladies_chroniques,
            'telephone' => $user->telephone,
            'is_premium' => (bool) ($user->is_premium ?? false),
            'role' => $user->getRoleNames()->first() ?? 'patient',
            'specialite' => $user->specialite,
            'is_active' => (bool) ($user->is_active ?? true),
            'created_at' => $user->created_at?->toIso8601String(),
            'photo_url' => $user->photo_url ?? null,
            // ✅ STATISTIQUES
            'medicaments' => $medicamentsCount,
            'rendez_vous' => $rendezVousCount,
            'sante_score' => $santeScore,
            'points' => $points,
        ];
    }
}