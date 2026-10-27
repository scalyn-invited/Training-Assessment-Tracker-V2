<?php

namespace Database\Seeders;

use App\Models\Appeal;
use App\Models\Identity;
use App\Models\User;
use App\Services\Grading;
use App\Services\MockIdentity;
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

class RealisticDemoSeeder extends Seeder
{
    public const VERSION = 'realistic-demo-v1';

    public static function guard(): string
    {
        app(MockIdentity::class)->assertEnabled();
        $connection = config('database.default');
        $actual = config("database.connections.$connection.database");
        $expected = config('demo.database');
        $root = config('demo.root');
        if (! config('demo.enabled') || ! $expected || $actual !== $expected || ! $root || ! is_dir($root) || config('ai.live_enabled') || config('training.smtp_enabled') || config('mail.default') !== 'array') {
            throw new \RuntimeException('Refusing non-isolated or externally connected demo configuration.');
        }
        if ($connection === 'sqlite') {
            $normal = str_replace('\\', '/', realpath($actual) ?: '');
            if (! str_contains($normal, '/.runtime/realistic-demo') || ! str_ends_with($normal, '/demo.sqlite')) {
                throw new \RuntimeException('Demo requires a dedicated realistic-demo*/demo.sqlite path.');
            }
        } elseif ($connection === 'mariadb') {
            if (! app()->environment('testing')) {
                throw new \RuntimeException('MariaDB demo qualification is testing only.');
            }
            require_once base_path('tests/Support/client-qualification-guard.php');
            assertClientQualificationDatabase();
        } else {
            throw new \RuntimeException('Unsupported demo database.');
        }
        if (! is_dir($root.'/private') || realpath(config('filesystems.disks.local.root')) !== realpath($root.'/private')) {
            throw new \RuntimeException('Demo private storage is not isolated.');
        }
        if (User::where('is_synthetic', false)->orWhere('environment', '!=', 'test')->exists()) {
            throw new \RuntimeException('Non-synthetic data detected.');
        }

        return $root;
    }

    public static function snapshot(): string
    {
        $result = [];
        foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $table) {
            $table = last(explode('.', $table));
            if (in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'])) {
                continue;
            }
            $rows = DB::table($table)->get()->map(fn ($r) => json_encode((array) $r, JSON_THROW_ON_ERROR))->sort()->values()->all();
            $result[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }ksort($result);

        return hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public static function sourceHash(): string
    {
        $files = [...glob(database_path('factories/*.php')), ...glob(database_path('seeders/*Demo*.php'))];
        sort($files);

        return hash('sha256', implode('', array_map(fn ($f) => hash_file('sha256', $f), $files)));
    }

    public function run(): void
    {
        $root = self::guard();
        $file = $root.'/manifest.json';
        $source = self::sourceHash();
        $lock = fopen($root.'/seed.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Another demo generation is running.');
        }
        try {
            if (is_file($file)) {
                $prior = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                if (($prior['status'] ?? '') !== 'complete' || ($prior['source'] ?? '') !== $source || ($prior['snapshot'] ?? '') !== self::snapshot()) {
                    throw new \RuntimeException('Changed or partial demo: choose a fresh disposable database.');
                }
                foreach ($prior['files'] as $key => $hash) {
                    if (! Storage::disk('local')->exists($key) || hash('sha256', Storage::disk('local')->get($key)) !== $hash) {
                        throw new \RuntimeException('Demo evidence changed; use a fresh disposable database.');
                    }
                }
                $this->command?->info('Identical demo already complete; no records or audit events added.');

                return;
            }
            if (DB::table('users')->exists() || DB::table('organisations')->exists()) {
                throw new \RuntimeException('Demo requires an empty migrated disposable database.');
            }
            file_put_contents($file, json_encode(['status' => 'building', 'source' => $source, 'version' => self::VERSION]));
            $counter = 0;
            Str::createUuidsUsing(function () use (&$counter) {
                return Uuid::uuid5(Uuid::NAMESPACE_URL, self::VERSION.':'.++$counter);
            });
            $reference = CarbonImmutable::parse(config('demo.reference'));
            $inventory = [];
            $files = [];
            DB::transaction(function () use ($reference, &$inventory, &$files) {
                $all = [];
                $authority = new AuthorityFactory;
                $service = app(ClientAdministration::class);
                foreach (DemoCatalogue::CLIENTS as $ci => $spec) {
                    self::clock($reference);
                    $org = OrganisationFactory::new()->create(['name' => $spec['name'].' (fictional)']);
                    app(ClientContext::class)->id = $org->id;
                    $users = [];
                    $memberships = [];
                    foreach ($spec['people'] as $i => $name) {
                        $role = $i === 0 ? 'admin' : ($i < 3 ? 'coordinator' : 'member');
                        $users[$i] = SyntheticUserFactory::new()->forClient($org)->create(['name' => $name.' · '.$spec['name'].' · '.$role, 'email' => 'demo.'.($ci + 1).'.'.$i.'@example.invalid', 'role' => $role]);
                        Identity::create(['user_id' => $users[$i]->id, 'issuer' => config('training.mock_issuer'), 'subject' => 'demo-'.($ci + 1).'-'.$i]);
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
                    $scenarios = [];
                    foreach (['onboarding', 'draft', 'saved', 'submitted', 'correction', 'completed', 'appeal'] as $mi => $state) {
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
                    self::clock($reference->addWeeks(5));
                    // One active primary becomes summary-only for sensitive material after explicit revocation.
                    app(SensitiveAccess::class)->revoke($admin, $org->id, $grants[6], 'Synthetic sensitive access revocation demonstration');
                    $all[] = compact('org', 'users', 'memberships', 'admin');
                    $inventory[] = ['client' => $org->id, 'name' => $spec['name'], 'teams' => array_map(fn ($g) => $g->id, $teams), 'cohort' => $cohort->id, 'people' => array_map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email], $users), 'scenarios' => $scenarios];
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
                $inventory[2]['departed_member'] = $a['users'][3]->id;
            });
            file_put_contents($file, json_encode(['status' => 'complete', 'version' => self::VERSION, 'source' => $source, 'reference' => $reference->toIso8601String(), 'snapshot' => self::snapshot(), 'files' => $files, 'clients' => $inventory], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->command?->info('Created three fictional clients, 30 people and 21 training scenarios.');
        } finally {
            Str::createUuidsNormally();
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            app(ClientContext::class)->id = null;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function clock(CarbonImmutable $at): void
    {
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }
}
