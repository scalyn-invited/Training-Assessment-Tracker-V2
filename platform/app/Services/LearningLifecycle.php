<?php

namespace App\Services;

use App\Models\AdaptationProposal;
use App\Models\CalendarChange;
use App\Models\Enrolment;
use App\Models\KpiVersion;
use App\Models\LearningBlock;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Models\WorkDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningLifecycle
{
    public function pause(User $actor, Enrolment $enrolment, int $expected, string $reason): void
    {
        app(Delivery::class)->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            abort_unless($enrolment->version === $expected && $enrolment->status === 'active', 409);
            $enrolment->update(['status' => 'paused', 'version' => $expected + 1]);
            Audit::record($actor, 'enrolment.paused', $enrolment->id, ['reason' => $reason]);
            app(LearningNotices::class)->record($enrolment, $enrolment->member_id, 'paused:'.$enrolment->id.':'.$enrolment->version, 'programme_paused');
        });
    }

    private function previewData(Enrolment $enrolment, int $weeks): array
    {
        abort_unless($enrolment->status === 'paused' && $weeks >= 0 && $weeks <= 52, 409);
        $calendar = app(Onboarding::class)->calendar($enrolment);
        $days = [];
        $replacements = [];
        foreach (LearningBlock::where('enrolment_id', $enrolment->id)->where('state', 'current')->orderBy('number')->get() as $block) {
            $content = $block->content;
            $move = ! $block->started_at && ! WorkDraft::where('learning_block_id', $block->id)->exists() && ! SubmissionAttempt::where('learning_block_id', $block->id)->exists();
            if ($move) {
                $content['start'] = CarbonImmutable::parse($content['start'])->addWeeks($weeks)->toDateString();
                $content['freeze_at'] = app(LearningCalendar::class)->freeze($calendar, $content['start']);
                foreach ($content['lessons'] as &$lesson) {
                    $lesson['date'] = CarbonImmutable::parse($lesson['date'])->addWeeks($weeks)->toDateString();
                }
                unset($lesson);
                if ($weeks > 0) {
                    abort_if($content['start'] < now($enrolment->timezone)->toDateString(), 422, 'Move upcoming blocks to today or later.');
                }
                $replacements[$block->id] = $content;
            }
            foreach ($content['lessons'] as $lesson) {
                // Started history remains untouched; reserve its remaining future days too.
                if ($lesson['date'] >= now($enrolment->timezone)->toDateString()) {
                    $days[$lesson['date']] = ($days[$lesson['date']] ?? 0) + $lesson['reading'] + $lesson['practice'] + $lesson['assessment'] + $lesson['revision'];
                }
            }
        }
        app(LearningCalendar::class)->assertCapacity($calendar, $days, $enrolment->id);

        return ['weeks' => $weeks, 'calendar_id' => $calendar->id, 'days' => $days, 'replacements' => $replacements];
    }

    public function preview(User $actor, Enrolment $enrolment, int $expected, int $weeks, string $reason): CalendarChange
    {
        return app(Delivery::class)->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $weeks, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            abort_unless($enrolment->version === $expected, 409);

            return CalendarChange::create(['enrolment_id' => $enrolment->id, 'enrolment_version' => $expected, 'preview' => $this->previewData($enrolment, $weeks),
                'status' => 'preview', 'actor_id' => $actor->id, 'reason' => $reason]);
        });
    }

    public function resume(User $actor, Enrolment $enrolment, CalendarChange $change): void
    {
        app(Delivery::class)->locked($actor, $enrolment, function ($actor, $enrolment) use ($change) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            $change = $change->fresh();
            abort_unless($change->enrolment_id === $enrolment->id && $change->status === 'preview' && $change->enrolment_version === $enrolment->version, 409);
            $data = $this->previewData($enrolment, $change->preview['weeks']);
            abort_unless($data === $change->preview, 409, 'Calendar or content changed. Make a new preview.');
            foreach ($data['replacements'] as $id => $content) {
                $block = LearningBlock::findOrFail($id);
                $block->update(['state' => 'superseded']);
                $next = LearningBlock::create(['enrolment_id' => $enrolment->id, 'organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                    'programme_version_id' => $block->programme_version_id, 'member_calendar_id' => $data['calendar_id'], 'number' => $block->number,
                    'version' => $block->version + 1, 'content' => $content, 'rubric' => $block->rubric, 'actor_id' => $actor->id, 'reason' => $change->reason]);
                app(ObjectiveQuizzes::class)->copy($block, $next);
            }
            $plan = LearningBlock::where('enrolment_id', $enrolment->id)->firstOrFail()->programme_version_id;
            DB::table('capacity_allocations')->where('enrolment_id', $enrolment->id)->where('active', true)->where('day', '>=', now($enrolment->timezone)->toDateString())->update(['active' => false, 'updated_at' => now()]);
            foreach ($data['days'] as $day => $minutes) {
                DB::table('capacity_allocations')->insert(['id' => (string) Str::uuid(), 'enrolment_id' => $enrolment->id, 'programme_version_id' => $plan,
                    'member_id' => $enrolment->member_id, 'day' => $day, 'minutes' => $minutes, 'revision' => $enrolment->version, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            AdaptationProposal::where('enrolment_id', $enrolment->id)->whereIn('status', ['queued', 'provisional'])->update(['status' => 'stale']);
            $change->update(['status' => 'approved']);
            $enrolment->update(['status' => 'active', 'version' => $enrolment->version + 1]);
            Audit::record($actor, 'enrolment.resumed', $enrolment->id, ['calendar_change_id' => $change->id]);
            app(LearningNotices::class)->record($enrolment, $enrolment->member_id, 'resumed:'.$change->id, 'programme_resumed');
        });
    }

    public function complete(User $actor, Enrolment $enrolment, int $expected, string $reason): void
    {
        app(Delivery::class)->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            abort_unless($enrolment->status === 'active' && $enrolment->version === $expected, 409);
            $blocks = LearningBlock::where('enrolment_id', $enrolment->id)->where('state', 'current')->get();
            abort_unless($blocks->count() === $enrolment->duration_weeks, 409);
            foreach ($blocks as $block) {
                foreach ($block->content['lessons'] as $index => $lesson) {
                    abort_unless(SubmissionAttempt::where('learning_block_id', $block->id)->where('lesson_index', $index)->whereNotNull('approved_grade_id')->where('status', 'approved')->exists(), 409, 'Every required lesson needs approved evidence.');
                }
            }
            $metrics = KpiVersion::where('enrolment_id', $enrolment->id)->where('effective_at', '<=', now())->get()->groupBy('number')->map(fn ($versions) => $versions->sortByDesc('version')->first());
            abort_unless($metrics->count() >= 3, 409);
            foreach ($metrics as $metric) {
                abort_unless(app(Kpis::class)->snapshot($actor, $metric)->status === 'on_target', 409, 'KPI targets need sufficient approved or verified evidence.');
            }
            $enrolment->update(['status' => 'completed', 'version' => $expected + 1]);
            app(ApprovedFeed::class)->record($enrolment, $enrolment->id, $enrolment->version, 'programme.completed', ['status' => 'completed']);
            Audit::record($actor, 'enrolment.completed', $enrolment->id, ['capability_confirmation' => $reason]);
            app(LearningNotices::class)->record($enrolment, $enrolment->member_id, 'completed:'.$enrolment->id, 'programme_completed');
        });
    }
}
