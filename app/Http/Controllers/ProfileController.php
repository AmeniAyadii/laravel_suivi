<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Charger le profil avec les relations
            $user->load('profile');
            
            // Structurer les données pour le mobile
            $profileData = [
                'id' => $user->id,
                'nom' => $user->name,
                'email' => $user->email,
                'photo_url' => $user->profile->photo_url ?? null,
                'age' => $user->profile->age ?? null,
                'sexe' => $user->profile->sexe ?? null,
                'taille' => $user->profile->taille ?? null,
                'poids' => $user->profile->poids ?? null,
                'telephone' => $user->profile->telephone ?? null,
                'groupe_sanguin' => $user->profile->groupe_sanguin ?? null,
                'allergies' => $user->profile->allergies ?? null,
                'maladies_chroniques' => $user->profile->maladies_chroniques ?? null,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'points' => $user->points ?? 0,
                'medicaments' => $user->profile->medicaments_count ?? 0,
                'rendez_vous' => $user->profile->appointments_count ?? 0,
                'sante_score' => $user->profile->health_score ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil utilisateur
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Validation des données
            $validator = Validator::make($request->all(), [
                'nom' => 'sometimes|string|max:255',
                'age' => 'sometimes|integer|min:1|max:150',
                'sexe' => 'sometimes|string|in:Homme,Femme,Autre',
                'taille' => 'sometimes|numeric|min:50|max:300',
                'poids' => 'sometimes|numeric|min:10|max:300',
                'telephone' => 'sometimes|string|max:20',
                'groupe_sanguin' => 'sometimes|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'allergies' => 'sometimes|string|nullable',
                'maladies_chroniques' => 'sometimes|string|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mettre à jour l'utilisateur
            if ($request->has('nom')) {
                $user->name = $request->nom;
                $user->save();
            }

            // Mettre à jour ou créer le profil
            $profile = $user->profile ?? new UserProfile();
            $profile->user_id = $user->id;
            
            if ($request->has('age')) $profile->age = $request->age;
            if ($request->has('sexe')) $profile->sexe = $request->sexe;
            if ($request->has('taille')) $profile->taille = $request->taille;
            if ($request->has('poids')) $profile->poids = $request->poids;
            if ($request->has('telephone')) $profile->telephone = $request->telephone;
            if ($request->has('groupe_sanguin')) $profile->groupe_sanguin = $request->groupe_sanguin;
            if ($request->has('allergies')) $profile->allergies = $request->allergies;
            if ($request->has('maladies_chroniques')) $profile->maladies_chroniques = $request->maladies_chroniques;
            
            $profile->save();

            // Recharger les données mises à jour
            $user->refresh();
            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $this->formatProfileData($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload de la photo de profil
     */
    public function uploadPhoto(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Supprimer l'ancienne photo
            if ($user->profile && $user->profile->photo_url) {
                Storage::disk('public')->delete($user->profile->photo_url);
            }

            // Upload de la nouvelle photo
            $file = $request->file('photo');
            $filename = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profiles', $filename, 'public');

            // Mettre à jour le profil
            $profile = $user->profile ?? new UserProfile();
            $profile->user_id = $user->id;
            $profile->photo_url = $path;
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'Photo mise à jour avec succès',
                'photo_url' => Storage::url($path)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formater les données du profil
     */
    private function formatProfileData($user)
    {
        return [
            'id' => $user->id,
            'nom' => $user->name,
            'email' => $user->email,
            'photo_url' => $user->profile->photo_url ? Storage::url($user->profile->photo_url) : null,
            'age' => $user->profile->age ?? null,
            'sexe' => $user->profile->sexe ?? null,
            'taille' => $user->profile->taille ?? null,
            'poids' => $user->profile->poids ?? null,
            'telephone' => $user->profile->telephone ?? null,
            'groupe_sanguin' => $user->profile->groupe_sanguin ?? null,
            'allergies' => $user->profile->allergies ?? null,
            'maladies_chroniques' => $user->profile->maladies_chroniques ?? null,
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at,
            'points' => $user->points ?? 0,
            'medicaments' => $user->profile->medicaments_count ?? 0,
            'rendez_vous' => $user->profile->appointments_count ?? 0,
            'sante_score' => $user->profile->health_score ?? 0,
        ];
    }

    /**
     * Supprimer le compte utilisateur
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Supprimer la photo de profil
            if ($user->profile && $user->profile->photo_url) {
                Storage::disk('public')->delete($user->profile->photo_url);
            }

            // Supprimer le profil et l'utilisateur
            if ($user->profile) {
                $user->profile->delete();
            }
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }
}