<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\CalendarChange;
use App\Models\Enrolment;
use App\Models\GradeAttempt;
use App\Models\GradeDecision;
use App\Models\LearningBlock;
use App\Models\ObjectiveQuiz;
use App\Models\SubmissionAttempt;
use App\Models\WorkDraft;
use App\Services\Access;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningLifecycle;
use App\Services\ObjectiveQuizzes;
use App\Services\Onboarding;
use App\Services\Progression;
use Illuminate\Http\Request;

class DeliveryController
{
    public function show(Request $request, string $id, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->with('member')->findOrFail($id);
        $blocks = LearningBlock::where('enrolment_id', $id)->where('state', 'current')->orderBy('number')->get();
        $submissions = SubmissionAttempt::where('enrolment_id', $id)
            ->when($request->user()->role === 'coordinator' && $request->user()->id !== $enrolment->coordinator_id,
                fn ($query) => $query->whereIn('id', Appeal::where('reviewer_id', $request->user()->id)->where('status', 'open')->select('submission_attempt_id')))
            ->orderByDesc('created_at')->get();
        $coordinator = $request->user()->role === 'coordinator' && $request->user()->sensitive_access && $request->user()->id === $enrolment->coordinator_id;
        $changes = $coordinator ? CalendarChange::where('enrolment_id', $id)->where('status', 'preview')->latest()->limit(3)->get() : collect();

        return view('delivery.show', compact('enrolment', 'blocks', 'submissions', 'coordinator', 'changes'));
    }

    public function lifecycle(Request $request, string $id, Access $access, LearningLifecycle $lifecycle)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $input = $request->validate(['action' => 'required|in:pause,preview,resume,complete', 'expected_version' => 'required|integer',
            'reason' => 'nullable|string', 'weeks' => 'nullable|integer|between:0,52', 'change' => 'nullable|uuid']);
        $actor = $request->user();
        $reason = $input['reason'] ?? '';
        match ($input['action']) {
            'pause' => $lifecycle->pause($actor, $enrolment, $input['expected_version'], $reason),
            'complete' => $lifecycle->complete($actor, $enrolment, $input['expected_version'], $reason),
            'preview' => $lifecycle->preview($actor, $enrolment, $input['expected_version'], $input['weeks'] ?? 0, $reason),
            'resume' => $lifecycle->resume($actor, $enrolment, CalendarChange::where('enrolment_id', $id)->findOrFail($input['change'] ?? '')),
        };

        return back()->with('status', 'Programme action recorded. Review any schedule preview before confirming resume.');
    }

    public function activate(Request $request, string $id, Access $access, Delivery $delivery)
    {
        $input = $request->validate(['expected_version' => 'required|integer', 'reason' => 'required|string']);
        $delivery->activate($request->user(), $access->enrolments($request->user())->findOrFail($id), $input['expected_version'], $input['reason']);

        return redirect()->route('delivery.show', $id)->with('status', 'Approved programme activated. Lessons are available; future days remain preview only.');
    }

    private function block(Request $request, string $id, Access $access): LearningBlock
    {
        return LearningBlock::whereIn('enrolment_id', $access->enrolments($request->user())->select('enrolments.id'))->findOrFail($id);
    }

    public function lesson(Request $request, string $id, int $index, Access $access)
    {
        $block = $this->block($request, $id, $access);
        abort_unless(isset($block->content['lessons'][$index]), 404);
        $enrolment = $block->enrolment;
        $lesson = $block->content['lessons'][$index];
        $draft = WorkDraft::where('learning_block_id', $id)->where('lesson_index', $index)->where('member_id', $request->user()->id)->first();
        $attempts = SubmissionAttempt::where('learning_block_id', $id)->where('lesson_index', $index)
            ->when($request->user()->role === 'coordinator' && $request->user()->id !== $enrolment->coordinator_id,
                fn ($query) => $query->whereIn('id', Appeal::where('reviewer_id', $request->user()->id)->where('status', 'open')->select('submission_attempt_id')))
            ->orderByDesc('attempt')->get();
        $editable = $request->user()->id === $enrolment->member_id && $enrolment->status === 'active' && $block->state === 'current'
            && ($block->early_start || $lesson['date'] <= now($enrolment->timezone)->toDateString());
        $files = $enrolment->files()->where('scan_status', 'clean')->get()->filter(fn ($file) => $access->canFile($request->user(), $file));
        $quiz = ObjectiveQuiz::where('learning_block_id', $id)->where('lesson_index', $index)->first();
        $canConfigureQuiz = $request->user()->id === $enrolment->coordinator_id && ! $quiz && app(Progression::class)->eligible($block);

        return view('delivery.lesson', compact('block', 'index', 'enrolment', 'lesson', 'draft', 'attempts', 'editable', 'files', 'quiz', 'canConfigureQuiz'));
    }

    public function save(Request $request, string $id, int $index, Access $access, Delivery $delivery)
    {
        $input = $request->validate(['expected_version' => 'required|integer|min:0', 'body' => 'nullable|string|max:30000', 'files' => 'nullable|array', 'answers' => 'nullable|array']);
        $draft = $delivery->save($request->user(), $this->block($request, $id, $access), $index, $input['expected_version'], $input['body'] ?? '', $input['files'] ?? [], $input['answers'] ?? []);
        if ($request->expectsJson()) {
            return response()->json(['version' => $draft->version, 'message' => 'Draft saved. Your work has not been submitted.']);
        }

        return back()->with('status', 'Draft saved. Your work has not been submitted.');
    }

    public function submit(Request $request, string $id, int $index, Access $access, Delivery $delivery)
    {
        $input = $request->validate(['expected_version' => 'required|integer|min:1', 'idempotency_key' => 'required|uuid']);
        $submission = $delivery->submit($request->user(), $this->block($request, $id, $access), $index, $input['expected_version'], $input['idempotency_key']);

        return redirect()->route('submission.show', $submission->id)->with('status', 'Submitted successfully. Receipt: '.$submission->id);
    }

    public function quiz(Request $request, string $id, int $index, Access $access)
    {
        $input = $request->validate(['questions' => 'required|array', 'questions.*.options' => 'required|string', 'reason' => 'required|string']);
        $questions = $request->input('questions');
        foreach ($questions as &$question) {
            $question['options'] = preg_split('/\R/', trim($question['options']));
        }
        unset($question);
        app(ObjectiveQuizzes::class)->create($request->user(), $this->block($request, $id, $access), $index, $questions, $input['reason']);

        return back()->with('status', 'Objective quiz published. The answer key stays private and submitted attempts pin this version.');
    }

    public function early(Request $request, string $id, Access $access, Delivery $delivery)
    {
        $request->validate(['reason' => 'required|string']);
        $delivery->earlyStart($request->user(), $this->block($request, $id, $access), $request->reason);

        return back()->with('status', 'Early start authorised. This block version is now locked against adaptation.');
    }

    private function submission(Request $request, string $id, Access $access): SubmissionAttempt
    {
        $submission = SubmissionAttempt::whereIn('enrolment_id', $access->enrolments($request->user())->select('enrolments.id'))->findOrFail($id);
        app(Onboarding::class)->authorise($request->user(), $submission->enrolment, true);
        abort_unless($request->user()->id === $submission->member_id || $request->user()->id === $submission->enrolment->coordinator_id
            || Appeal::where('submission_attempt_id', $id)->where('reviewer_id', $request->user()->id)->where('status', 'open')->exists(), 404);

        return $submission;
    }

    public function receipt(Request $request, string $id, Access $access)
    {
        $submission = $this->submission($request, $id, $access);
        $enrolment = $submission->enrolment;
        $coordinator = $request->user()->role === 'coordinator' && $request->user()->id !== $enrolment->member_id;
        $grades = $coordinator ? $submission->grades()->with('decision')->orderByDesc('version')->get() : collect();
        $approved = $submission->approved_grade_id ? GradeAttempt::with('decision')->find($submission->approved_grade_id) : null;
        $appeals = Appeal::where('submission_attempt_id', $id)->get();
        $changeDecision = GradeDecision::whereIn('grade_attempt_id', $submission->grades()->select('id'))->where('outcome', 'changes_requested')->latest()->first();

        return view('delivery.receipt', compact('submission', 'enrolment', 'coordinator', 'grades', 'approved', 'appeals', 'changeDecision'));
    }

    public function review(Request $request, string $id, Access $access, Grading $grading, Delivery $delivery)
    {
        $submission = $this->submission($request, $id, $access);
        $input = $request->validate(['action' => 'required|in:grade,approve,changes,manual,withdraw,appeal,uphold', 'expected_version' => 'required|integer', 'reason' => 'required|string|min:8|max:2000']);
        switch ($input['action']) {
            case 'grade': $grading->request($request->user(), $submission, $input['expected_version'], $input['reason']);
                break;
            case 'withdraw': $delivery->withdraw($request->user(), $submission, $input['expected_version'], $input['reason']);
                break;
            case 'appeal': $grading->appeal($request->user(), $submission, $input['reason']);
                break;
            case 'uphold': $grading->uphold($request->user(), Appeal::where('submission_attempt_id', $id)->where('status', 'open')->firstOrFail(), $input['reason']);
                break;
            case 'manual':
                $request->validate(['scores' => 'required|array', 'outcome' => 'required|in:approved,changes_requested']);
                $result = ['rubric_id' => $submission->rubric['id'], 'rubric_version' => $submission->rubric['version'], 'criteria' => []];
                foreach ($submission->rubric['criteria'] as $index => $criterion) {
                    $score = $request->input('scores.'.$index, []);
                    $result['criteria'][] = ['id' => $criterion['id'], 'score' => $score['score'] ?? null, 'feedback' => $score['feedback'] ?? '',
                        'evidence_ids' => [$submission->id.':text', ...$submission->files], 'insufficient_evidence' => ! empty($score['insufficient'])];
                }
                $grading->manual($request->user(), $submission, $input['expected_version'], $request->outcome, $input['reason'], $result);
                break;
            default:
                $grade = $submission->grades()->whereKey($request->input('grade_id'))->firstOrFail();
                $grading->decide($request->user(), $grade, $input['expected_version'], $input['action'] === 'approve' ? 'approved' : 'changes_requested', $input['reason']);
        }

        if ($request->user()->id !== $submission->member_id && $request->user()->id !== $submission->enrolment->coordinator_id) {
            return redirect()->route('reviews.show')->with('status', 'Independent review recorded. Case access closes when the appeal resolves.');
        }

        return redirect()->route('submission.show', $id)->with('status', 'Action recorded. Previous attempts and decisions are preserved.');
    }

    public function reviews(Request $request, Access $access)
    {
        $actor = $request->user();
        abort_unless($actor->role === 'coordinator' && $actor->sensitive_access, 403);
        $submissions = SubmissionAttempt::whereIn('enrolment_id', $access->enrolments($actor)->select('enrolments.id'))
            ->where(function ($scope) use ($actor) {
                $scope->whereIn('enrolment_id', Enrolment::where('coordinator_id', $actor->id)->select('id'))
                    ->orWhereIn('id', Appeal::where('reviewer_id', $actor->id)->where('status', 'open')->select('submission_attempt_id'));
            })
            ->where(fn ($query) => $query->whereIn('status', ['submitted', 'queued', 'review_pending', 'technical_failure'])
                ->orWhereIn('id', Appeal::where('reviewer_id', $actor->id)->where('status', 'open')->select('submission_attempt_id')))
            ->with('enrolment.member')->oldest()->paginate(20);

        return view('delivery.reviews', compact('submissions'));
    }
}
