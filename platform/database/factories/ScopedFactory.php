<?php

namespace Database\Factories;

use App\Models\Enrolment;
use App\Models\Group;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

abstract class ScopedFactory extends Factory
{
    public function forClient(Organisation $client): static
    {
        return $this->state(['organisation_id' => $client->id, 'environment' => 'test']);
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($record) {
            if (! $record->organisation_id || $record->environment !== 'test' || ! Organisation::whereKey($record->organisation_id)->exists()) {
                throw new \InvalidArgumentException('Synthetic factories require an explicit existing client and test environment.');
            }
            foreach (['group_id' => Group::class, 'enrolment_id' => Enrolment::class] as $key => $model) {
                if ($record->$key && ! $model::whereKey($record->$key)->where('organisation_id', $record->organisation_id)->where('environment', 'test')->exists()) {
                    throw new \InvalidArgumentException('Foreign-client factory relationship.');
                }
            }
        });
    }
}
