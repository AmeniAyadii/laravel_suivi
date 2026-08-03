<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Challenge;
use Carbon\Carbon;

class GenerateDailyChallenges extends Command
{
    protected $signature = 'challenges:generate-daily {days=7}';
    protected $description = 'Générer des défis quotidiens pour les X prochains jours';

    // ✅ Liste des défis quotidiens
    private $dailyTemplates = [
        [
            'title' => 'Défi Marche',
            'description' => 'Marchez 10 000 pas aujourd\'hui !',
            'icon' => '🚶',
            'color_hex' => '#4CAF50',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Marcheur du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Hydratation',
            'description' => 'Buvez 8 verres d\'eau aujourd\'hui !',
            'icon' => '💧',
            'color_hex' => '#2196F3',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Hydraté du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Sommeil',
            'description' => 'Dormez 8h cette nuit !',
            'icon' => '😴',
            'color_hex' => '#9C27B0',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Dormeur du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Méditation',
            'description' => 'Méditez 15 minutes aujourd\'hui !',
            'icon' => '🧘',
            'color_hex' => '#FF6B6B',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Zen du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Nutrition',
            'description' => 'Mangez 5 fruits et légumes aujourd\'hui !',
            'icon' => '🥗',
            'color_hex' => '#FF9800',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Nutrition du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Lecture',
            'description' => 'Lisez 30 minutes aujourd\'hui !',
            'icon' => '📚',
            'color_hex' => '#795548',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Lecteur du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
        [
            'title' => 'Défi Yoga',
            'description' => 'Faites 20 minutes de yoga aujourd\'hui !',
            'icon' => '🧘‍♀️',
            'color_hex' => '#E91E63',
            'rewards' => [
                ['type' => 'badge', 'title' => 'Yogi du jour', 'value' => 1],
                ['type' => 'points', 'title' => 'Points de défi', 'value' => 10],
            ],
        ],
    ];

    public function handle()
    {
        $days = $this->argument('days');
        $templates = $this->dailyTemplates;
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->addDays($i);
            
            // ✅ Vérifier si un défi existe déjà pour cette date
            $existing = Challenge::whereDate('start_date', $date)->first();
            if ($existing) {
                $this->info("Défi déjà existant pour le {$date->format('d/m/Y')}");
                continue;
            }
            
            // ✅ Sélectionner un défi aléatoire ou basé sur le jour de la semaine
            $index = $i % count($templates);
            $template = $templates[$index];
            
            // ✅ Personnaliser le titre avec la date
            $dayNumber = $i + 1;
            $title = "{$template['title']} - Jour {$dayNumber}";
            
            Challenge::create([
                'title' => $title,
                'description' => $template['description'],
                'icon' => $template['icon'],
                'color_hex' => $template['color_hex'],
                'start_date' => $date,
                'end_date' => $date->copy()->addDay(),
                'rewards' => $template['rewards'],
                'is_active' => true,
            ]);
            
            $this->info("✅ Défi créé pour le {$date->format('d/m/Y')}: $title");
        }
        
        $this->info("🎯 {$days} défis générés avec succès !");
    }
}