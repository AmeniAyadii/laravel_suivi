<?php

namespace Database\Factories;

use App\Models\MedicationTaking;
use App\Models\User;
use App\Models\Medicament;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationTakingFactory extends Factory
{
    protected $model = MedicationTaking::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'medicament_id' => Medicament::factory(),
            'prise_prevue' => $this->faker->dateTimeBetween('now', '+1 week'),
            'prise_reelle' => null,
            'statut' => $this->faker->randomElement(['prevue', 'prise', 'oubliee', 'reportee']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}