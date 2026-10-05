<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\LearningBlock;
use App\Models\ObjectiveQuiz;
use App\Models\Organisation;
use App\Models\ProgrammeVersion;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Models\WorkDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Delivery
{
    public function locked(User $actor, Enrolment $enrolment, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $enrolment, $action) {
            Organisation::whereKey($enrolment->organisation_id)->lockForUpdate()->firstOrFail();
            User::whereKey($enrolment->member_id)->lockForUpdate()->firstOrFail();
            $enrolment = Enrolment::whereKey($enrolment->id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(Access::class)->canView($actor, $enrolment), 404);

            return $action($actor, $enrolment);
        }, 3);
    }

    public function activate(User $actor, Enrolment $enrolment, int $expected, string $reason): void
    {
        $this->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            $this->reason($reason);
            abort_unless($enrolment->version === $expected && $enrolment->status === 'ready', 409, 'The enrolment changed or is not ready.');
            $plan = ProgrammeVersion::where('enrolment_id', $enrolment->id)->where('state', 'approved')->firstOrFail();
            app(LearningPlans::class)->validate($plan, $enrolment);
            foreach ($plan->content['blocks'] as $index => $block) {
                $id = (string) Str::uuid();
                $rubric = ['id' => $id, 'version' => 1, 'criteria' => [
                    ['id' => $id.':one', 'label' => $block['criterion_one'], 'weight' => (float) $block['weight_one'], 'maximum' => 100, 'competency' => $block['competency']],
                    ['id' => $id.':two', 'label' => $block['criterion_two'], 'weight' => (float) $block['weight_two'], 'maximum' => 100, 'competency' => $block['competency']],
                ]];
                LearningBlock::create(['id' => $id, 'enrolment_id' => $enrolment->id, 'organisation_id' => $enrolment->organisation_id,
                    'environment' => $enrolment->environment, 'programme_version_id' => $plan->id, 'member_calendar_id' => $plan->member_calendar_id,
                    'number' => $index + 1, 'version' => 1, 'content' => $block, 'rubric' => $rubric, 'actor_id' => $actor->id, 'reason' => $reason]);
            }
            $enrolment->update(['status' => 'active', 'version' => $enrolment->version + 1]);
            app(Kpis::class)->seed($actor, $enrolment, $plan);
            Audit::record($actor, 'enrolment.activated', $enrolment->id, ['programme_version_id' => $plan->id, 'reason' => $reason]);
            app(LearningNotices::class)->record($enrolment, $enrolment->member_id, 'activation:'.$plan->id, 'programme_ready');
        });
    }

    public function reason(string $reason): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:8|max:2000'])->validate();
    }

    public function earlyStart(User $actor, LearningBlock $block, string $reason): void
    {
        $this->locked($actor, $block->enrolment, function ($actor, $enrolment) use ($block, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            $this->reason($reason);
            $block = $block->fresh();
            abort_unless($enrolment->status === 'active' && $block->state === 'current' && ! $block->started_at, 409);
            $block->update(['started_at' => now(), 'early_start' => true]);
            Audit::record($actor, 'block.early_start', $block->id, ['reason' => $reason]);
        });
    }

    private function working(User $actor, Enrolment $enrolment, LearningBlock $block, int $lesson): void
    {
        abort_unless($actor->id === $enrolment->member_id, 403);
        abort_unless($enrolment->status === 'active' && $block->state === 'current', 409, 'This enrolment is not active or this block has been replaced.');
        abort_unless(isset($block->content['lessons'][$lesson]), 404);
        $today = CarbonImmutable::today($enrolment->timezone)->toDateString();
        abort_unless($block->early_start || $block->content['lessons'][$lesson]['date'] <= $today, 409, 'Future lessons are preview only. A coordinator can authorise early start.');
    }

    private function evidence(User $actor, Enrolment $enrolment, array $files): array
    {
        Validator::make(['files' => $files], ['files' => 'array|max:5', 'files.*' => 'uuid|distinct'])->validate();
        foreach ($files as $id) {
            $file = EvidenceFile::find($id);
            abort_unless($file && app(Access::class)->canFile($actor, $file), 422, 'Evidence is not accessible to this member.');
            abort_unless(EvidenceFile::whereKey($id)->where('enrolment_id', $enrolment->id)->where('organisation_id', $enrolment->organisation_id)
                ->where('environment', $enrolment->environment)->where('scan_status', 'clean')->exists(), 422, 'Evidence must belong to this enrolment and have passed scanning.');
        }

        return array_values($files);
    }

    public function save(User $actor, LearningBlock $block, int $lesson, int $expected, string $body, array $files = [], array $answers = []): WorkDraft
    {
        Validator::make(['body' => $body], ['body' => 'nullable|string|max:30000'])->validate();

        return $this->locked($actor, $block->enrolment, function ($actor, $enrolment) use ($block, $lesson, $expected, $body, $files, $answers) {
            $block = $block->fresh();
            $this->working($actor, $enrolment, $block, $lesson);
            $draft = WorkDraft::where('learning_block_id', $block->id)->where('lesson_index', $lesson)->where('member_id', $actor->id)->first();
            abort_unless(($draft?->version ?? 0) === $expected, 409, 'Your work changed in another window. Copy your text and reload.');
            Validator::make(['answers' => $answers], ['answers' => 'array|max:20', 'answers.*' => 'nullable|integer|between:0,3'])->validate();
            $values = ['version' => $expected + 1, 'body' => $body, 'files' => $this->evidence($actor, $enrolment, $files), 'answers' => $answers];
            if ($draft) {
                $draft->update($values);
            } else {
                $draft = WorkDraft::create($values + ['learning_block_id' => $block->id, 'lesson_index' => $lesson, 'member_id' => $actor->id]);
            }
            if (! $block->started_at) {
                $block->update(['started_at' => now()]);
            }

            return $draft;
        });
    }

    public function submit(User $actor, LearningBlock $block, int $lesson, int $expected, string $key): SubmissionAttempt
    {
        Validator::make(['key' => $key], ['key' => 'required|uuid'])->validate();

        return $this->locked($actor, $block->enrolment, function ($actor, $enrolment) use ($block, $lesson, $expected, $key) {
            $block = $block->fresh();
            $this->working($actor, $enrolment, $block, $lesson);
            $existing = SubmissionAttempt::where('member_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->learning_block_id === $block->id && $existing->lesson_index === $lesson && $existing->draft_version === $expected, 409);

                return $existing;
            }
            $draft = WorkDraft::where('learning_block_id', $block->id)->where('lesson_index', $lesson)->where('member_id', $actor->id)->first();
            abort_unless($draft && $draft->version === $expected, 409, 'Save the current draft before submitting.');
            abort_unless(trim($draft->body ?? '') !== '' || count($draft->files) || count(array_filter($draft->answers ?? [], fn ($answer) => $answer !== null && $answer !== '')), 422, 'Add your work or clean evidence before submitting.');
            $previous = SubmissionAttempt::where('learning_block_id', $block->id)->where('lesson_index', $lesson)->orderByDesc('attempt')->first();
            abort_if($previous && ! in_array($previous->status, ['changes_requested', 'withdrawn', 'technical_failure']), 409, 'The previous attempt is awaiting review or already approved.');
            $files = $this->evidence($actor, $enrolment, $draft->files);
            $quiz = ObjectiveQuiz::where('learning_block_id', $block->id)->where('lesson_index', $lesson)->first();
            $quizSnapshot = $quiz ? ['id' => $quiz->id, 'questions' => $quiz->questions, 'answer_key' => $quiz->answer_key] : null;
            $submission = SubmissionAttempt::create(['enrolment_id' => $enrolment->id, 'organisation_id' => $enrolment->organisation_id,
                'environment' => $enrolment->environment, 'learning_block_id' => $block->id, 'lesson_index' => $lesson,
                'member_id' => $actor->id, 'attempt' => ($previous?->attempt ?? 0) + 1, 'status' => 'submitted',
                'body' => $draft->body, 'files' => $files, 'rubric' => $block->rubric, 'draft_version' => $expected,
                'answers' => $draft->answers, 'quiz_snapshot' => $quizSnapshot,
                'content_hash' => hash('sha256', json_encode([$draft->body, $files, $block->rubric, $draft->answers, $quizSnapshot], JSON_THROW_ON_ERROR)), 'idempotency_key' => $key]);
            Audit::record($actor, 'submission.submitted', $submission->id, ['attempt' => $submission->attempt]);
            app(LearningNotices::class)->record($enrolment, $enrolment->coordinator_id, 'submitted:'.$submission->id, 'review_needed');

            return $submission;
        });
    }

    public function withdraw(User $actor, SubmissionAttempt $submission, int $expected, string $reason): void
    {
        $this->locked($actor, $submission->enrolment, function ($actor) use ($submission, $expected, $reason) {
            $submission = $submission->fresh();
            $this->reason($reason);
            abort_unless($actor->id === $submission->member_id, 403);
            abort_unless($submission->version === $expected && ! $submission->approved_grade_id && ! in_array($submission->status, ['approved', 'withdrawn']), 409);
            $submission->update(['status' => 'withdrawn', 'version' => $expected + 1]);
            Audit::record($actor, 'submission.withdrawn', $submission->id, ['reason' => $reason]);
        });
    }
}
