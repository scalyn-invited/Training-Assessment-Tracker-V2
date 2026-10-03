<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\Group;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\ApprovedFeed;
use App\Services\SandboxDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegrationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_excludes_history_until_explicit_export_and_rechecks_membership(): void
    {
        config(['training.environment' => 'production']);
        $org = Organisation::create(['name' => 'Contract fixture']);
        $member = User::create(['organisation_id' => $org->id, 'environment' => 'production', 'name' => 'Contract learner', 'email' => 'contract-member@example.invalid', 'password' => 'unused', 'role' => 'member', 'active' => true,
            'is_synthetic' => false, 'origin' => 'manual', 'sync_policy' => 'linked', 'external_person_id' => 'person-1', 'linked_at' => now()->addMinute()]);
        Identity::create(['user_id' => $member->id, 'issuer' => 'https://idp.example.invalid', 'subject' => 'contract-member']);
        $group = Group::create(['organisation_id' => $org->id, 'environment' => 'production', 'name' => 'Contract group', 'active' => true]);
        $membership = (string) Str::uuid();
        DB::table('memberships')->insert(['id' => $membership, 'organisation_id' => $org->id, 'environment' => 'production', 'user_id' => $member->id, 'group_id' => $group->id, 'starts_at' => now()]);
        $enrolment = Enrolment::create(['organisation_id' => $org->id, 'environment' => 'production', 'member_id' => $member->id, 'coordinator_id' => $member->id, 'group_id' => $group->id, 'title' => 'Contract fixture', 'status' => 'completed']);
        $feed = app(ApprovedFeed::class);
        $feed->record($enrolment, $enrolment->id, 1, 'programme.completed', ['status' => 'completed']);
        $this->assertSame([], $feed->read($member)['items']);
        $member->update(['export_training_history' => true]);
        $visible = $feed->read($member);
        $this->assertCount(1, $visible['items']);
        $this->assertSame(['status' => 'completed'], $visible['items'][0]['facts']);
        $this->assertSame([], $feed->read($member, $visible['next_cursor'])['items']);
        DB::table('memberships')->where('id', $membership)->update(['ends_at' => now()]);
        $member->increment('permission_version');
        $this->assertSame([], $feed->read($member)['items']);
    }

    public function test_directory_page_does_not_advance_cursor_or_persist_partial_events_on_failure(): void
    {
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        $this->seed();
        $org = Organisation::firstOrFail();
        $event = ['event_id' => (string) Str::uuid(), 'entity_id' => 'page-person', 'entity_version' => 1, 'environment' => 'test', 'source' => 'primary-platform', 'event_type' => 'person.upserted', 'payload' => ['display_name' => 'Page fixture', 'active' => true]];
        $bad = $event;
        $bad['event_id'] = (string) Str::uuid();
        $bad['entity_id'] = 'invalid-person';
        $bad['payload'] = [];
        try {
            app(SandboxDirectory::class)->page($org, null, 'page-1', [$event, $bad]);
            $this->fail('Invalid page accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('integration_cursors', 0);
            $this->assertDatabaseCount('inbound_events', 0);
        }
        app(SandboxDirectory::class)->page($org, null, 'page-1', [$event]);
        $this->assertDatabaseHas('integration_cursors', ['cursor' => 'page-1']);
        $this->assertDatabaseCount('inbound_events', 1);
        $remove = $event;
        $remove['event_id'] = (string) Str::uuid();
        $remove['entity_version'] = 2;
        $remove['event_type'] = 'person.deactivated';
        $remove['payload'] = ['active' => false];
        app(SandboxDirectory::class)->page($org, 'page-1', 'page-2', [$remove]);
        $stale = $event;
        $stale['event_id'] = (string) Str::uuid();
        $this->assertSame('stale', app(SandboxDirectory::class)->apply($org, $stale));
        $this->assertFalse(User::where('external_person_id', 'page-person')->firstOrFail()->active);
    }
}
