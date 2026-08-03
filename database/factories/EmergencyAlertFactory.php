<?php

namespace Database\Factories;

use App\Models\EmergencyAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmergencyAlertFactory extends Factory
{
    protected $model = EmergencyAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symptomes' => $this->faker->sentence(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'adresse' => $this->faker->address(),
            'statut' => $this->faker->randomElement(['envoyee', 'en_cours', 'terminee']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}