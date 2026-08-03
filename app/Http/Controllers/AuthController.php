<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'age' => 'required|integer|min:0|max:150',
            'sexe' => 'required|in:M,F,A',
            'taille' => 'required|numeric|min:0|max:300',
            'poids' => 'required|numeric|min:0|max:500',
            'groupe_sanguin' => 'nullable|string|max:3',
            'allergies' => 'nullable|string',
            'maladies_chroniques' => 'nullable|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'nom' => $request->nom,
                'age' => $request->age,
                'sexe' => $request->sexe,
                'taille' => $request->taille,
                'poids' => $request->poids,
                'groupe_sanguin' => $request->groupe_sanguin,
                'allergies' => $request->allergies,
                'maladies_chroniques' => $request->maladies_chroniques,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            // ✅ Créer un token pour l'utilisateur après inscription
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'token' => $token,
                'user' => [
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
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
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
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Email ou mot de passe incorrect'
        ], 401);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
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
            ]
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
}
