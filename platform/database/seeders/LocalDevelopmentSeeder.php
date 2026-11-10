<?php

namespace Database\Seeders;

use App\Models\Appeal;
use App\Models\Group;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Grading;
use App\Services\LocalAccounts;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\SensitiveAccess;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\AuthorityFactory;
use Database\Factories\EnrolmentFactory;
use Database\Factories\EvidenceFileFactory;
use Database\Factories\GroupFactory;
use Database\Factories\OrganisationFactory;
use Database\Factories\SyntheticUserFactory;
use Database\Factories\TrainingScenarioFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/** Password fixtures advanced in resumable, transaction-scoped stages. */
class LocalDevelopmentSeeder extends Seeder
{
    public const VERSION = 'local-password-demo-v1';

    public const STEPS = 25;

    public function seedStep(string $installationId, string $passwordHash): int
    {
        $receipt = DB::table('training_installations')->where('installation_id', $installationId)->where('stage', 'migrated')->first();
        if (! $receipt || ! app()->environment('local') || config('training.environment') !== 'test'
            || config('training.authentication_mode') !== 'local_password' || config('ai.live_enabled')
            || config('training.smtp_enabled') || config('mail.default') !== 'array') {
            throw new \RuntimeException('Demo seeding requires an owned local installation.');
        }
        if ($receipt->fixture_version && $receipt->fixture_version !== self::VERSION) {
            throw new \RuntimeException('The pending demo belongs to another fixture version. Restore its matching code before resuming.');
        }
        $progress = $receipt->fixture_state ? json_decode($receipt->fixture_state, true, 64, JSON_THROW_ON_ERROR)
            : ['next' => 0, 'reference' => CarbonImmutable::now()->startOfWeek()->subWeeks(6)->toIso8601String(), 'clients' => []];
        $next = $progress['next'];
        if ($next === 0 && User::exists()) {
            throw new \RuntimeException('Initial demo seeding requires an empty workspace.');
        }
        $reference = CarbonImmutable::parse($progress['reference']);
        $authority = new AuthorityFactory;
        $service = app(ClientAdministration::class);
        $counter = 0;
        Str::createUuidsUsing(function () use ($installationId, $next, &$counter) {
            return Uuid::uuid5(Uuid::NAMESPACE_URL, $installationId.':demo:'.$next.':'.++$counter);
        });
        config(['demo.enabled' => true]);
        try {
            if ($next < 3) {
                $ci = $next;
                $spec = DemoCatalogue::CLIENTS[$ci];
                self::clock($reference);
                $org = OrganisationFactory::new()->create(['name' => $spec['name'].' (fictional)']);
                app(ClientContext::class)->id = $org->id;
                $users = [];
                $memberships = [];
                foreach ($spec['people'] as $i => $name) {
                    $role = $i === 0 ? 'admin' : ($i < 3 ? 'coordinator' : 'member');
                    $users[$i] = SyntheticUserFactory::new()->forClient($org)->create(['name' => $name.' · '.$spec['name'].' · '.$role, 'email' => 'demo.'.($ci + 1).'.'.$i.'@example.invalid', 'role' => $role]);
                    Identity::create(['user_id' => $users[$i]->id, 'issuer' => LocalAccounts::ISSUER, 'subject' => $users[$i]->id]);
                    $users[$i]->update(['password' => $passwordHash, 'local_password_enabled_at' => now()]);
                }
                $admin = $users[0];
                $memberships[0] = $authority->bootstrapAdmin($admin);
                $teams = [];
                foreach ($spec['teams'] as $name) {
                    $teams[] = GroupFactory::new()->forClient($org)->create(['name' => $name]);
                }
                $cohort = GroupFactory::new()->forClient($org)->cohort()->create(['name' => 'New specialists — September 2026']);
                foreach ([1, 2] as $i) {
                    $memberships[$i] = $authority->member($admin, $users[$i], 'coordinator', $teams[0]->id);
                    $service->grantRole($admin, $org->id, $memberships[$i]->id, 'coordinator', $teams[1]->id, 'Synthetic second-team coordinator assignment');
                }
                $grants = [];
                for ($i = 3; $i < 10; $i++) {
                    $memberships[$i] = $authority->member($admin, $users[$i], 'member');
                    $team = $teams[($i - 3) % 2];
                    if ($i !== 3) {
                        $service->joinTeam($admin, $org->id, $memberships[$i]->id, $team->id, 'Synthetic internal team membership');
                    }
                    if ($i === 4) {
                        $service->joinTeam($admin, $org->id, $memberships[$i]->id, $teams[0]->id, 'Synthetic multi-team membership');
                    }
                    // Cohort enrolment is directory membership, never an authority grant.
                    DB::table('memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $org->id, 'environment' => 'test', 'group_id' => $cohort->id, 'user_id' => $users[$i]->id, 'starts_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                    $primary = $i % 2 ? 1 : 2;
                    $a = $service->allocate($admin, $org->id, $memberships[$i]->id, $memberships[$primary]->id, 'primary', 'Synthetic accountable coordinator assignment');
                    if ($i === 5) {
                        $service->allocate($admin, $org->id, $memberships[$i]->id, $memberships[3 - $primary]->id, 'backup', 'Synthetic backup assignment without management authority');
                    }
                    $grants[$i] = app(SensitiveAccess::class)->grant($admin, $org->id, $a->id, 'Synthetic explicit assessment access from a different Client Admin');
                }
                DB::table('client_brandings')->insert(['organisation_id' => $org->id, 'environment' => 'test', 'mode' => 'white-label', 'client_name' => $spec['name'], 'app_name' => 'Skills Academy', 'accent' => $spec['colour'], 'theme_colours' => json_encode(['generation' => 1, 'source' => $spec['colour']]), 'default_theme' => $ci === 1 ? 'client-dark' : 'client-light', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('client_theme_preferences')->insert(['user_id' => $users[4]->id, 'organisation_id' => $org->id, 'environment' => 'test', 'theme' => 'neon-cyan', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

                $progress['clients'][$ci] = ['org' => $org->id,
                    'users' => array_map(fn ($u) => $u->id, $users),
                    'memberships' => array_map(fn ($m) => $m->id, $memberships),
                    'teams' => array_map(fn ($g) => $g->id, $teams), 'grants' => $grants];
            } elseif ($next < 24) {
                $ci = intdiv($next - 3, 7);
                $mi = ($next - 3) % 7;
                $spec = DemoCatalogue::CLIENTS[$ci];
                $saved = $progress['clients'][$ci];
                $org = Organisation::findOrFail($saved['org']);
                $users = array_map(fn ($id) => User::findOrFail($id), $saved['users']);
                $teams = array_map(fn ($id) => Group::findOrFail($id), $saved['teams']);
                $admin = $users[0];
                app(ClientContext::class)->id = $org->id;
                $files = [];
                $scenarios = [];
                foreach ([$mi => ['onboarding', 'draft', 'saved', 'submitted', 'correction', 'completed', 'appeal'][$mi]] as $mi => $state) {
                    self::clock($reference->addDay());
                    $i = $mi + 3;
                    $primary = $i % 2 ? 1 : 2;
                    $e = EnrolmentFactory::new()->forClient($org)->create(['member_id' => $users[$i]->id, 'coordinator_id' => $users[$primary]->id, 'group_id' => $i === 3 ? null : $teams[$mi % 2]->id, 'title' => $spec['topic'].' — '.$state]);
                    $scenarios[$state] = (new TrainingScenarioFactory)->create($e, $users[$primary], $users[$i], $spec, $state, $reference) + ['member' => $users[$i]->id, 'primary' => $users[$primary]->id];
                    $content = "SYNTHETIC DEMO EVIDENCE — no real customer or employee information.\n".$spec['example']."\nChecklist: impact recorded; safe steps checked; next owner named.\n";
                    $key = 'demo/'.$e->id.'/case-notes.txt';
                    Storage::disk('local')->put($key, $content);
                    $files[$key] = hash('sha256', $content);
                    EvidenceFileFactory::new()->forClient($org)->create(['enrolment_id' => $e->id, 'storage_key' => $key, 'bytes' => strlen($content), 'sha256' => $files[$key], 'scan_status' => 'clean', 'purpose' => 'evidence', 'uploaded_by' => $users[$i]->id]);
                    $key = 'demo/'.$e->id.'/reviewer-notes.txt';
                    $private = "Synthetic private reviewer note. Coaching observation only: check attribution and escalation owner.\n";
                    Storage::disk('local')->put($key, $private);
                    $files[$key] = hash('sha256', $private);
                    EvidenceFileFactory::new()->forClient($org)->create(['enrolment_id' => $e->id, 'storage_key' => $key, 'original_name' => 'synthetic-reviewer-notes.txt', 'bytes' => strlen($private), 'sha256' => $files[$key], 'scan_status' => 'clean', 'sensitive' => true, 'purpose' => 'assessment', 'uploaded_by' => $admin->id]);
                    if ($state === 'appeal') {
                        app(Grading::class)->assignAppeal($admin, Appeal::findOrFail($scenarios[$state]['appeal']), $users[3 - $primary], 'Synthetic case-specific independent appeal review');
                    }
                }

            } elseif ($next === 24) {
                $all = [];
                $inventory = [];
                self::clock($reference->addWeeks(5));
                foreach ($progress['clients'] as $saved) {
                    $org = Organisation::findOrFail($saved['org']);
                    $users = array_map(fn ($id) => User::findOrFail($id), $saved['users']);
                    $memberships = array_map(fn ($id) => DB::table('client_memberships')->where('id', $id)->first(), $saved['memberships']);
                    $admin = $users[0];
                    app(ClientContext::class)->id = $org->id;
                    app(SensitiveAccess::class)->revoke($admin, $org->id, $saved['grants'][6], 'Synthetic sensitive access revocation demonstration');
                    $all[] = compact('org', 'users', 'memberships', 'admin');
                }
                // An existing member also belongs to another client, with explicit independent authority.
                self::clock($reference->addWeeks(5));
                $a = $all[1];
                app(ClientContext::class)->id = $a['org']->id;
                $shared = $all[0]['users'][3];
                $m = $authority->member($a['admin'], $shared, 'member');
                $service->allocate($a['admin'], $a['org']->id, $m->id, $a['memberships'][1]->id, 'primary', 'Synthetic teamless multi-client member allocation');
                $inventory[1]['shared_member'] = $shared->id;
                // Preserve history while removing ordinary access for one fictional departing member.
                $a = $all[2];
                app(ClientContext::class)->id = $a['org']->id;
                $m = $a['memberships'][3];
                $service->leave($a['admin'], $a['org']->id, $m->id, $m->version, 'Synthetic client departure with history retained');

            }
            $progress['next'] = min(self::STEPS, $next + 1);
            DB::table('training_installations')->where('installation_id', $installationId)->update([
                'fixture_state' => json_encode($progress, JSON_THROW_ON_ERROR), 'fixture_version' => self::VERSION,
            ]);

            return $progress['next'];
        } finally {
            Str::createUuidsNormally();
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            app(ClientContext::class)->id = null;
            config(['demo.enabled' => false]);
        }
    }

    private static function clock(CarbonImmutable $at): void
    {
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }
}
