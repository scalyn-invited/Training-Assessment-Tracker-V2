<?php

namespace Database\Seeders;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Group;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\MockIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(MockIdentity::class)->assertEnabled();
        $organisation = Organisation::firstOrCreate(['name' => 'Training Sandbox']);
        $groups = [];
        foreach (['Operations', 'Customer Support'] as $name) {
            $groups[] = Group::firstOrCreate(['organisation_id' => $organisation->id, 'environment' => 'test', 'name' => $name]);
        }
        $users = [];
        foreach ([
            ['admin', 'Alex · Administrator', 'admin', false],
            ['coordinator-a', 'Casey · Operations coordinator', 'coordinator', true],
            ['coordinator-b', 'Morgan · Support coordinator', 'coordinator', true],
            ['member-a', 'Sam · Operations learner', 'member', false],
            ['member-b', 'Jordan · Support learner', 'member', false],
        ] as [$subject, $name, $role, $sensitive]) {
            $users[$subject] = User::firstOrCreate(['email' => $subject.'@example.invalid'], [
                'organisation_id' => $organisation->id, 'name' => $name, 'password' => Str::random(64),
                'environment' => 'test', 'role' => $role, 'is_synthetic' => true, 'sensitive_access' => $sensitive,
                'origin' => $subject === 'member-a' ? 'primary_import' : 'manual',
                'sync_policy' => $subject === 'member-a' ? 'linked' : 'local_only',
                'external_person_id' => 'sandbox-'.$subject,
                'permissions_synced_at' => $subject === 'member-a' ? now() : null,
            ]);
            Identity::firstOrCreate(['issuer' => config('training.mock_issuer'), 'subject' => $subject], ['user_id' => $users[$subject]->id]);
        }
        foreach ([0 => ['coordinator-a', 'member-a', 'Clear, reproducible procedures'], 1 => ['coordinator-b', 'member-b', 'Customer communication fundamentals']] as $index => [$coordinator, $member, $title]) {
            foreach (['coordinator_assignments' => $coordinator, 'memberships' => $member] as $table => $subject) {
                if (! DB::table($table)->where('group_id', $groups[$index]->id)->where('user_id', $users[$subject]->id)->exists()) {
                    DB::table($table)->insert(['id' => (string) Str::uuid(), 'organisation_id' => $organisation->id, 'environment' => 'test',
                        'group_id' => $groups[$index]->id, 'user_id' => $users[$subject]->id,
                        'starts_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $enrolment = Enrolment::firstOrCreate(['member_id' => $users[$member]->id, 'title' => $title], [
                'organisation_id' => $organisation->id, 'environment' => 'test', 'group_id' => $groups[$index]->id,
                'coordinator_id' => $users[$coordinator]->id, 'status' => 'onboarding',
            ]);
            if (! $enrolment->files()->exists()) {
                $content = "Synthetic evidence fixture. No real employee data.\nThis file demonstrates an authorised private download.\n";
                $key = 'fixtures/'.Str::uuid();
                Storage::disk('local')->put($key, $content);
                EvidenceFile::create(['organisation_id' => $organisation->id, 'environment' => 'test', 'enrolment_id' => $enrolment->id,
                    'storage_key' => $key, 'original_name' => 'synthetic-evidence.txt', 'mime' => 'text/plain',
                    'bytes' => strlen($content), 'sha256' => hash('sha256', $content), 'scan_status' => 'clean', 'sensitive' => false]);
            }
        }
    }
}
