<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('BR-####'),
            'name' => fake()->company(),
            'type' => Branch::TYPE_STORE,
            'legal_address' => fake()->address(),
            'timezone' => 'America/La_Paz',
            'is_active' => true,
        ];
    }

    public function central(): static
    {
        return $this->state(fn () => ['type' => Branch::TYPE_CENTRAL]);
    }
}
