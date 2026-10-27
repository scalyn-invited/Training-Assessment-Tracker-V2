<?php

namespace Database\Factories;

use App\Models\User;

class SyntheticUserFactory extends ScopedFactory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => 'Fictional learner', 'email' => fake()->unique()->userName().'@example.invalid', 'password' => '!synthetic-sign-in-only!', 'role' => 'member', 'active' => true, 'is_synthetic' => true, 'sensitive_access' => false, 'origin' => 'manual', 'sync_policy' => 'local_only'];
    }
}
