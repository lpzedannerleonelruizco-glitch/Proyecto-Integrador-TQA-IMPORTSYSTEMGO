<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'employee_code' => fake()->unique()->bothify('EMP-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'home_branch_id' => Branch::factory(),
            'status' => Employee::STATUS_ACTIVE,
            'hired_at' => fake()->dateTimeBetween('-5 years', 'now'),
            'terminated_at' => null,
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn () => ['user_id' => User::factory()]);
    }
}
