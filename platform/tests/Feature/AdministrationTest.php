<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\User;
use App\Services\Access;
use App\Services\Administration;
use App\Services\PrimaryPlatform;
use App\Services\Promotions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Http::preventStrayRequests();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_admin_can_create_synthetic_person_cohort_assignments_and_enrolment(): void
    {
        $admin = $this->admin();
        $service = app(Administration::class);
        $person = $service->person($admin, ['name' => 'Synthetic trainee', 'email' => 'trainee@example.invalid', 'role' => 'member', 'synthetic' => true, 'reason' => 'Add a synthetic local trainee.']);
        $cohort = $service->group($admin, 'Local practice cohort', 'Create a local practice cohort.');
        $service->assignment($admin, $person, $cohort, false, true, $person->fresh()->permission_version, 'Assign member to practice.');
        $service->assignment($admin, $this->person(), $cohort, true, true, $this->person()->permission_version, 'Assign accountable reviewer.');
        $enrolment = $service->enrol($admin, $person->fresh(), $this->person(), $cohort, 'Local demonstration', 'Start local onboarding journey.');
        $this->assertTrue(app(Access::class)->canView($person->fresh(), $enrolment));
        $this->assertSame('local_only', $person->fresh()->sync_policy);
        $service->deactivate($admin, $person, $person->fresh()->permission_version, 'Deactivate synthetic test account.');
        $this->assertFalse(app(Access::class)->canView($person->fresh(), $enrolment));
    }

    public function test_member_cannot_create_people_and_synthetic_person_cannot_be_promoted(): void
    {
        try {
            app(Administration::class)->group($this->person('member-a'), 'Forbidden cohort', 'Unauthorized cohort creation.');
            $this->fail('Unauthorized administration accepted');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->expectException(HttpException::class);
        app(Promotions::class)->request($this->admin(), $this->person('member-a'), (string) Str::uuid(), 'Do not export synthetic history.');
    }

    public function test_ambiguous_promotion_reconciles_original_key_and_does_not_export_history(): void
    {
        $person = app(Administration::class)->person($this->admin(), ['name' => 'Contract fixture', 'email' => 'contract@example.invalid', 'role' => 'member', 'synthetic' => false, 'reason' => 'Isolated contract fixture only.']);
        Identity::create(['user_id' => $person->id, 'issuer' => 'https://idp.example.invalid', 'subject' => 'verified-contract-subject']);
        $service = app(Promotions::class);
        $key = (string) Str::uuid();
        $promotion = $service->request($this->admin(), $person, $key, 'Verified identity and duplicate review.');
        $this->assertSame($promotion->id, $service->request($this->admin(), $person, $key, 'Verified duplicate request retry.')->id);
        $adapter = new class extends PrimaryPlatform
        {
            public array $seen = [];

            public array $payload = [];

            public function createPerson(string $key, array $payload): array
            {
                $this->seen[] = $key;
                $this->payload = $payload;
                throw new \RuntimeException('timeout_after_acceptance');
            }

            public function findByKey(string $key): ?array
            {
                $this->seen[] = $key;

                return ['idempotency_key' => $key, 'person_id' => 'primary-contract-1', 'local_person_id' => $this->payload['local_person_id'], 'identity' => $this->payload['identity']];
            }
        };
        app()->instance(PrimaryPlatform::class, $adapter);
        $service->send($this->admin(), $promotion);
        $this->assertSame('ambiguous', $promotion->fresh()->status);
        $this->assertSame('promotion_pending', $person->fresh()->sync_policy);
        $service->send($this->admin(), $promotion, true);
        $this->assertSame([$key, $key], $adapter->seen);
        $this->assertSame('linked', $person->fresh()->sync_policy);
        $this->assertFalse((bool) $person->fresh()->export_training_history);
        Http::assertNothingSent();
    }

    public function test_administration_and_progress_pages_render_with_scope(): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->admin()->id)->first()->id]);
        $this->get('/administration')->assertOk()->assertSee('Create enrolment')->assertDontSee('submission body');
        $this->post('/logout');
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person('member-a')->id)->first()->id]);
        $this->get('/administration')->assertForbidden();
        $this->get('/enrolments/'.$this->enrolment()->id.'/progress')->assertOk()->assertSee('Progress and evidence');
    }
}
