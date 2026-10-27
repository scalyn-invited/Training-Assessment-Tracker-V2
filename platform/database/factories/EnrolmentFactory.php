<?php

namespace Database\Factories;

use App\Models\Enrolment;
use Illuminate\Support\Facades\DB;

class EnrolmentFactory extends ScopedFactory
{
    protected $model = Enrolment::class;

    public function definition(): array
    {
        return ['title' => 'Synthetic skills programme', 'status' => 'onboarding', 'group_id' => null, 'version' => 1, 'timezone' => 'Asia/Manila', 'daily_minutes' => 30, 'duration_weeks' => 4];
    }

    public function configure(): static
    {
        return parent::configure()->afterMaking(function ($record) {
            foreach (['member_id', 'coordinator_id'] as $key) {
                if (! DB::table('client_memberships')->where('organisation_id', $record->organisation_id)->where('user_id', $record->$key)->where('environment', 'test')->where('status', 'active')->exists()) {
                    throw new \InvalidArgumentException('Enrolment factory requires explicit client members.');
                }
            }
        });
    }
}
