<?php

namespace Database\Factories;

use App\Models\Group;

class GroupFactory extends ScopedFactory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return ['name' => 'Fictional team', 'kind' => 'team', 'active' => true];
    }

    public function cohort(): static
    {
        return $this->state(['kind' => 'cohort']);
    }
}
