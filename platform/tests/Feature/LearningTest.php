<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\Identity;
use App\Models\MemberCalendar;
use App\Models\OnboardingVersion;
use App\Services\LearningCalendar;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        $this->seed();
    }

    private function signIn(string $name = 'coordinator-a'): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person($name)->id)->firstOrFail()->id])->assertRedirect('/dashboard');
    }

    private function failure(int $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected HTTP '.$code);
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_member_draft_is_resumable_and_stale_saves_do_not_overwrite(): void
    {
        $this->signIn('member-a');
        $url = '/enrolments/'.$this->enrolment()->id.'/onboarding';
        $this->postJson($url.'/profile', ['expected_version' => 0, 'data' => ['current_role' => 'Saved role']])->assertOk()->assertJsonPath('version', 1);
        $this->get($url.'?step=profile')->assertOk()->assertSee('Saved role');
        $this->postJson($url.'/profile', ['expected_version' => 0, 'data' => ['current_role' => 'Stale role']])->assertStatus(409);
        $this->assertSame('Saved role', OnboardingVersion::first()->data['profile']['current_role']);
        $this->assertDatabaseCount('onboarding_versions', 1);
    }

    public function test_member_cannot_write_owned_targets_confirm_assessments_or_read_peer_inputs(): void
    {
        $this->signIn('member-a');
        $url = '/enrolments/'.$this->enrolment()->id.'/onboarding';
        $this->postJson($url.'/capability', ['expected_version' => 0, 'data' => []])->assertForbidden();
        $this->postJson($url.'/assessment', ['expected_version' => 0, 'confirm_assessment' => 1, 'data' => $this->inputs()['assessment']])->assertForbidden();
        $peer = Enrolment::where('member_id', $this->person('member-b')->id)->firstOrFail();
        $this->get('/enrolments/'.$peer->id.'/onboarding')->assertNotFound();
        $this->assertDatabaseCount('onboarding_versions', 0);
    }

    public function test_confirmation_metadata_cannot_be_forged_and_edit_clears_confirmation(): void
    {
        $service = app(Onboarding::class);
        $input = $this->inputs()['assessment'] + ['confirmed_by' => $this->person()->id];
        $draft = $service->save($this->person('member-a'), $this->enrolment(), 'assessment', $input, 0);
        $this->assertArrayNotHasKey('confirmed_by', $draft->data['assessment']);
        $draft = $service->save($this->person(), $this->enrolment(), 'assessment', $input, 1, true);
        $this->assertSame($this->person()->id, $draft->data['assessment']['confirmed_by']);
        $draft = $service->save($this->person('member-a'), $this->enrolment(), 'assessment', $input, 2);
        $this->assertArrayNotHasKey('confirmed_by', $draft->data['assessment']);
    }

    public function test_all_duration_and_daily_budget_boundaries_are_enforced(): void
    {
        $service = app(Onboarding::class);
        $version = 0;
        foreach ([4, 6, 8, 10, 12] as $duration) {
            foreach ([15, 120] as $minutes) {
                $input = $this->inputs($duration, $minutes)['schedule'];
                $draft = $service->save($this->person(), $this->enrolment(), 'schedule', $input, $version++);
                $this->assertSame($duration, $draft->data['schedule']['duration']);
            }
        }
        foreach ([14, 121] as $minutes) {
            $this->invalid(fn () => $service->save($this->person(), $this->enrolment(), 'schedule', $this->inputs(4, $minutes)['schedule'], $version));
        }
        $this->invalid(fn () => $service->save($this->person(), $this->enrolment(), 'schedule', $this->inputs(5)['schedule'], $version));
    }

    public function test_calendar_uses_local_business_days_and_holidays_for_freeze(): void
    {
        $calendar = new MemberCalendar(['timezone' => 'Asia/Manila', 'weekdays' => [1, 2, 3, 4, 5], 'holidays' => [], 'absences' => []]);
        $service = app(LearningCalendar::class);
        $this->assertSame('2027-01-07T09:00:00+00:00', $service->freeze($calendar, '2027-01-11'));
        $calendar->holidays = ['2027-01-08'];
        $calendar->absences = ['2027-01-12'];
        $this->assertSame('2027-01-06T09:00:00+00:00', $service->freeze($calendar, '2027-01-11'));
        $blocks = $service->blocks($calendar, '2027-01-11', 4);
        $this->assertCount(4, $blocks);
        $this->assertNotContains('2027-01-12', $blocks[0]['days']);
        $this->assertContains('2027-01-13', $blocks[0]['days']);
    }

    public function test_heading_only_plans_and_incorrect_rubric_or_minutes_cannot_enter_review(): void
    {
        $plan = $this->prepare();
        $service = app(LearningPlans::class);
        $this->invalid(fn () => $service->review($this->person(), $this->enrolment(), $plan->version, false, ''));
        $plan = $this->fill($plan);
        $input = $this->blockInput($plan->content['blocks'][0]);
        $input['lessons'][0]['revision'] = 6;
        $input['weight_two'] = 0.8;
        $plan = $service->edit($this->person(), $this->enrolment(), $plan->version, 0, $input, 'Deliberately invalid test input.');
        $this->invalid(fn () => $service->review($this->person(), $this->enrolment(), $plan->version, false, ''));
        $this->assertDatabaseCount('programme_approvals', 0);
    }

    public function test_approval_binds_exact_content_and_edits_preserve_approved_history(): void
    {
        $plan = $this->fill($this->prepare());
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $approved = $service->review($this->person(), $this->enrolment(), $plan->version, true, 'All lessons reviewed against the target.');
        $this->assertSame('ready', $this->enrolment()->status);
        $this->assertDatabaseCount('capacity_allocations', 4);
        $this->assertDatabaseHas('programme_approvals', ['programme_version_id' => $approved->id, 'content_hash' => hash('sha256', json_encode($approved->content, JSON_THROW_ON_ERROR))]);
        $copy = $service->edit($this->person(), $this->enrolment(), $plan->version, 0, $this->blockInput($plan->content['blocks'][0]), 'Clarify the practice instructions.');
        $this->assertSame('approved', $approved->fresh()->state);
        $this->assertSame($approved->content, $approved->fresh()->content);
        $this->assertSame('draft', $copy->state);
        $this->failure(409, fn () => $service->review($this->person(), $this->enrolment(), $plan->version, true, 'Attempt stale approval.'));
    }

    public function test_pending_review_is_invalidated_by_content_edits(): void
    {
        $plan = $this->fill($this->prepare());
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $new = $service->edit($this->person(), $this->enrolment(), $plan->version, 0, $this->blockInput($plan->content['blocks'][0]), 'Review requested further clarification.');
        $this->assertSame('superseded', $plan->fresh()->state);
        $this->failure(409, fn () => $service->review($this->person(), $this->enrolment(), $new->version, true, 'Cannot bypass review.'));
    }

    public function test_overlapping_approved_plans_share_one_capacity_budget(): void
    {
        $first = $this->fill($this->prepare());
        $secondEnrolment = $this->enrolment()->replicate();
        $secondEnrolment->title = 'Concurrent synthetic plan';
        $secondEnrolment->save();
        $second = $this->fill($this->prepare($secondEnrolment, 4, 30, false), $secondEnrolment);
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $first->version, false, '');
        $service->review($this->person(), $secondEnrolment, $second->version, false, '');
        $service->review($this->person(), $this->enrolment(), $first->version, true, 'Approve first baseline.');
        $this->invalid(fn () => $service->review($this->person(), $secondEnrolment, $second->version, true, 'Would exceed shared minutes.'));
        $this->assertDatabaseCount('programme_approvals', 1);
        $this->assertSame('needs_review', $second->fresh()->state);
    }

    public function test_calendar_and_onboarding_changes_make_pending_plans_stale(): void
    {
        $plan = $this->fill($this->prepare());
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $onboarding = app(Onboarding::class);
        $onboarding->save($this->person(), $this->enrolment(), 'profile', $this->inputs()['profile'], $onboarding->latest($this->enrolment())->version);
        $this->failure(409, fn () => $service->review($this->person(), $this->enrolment(), $plan->version, true, 'Cannot approve stale inputs.'));
    }

    public function test_absence_on_an_approved_day_is_rejected_without_rewriting_history(): void
    {
        $plan = $this->fill($this->prepare());
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $service->review($this->person(), $this->enrolment(), $plan->version, true, 'Approve complete baseline.');
        $onboarding = app(Onboarding::class);
        $schedule = $this->inputs()['schedule'];
        $schedule['absences'] = [$schedule['start_date']];
        $draft = $onboarding->save($this->person(), $this->enrolment(), 'schedule', $schedule, $onboarding->latest($this->enrolment())->version);
        $this->invalid(fn () => $onboarding->confirmCalendar($this->person(), $this->enrolment(), 1, $draft->version, 'Member is away that day.'));
        $this->assertDatabaseCount('member_calendars', 1);
        $this->assertSame('approved', $plan->fresh()->state);
        $this->assertSame(4, DB::table('capacity_allocations')->where('active', true)->count());
    }

    public function test_removed_coordinator_and_self_approval_are_rejected(): void
    {
        $plan = $this->fill($this->prepare());
        DB::table('coordinator_assignments')->where('user_id', $this->person()->id)->update(['ends_at' => now()->subMinute()]);
        $this->failure(404, fn () => app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, false, ''));
        $member = $this->person('member-a');
        $member->update(['role' => 'coordinator', 'sensitive_access' => true]);
        $enrolment = $this->enrolment();
        $enrolment->update(['coordinator_id' => $member->id]);
        DB::table('coordinator_assignments')->where('user_id', $this->person()->id)->update(['user_id' => $member->id, 'ends_at' => null]);
        $this->failure(403, fn () => app(LearningPlans::class)->review($member, $enrolment, $plan->version, false, ''));
    }

    public function test_views_render_and_peer_cannot_read_version_history(): void
    {
        $plan = $this->prepare();
        $this->signIn();
        foreach (array_merge(array_keys(Onboarding::STEPS), ['review']) as $step) {
            $this->get('/enrolments/'.$this->enrolment()->id.'/onboarding?step='.$step)->assertOk();
        }
        $this->get('/enrolments/'.$this->enrolment()->id.'/curriculum')->assertOk()->assertSee('Curriculum');
        $this->signIn('member-b');
        $this->get('/enrolments/'.$this->enrolment()->id.'/curriculum?version='.$plan->version)->assertNotFound();
    }
}
