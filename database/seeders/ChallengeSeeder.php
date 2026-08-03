<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Challenge;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $challenges = [
            [
                'title' => 'Défi Marche 7 jours',
                'description' => 'Marchez 10 000 pas par jour pendant 7 jours consécutifs',
                'icon' => '🚶',
                'color_hex' => '#4CAF50',
                'start_date' => now(),
                'end_date' => now()->addDays(7),
                'rewards' => [
                    ['type' => 'badge', 'title' => 'Marcheur', 'value' => 1],
                    ['type' => 'points', 'title' => 'Points de défi', 'value' => 50],
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Défi Hydratation',
                'description' => 'Buvez 8 verres d\'eau par jour pendant 5 jours',
                'icon' => '💧',
                'color_hex' => '#2196F3',
                'start_date' => now(),
                'end_date' => now()->addDays(5),
                'rewards' => [
                    ['type' => 'badge', 'title' => 'Hydraté', 'value' => 1],
                    ['type' => 'points', 'title' => 'Points de défi', 'value' => 30],
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Défi Sommeil',
                'description' => 'Dormez 8h par nuit pendant 7 jours',
                'icon' => '😴',
                'color_hex' => '#9C27B0',
                'start_date' => now(),
                'end_date' => now()->addDays(7),
                'rewards' => [
                    ['type' => 'badge', 'title' => 'Dormeur', 'value' => 1],
                    ['type' => 'points', 'title' => 'Points de défi', 'value' => 40],
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Défi Méditation',
                'description' => 'Méditez 15 minutes par jour pendant 10 jours',
                'icon' => '🧘',
                'color_hex' => '#FF6B6B',
                'start_date' => now()->addDays(3),
                'end_date' => now()->addDays(13),
                'rewards' => [
                    ['type' => 'badge', 'title' => 'Zen', 'value' => 1],
                    ['type' => 'points', 'title' => 'Points de défi', 'value' => 60],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($challenges as $challenge) {
            Challenge::create($challenge);
        }
    }
}