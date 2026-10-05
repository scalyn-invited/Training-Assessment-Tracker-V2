<?php

namespace App\Services;

use App\Models\LearningBlock;
use App\Models\ObjectiveQuiz;
use App\Models\SubmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ObjectiveQuizzes
{
    public function copy(LearningBlock $base, LearningBlock $next): void
    {
        foreach (ObjectiveQuiz::where('learning_block_id', $base->id)->get() as $quiz) {
            ObjectiveQuiz::create(['learning_block_id' => $next->id, 'lesson_index' => $quiz->lesson_index, 'questions' => $quiz->questions, 'answer_key' => $quiz->answer_key, 'actor_id' => $next->actor_id, 'reason' => 'Preserve reviewed quiz across explicit content or schedule version.']);
        }
    }

    public function create(User $actor, LearningBlock $block, int $lesson, array $questions, string $reason): void
    {
        app(Delivery::class)->locked($actor, $block->enrolment, function ($actor, $enrolment) use ($block, $lesson, $questions, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            $block = $block->fresh();
            abort_unless($enrolment->status === 'active' && app(Progression::class)->eligible($block) && isset($block->content['lessons'][$lesson]), 409, 'Quiz must be configured before work or freeze.');
            abort_if(ObjectiveQuiz::where('learning_block_id', $block->id)->where('lesson_index', $lesson)->exists(), 409, 'Quiz version is already published.');
            Validator::make(['questions' => $questions], ['questions' => 'required|array|list|size:'.count($block->rubric['criteria']), 'questions.*' => 'required|array:prompt,options,correct',
                'questions.*.prompt' => 'required|string|min:10|max:2000', 'questions.*.options' => 'required|array|list|between:2,4', 'questions.*.options.*' => 'required|string|max:1000', 'questions.*.correct' => 'required|integer|min:0|max:3'])->validate();
            $public = [];
            $key = [];
            foreach ($questions as $index => $question) {
                abort_unless(isset($question['options'][$question['correct']]), 422);
                $public[] = ['id' => (string) $index, 'prompt' => $question['prompt'], 'options' => $question['options'], 'criterion_id' => $block->rubric['criteria'][$index]['id']];
                $key[(string) $index] = (int) $question['correct'];
            }
            $quiz = ObjectiveQuiz::create(['learning_block_id' => $block->id, 'lesson_index' => $lesson, 'questions' => $public, 'answer_key' => $key, 'actor_id' => $actor->id, 'reason' => $reason]);
            Audit::record($actor, 'quiz.published', $quiz->id, ['reason' => $reason]);
        });
    }

    public function result(SubmissionAttempt $submission): array
    {
        $quiz = $submission->quiz_snapshot;
        $criteria = [];
        foreach ($quiz['questions'] as $question) {
            $id = $question['id'];
            $answer = $submission->answers[$id] ?? null;
            $present = $answer !== null && $answer !== '';
            $criteria[] = ['id' => $question['criterion_id'], 'score' => $present && (int) $answer === $quiz['answer_key'][$id] ? 100 : 0,
                'evidence_ids' => [$submission->id.':text'], 'feedback' => $present ? 'Deterministic answer-key comparison; coordinator review is still required.' : 'No answer supplied. Request evidence; do not approve an automatic zero.',
                'insufficient_evidence' => ! $present];
        }

        return ['rubric_id' => $submission->rubric['id'], 'rubric_version' => $submission->rubric['version'], 'criteria' => $criteria];
    }
}
