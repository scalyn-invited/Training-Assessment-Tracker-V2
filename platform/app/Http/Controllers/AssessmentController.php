<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAppeal;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentReview;
use App\Models\DocumentExtraction;
use App\Models\EvidenceFile;
use App\Models\User;
use App\Services\Access;
use App\Services\AssessmentAnalysis;
use App\Services\DocumentExtractionService;
use App\Services\InitialAssessments;
use App\Services\Tenancy\ClientAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssessmentController
{
    public function index(Request $r, InitialAssessments $s)
    {
        $actor = $r->user();
        $author = app(Access::class)->coordinator($actor);
        $definitions = $author ? $s->definitions($actor)->latest()->paginate(15, ['*'], 'definitions') : collect();
        $assignments = $s->assignments($actor)->with('definition', 'enrolment.member')->latest()->paginate(20);
        $enrolments = $author ? app(Access::class)->manageableEnrolments($actor)->with('member')->get() : collect();
        $admin = app(ClientAccess::class)->clientRole($actor, app(Access::class)->client($actor) ?? '', 'admin');
        // Admins may route an appeal; this projection contains no response, score, or private reason.
        $appeals = $admin ? AssessmentAppeal::where('status', 'open')->whereHas('assignment.enrolment', fn ($q) => $q->where('organisation_id', app(Access::class)->client($actor))->where('environment', config('training.environment')))->get(['id', 'assignment_id', 'reviewer_id']) : collect();
        $reviewers = $admin ? User::whereIn('id', app(ClientAccess::class)->activeMemberships()->where('cm.organisation_id', app(Access::class)->client($actor))->select('cm.user_id'))->get(['id', 'name']) : collect();

        return view('assessments.index', compact('definitions', 'assignments', 'enrolments', 'author', 'appeals', 'reviewers'));
    }

    public function edit(Request $r, InitialAssessments $s, ?string $id = null)
    {
        $s->author($r->user(), app(Access::class)->client($r->user()) ?? '');
        $definition = $id ? $s->definitions($r->user())->findOrFail($id) : null;

        return view('assessments.definition', compact('definition'));
    }

    public function definition(Request $r, InitialAssessments $s)
    {
        $r->validate(['action' => 'required|in:save,publish,assign', 'id' => 'nullable|uuid', 'expected_version' => 'required|integer|min:0']);
        $d = $r->id ? $s->definitions($r->user())->findOrFail($r->id) : null;
        if ($r->action === 'publish') {
            abort_unless($d, 404);
            $s->publish($r->user(), $d, (int) $r->expected_version);
        } elseif ($r->action === 'assign') {
            abort_unless($d, 404);
            $a = $s->assign($r->user(), $d, app(Access::class)->manageableEnrolments($r->user())->findOrFail($r->enrolment_id));

            return redirect()->route('assessments.show', $a->id)->with('status', 'Assessment assigned.');
        } else {
            // Empty optional rows are omitted, while supplied rows are fully validated by the domain service.
            $questions = array_values(array_filter($r->input('questions', []), fn ($q) => trim($q['prompt'] ?? '') !== ''));
            $criteria = array_values(array_filter($r->input('criteria', []), fn ($c) => trim($c['label'] ?? '') !== ''));
            $d = $s->definition($r->user(), ['title' => $r->title, 'questions' => $questions, 'rubric' => ['criteria' => $criteria, 'threshold' => $r->threshold]], $d, (int) $r->expected_version);

            return redirect()->route('assessments.edit', $d->id)->with('status', 'Draft saved. Publish it before assigning members.');
        }

        return redirect()->route('assessments.index')->with('status', 'Assessment definition published.');
    }

    public function show(Request $r, string $id, InitialAssessments $s)
    {
        $a = $s->assignments($r->user())->with('definition', 'enrolment.member')->findOrFail($id);
        $s->visible($r->user(), $a);
        $member = $r->user()->id === $a->enrolment->member_id;
        $attempts = AssessmentAttempt::where('assignment_id', $id)->orderByDesc('number')->get();
        $attempt = $attempts->first();
        $reviews = ! $member && $attempt ? AssessmentReview::where('attempt_id', $attempt->id)->orderByDesc('sequence')->get() : collect();
        $approved = $s->approved($r->user(), $a);
        $files = EvidenceFile::where('enrolment_id', $a->enrolment_id)->get()->filter(fn ($f) => $s->fileAccess($r->user(), $f, $a));
        $evidence = ! $member && $attempt ? $s->evidence($attempt) : [];
        $extractions = ! $member && $attempt ? DocumentExtraction::where('assessment_attempt_id', $attempt->id)->latest()->get() : collect();
        $appeal = AssessmentAppeal::where('assignment_id', $id)->where('status', 'open')->first();
        $canReview = false;
        if (! $member) {
            try {
                $s->reviewer($r->user(), $a);
                $canReview = true;
            } catch (HttpException) {
            }
        }
        if ($r->expectsJson()) {
            return response()->json(['id' => $a->id, 'status' => $a->status, 'version' => $a->version, 'approved' => $approved]);
        }

        return view('assessments.show', compact('a', 'member', 'attempts', 'attempt', 'reviews', 'approved', 'files', 'evidence', 'extractions', 'appeal', 'canReview'));
    }

    public function act(Request $r, string $id, InitialAssessments $s)
    {
        $a = $s->assignments($r->user())->findOrFail($id);
        $r->validate(['action' => 'required|in:save,submit,approve,reject,analysis,confirm,extract,appeal,uphold', 'expected_version' => 'required|integer|min:1']);
        $attempt = AssessmentAttempt::where('assignment_id', $id)->orderByDesc('number')->first();
        $v = (int) $r->expected_version;
        $reason = $r->input('reason', '');
        switch ($r->action) {
            case 'save': $s->save($r->user(), $a, $v, $r->only('responses', 'context', 'files'));
                break;
            case 'submit': $s->submit($r->user(), $a, $v, (string) $r->idempotency_key);
                break;
            case 'appeal': $s->appeal($r->user(), $a, $v, $reason);
                break;
            case 'uphold': $s->uphold($r->user(), $a, $v, $reason);
                break;
            default:
                abort_unless($attempt, 409);
                if ($r->action === 'analysis') {
                    app(AssessmentAnalysis::class)->request($r->user(), $attempt, $v, $reason);
                } elseif ($r->action === 'extract') {
                    app(DocumentExtractionService::class)->requestAssessment($r->user(), $attempt, EvidenceFile::findOrFail($r->file_id));
                } elseif ($r->action === 'confirm') {
                    $s->confirmFile($r->user(), $attempt, $v, (string) $r->file_id, (string) $r->text, $reason);
                } else {
                    $rows = [];
                    foreach ($r->input('scores', []) as $key => $row) {
                        $rows[] = ['id' => $key, 'score' => $row['score'] ?? null, 'evidence_ids' => $row['evidence_ids'] ?? [], 'feedback' => $row['feedback'] ?? '', 'insufficient_evidence' => ! empty($row['insufficient'])];
                    }
                    $s->decide($r->user(), $attempt, $v, $r->action === 'approve' ? 'approved' : 'rejected', $reason,
                        ['rubric_id' => $attempt->snapshot['definition_id'], 'rubric_version' => $attempt->snapshot['revision'], 'criteria' => $rows], $r->input('source_review_id'));
                }
        }
        if ($r->expectsJson()) {
            return response()->json(['id' => $a->id, 'version' => $a->fresh()->version, 'status' => $a->fresh()->status]);
        }
        // Alternate access ends immediately after resolving an appeal.
        if (! app(Access::class)->canDetails($r->user(), $a->enrolment) && ! $s->alternate($r->user(), $a)) {
            return redirect()->route('assessments.index')->with('status', 'Review recorded. Case access is now closed.');
        }

        return redirect()->route('assessments.show', $a->id)->with('status', 'Assessment action saved.');
    }

    public function routeAppeal(Request $r, InitialAssessments $s)
    {
        $r->validate(['appeal_id' => 'required|uuid', 'reviewer_id' => 'required|uuid', 'reason' => 'required|string']);
        $s->assignAppeal($r->user(), AssessmentAppeal::findOrFail($r->appeal_id), User::findOrFail($r->reviewer_id), $r->reason);

        return back()->with('status', 'Alternate reviewer assigned.');
    }
}
