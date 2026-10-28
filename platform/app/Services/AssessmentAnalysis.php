<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentReview;
use App\Models\User;
use App\Services\Ai\TaskGateway;

class AssessmentAnalysis
{
    public function authorise(User $actor, AssessmentReview $review): void
    {
        $a = $review->attempt->assignment;
        app(InitialAssessments::class)->reviewer($actor, $a);
        abort_unless($review->kind === 'analysis' && $review->status === 'queued'
            && $a->status === 'queued'
            && AssessmentAttempt::where('assignment_id', $a->id)->orderByDesc('number')->value('id') === $review->attempt_id, 409);
        // M3 is qualified with synthetic mocks only. Live enablement is a separate approval.
        abort_unless($a->enrolment->environment === 'test' && $a->enrolment->member->is_synthetic && app()->environment(['local', 'testing']), 422, 'AI assessment analysis is not configured. Manual review is available.');
    }

    public function request(User $actor, AssessmentAttempt $attempt, int $expected, string $reason): AssessmentReview
    {
        $service = app(InitialAssessments::class);

        return $service->locked($actor, $attempt->assignment, function ($actor, $a) use ($service, $attempt, $expected, $reason) {
            $service->reviewer($actor, $a);
            app(Delivery::class)->reason($reason);
            abort_unless($a->version === $expected && in_array($a->status, ['submitted', 'review_pending', 'technical_failure', 'approved'])
                && AssessmentAttempt::where('assignment_id', $a->id)->orderByDesc('number')->value('id') === $attempt->id, 409);
            $review = AssessmentReview::create(['attempt_id' => $attempt->id, 'actor_id' => $actor->id, 'sequence' => (AssessmentReview::where('attempt_id', $attempt->id)->max('sequence') ?? 0) + 1, 'kind' => 'analysis', 'status' => 'queued', 'reason' => $reason]);
            $a->update(['status' => 'queued', 'version' => $expected + 1]);
            $this->authorise($actor, $review->fresh());
            $evidence = $service->evidence($attempt);
            abort_unless(count($evidence), 422, 'Add confirmed evidence before requesting analysis.');
            $input = ['task' => 'assess_initial', 'attempt_id' => $attempt->id, 'questions' => $attempt->snapshot['questions'], 'context' => $attempt->snapshot['context'] ?? '',
                'rubric' => $attempt->snapshot['rubric'] + ['id' => $attempt->snapshot['definition_id'], 'version' => $attempt->snapshot['revision']],
                'evidence' => $evidence, 'unread_files' => [], 'shape' => ['rubric_id', 'rubric_version', 'criteria']];
            app(TaskGateway::class)->request($actor, $a->enrolment, 'assess_initial', $review->id, null, $input, InitialAssessments::PROMPT);

            return $review;
        });
    }

    public function receive(AssessmentReview $review, array $output): void
    {
        $actor = User::findOrFail($review->actor_id);
        app(InitialAssessments::class)->locked($actor, $review->attempt->assignment, function ($actor, $a) use ($review, $output) {
            $this->authorise($actor, $review->fresh());
            $score = app(InitialAssessments::class)->score($review->attempt, $output);
            $review->update(['status' => 'provisional', 'result' => $score]);
            $a->update(['status' => 'review_pending', 'version' => $a->version + 1]);
            app(LearningNotices::class)->record($a->enrolment, $actor->id, 'assessment-analysis:'.$review->id, 'assessment_review');
        });
    }

    public function fail(string $id): void
    {
        $r = AssessmentReview::findOrFail($id);
        if ($r->status === 'queued') {
            $r->update(['status' => 'technical_failure']);
            $a = $r->attempt->assignment;
            $a->update(['status' => 'technical_failure', 'version' => $a->version + 1]);
        }
    }
}
