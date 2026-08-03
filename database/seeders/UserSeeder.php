<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'nom' => 'Admin',
            'prenom' => 'System',
            'email' => 'admin@healthapp.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Médecin
        User::create([
            'nom' => 'Martin',
            'prenom' => 'Jean',
            'email' => 'dr.martin@healthapp.com',
            'password' => Hash::make('password'),
            'role' => 'medecin',
            'is_active' => true,
            'email_verified_at' => now(),
            'telephone' => '0123456789',
        ]);

        // Patient
        User::create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'email' => 'patient@healthapp.com',
            'password' => Hash::make('password'),
            'role' => 'patient',
            'is_active' => true,
            'email_verified_at' => now(),
            'age' => 35,
            'sexe' => 'F',
            'taille' => 165,
            'poids' => 68,
            'groupe_sanguin' => 'A+',
            'telephone' => '0612345678',
            'contact_urgence_nom' => 'Pierre Dupont',
            'contact_urgence_telephone' => '0678912345',
            'contact_urgence_relation' => 'Conjoint',
        ]);
    }
}