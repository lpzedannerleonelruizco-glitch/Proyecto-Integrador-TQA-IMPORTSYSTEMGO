<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BranchUserMembership> */
class BranchUserMembershipFactory extends Factory
{
    protected $model = BranchUserMembership::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'granted_by' => null,
            'ended_by' => null,
            'end_reason' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }
}
