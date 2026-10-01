<?php

namespace App\Http\Controllers;

use App\Models\AiAttempt;
use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\ProgrammeVersion;
use App\Services\Access;
use App\Services\Ai\Generation;
use App\Services\Ai\Registry;
use App\Services\Onboarding;
use Illuminate\Http\Request;

class AiController
{
    public function request(Request $request, string $id, Access $access, Generation $generation)
    {
        $input = $request->validate(['expected_version' => 'required|integer|min:1', 'idempotency_key' => 'required|uuid', 'feedback' => 'nullable|string|max:4000']);
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $run = $generation->request($request->user(), $enrolment, $input['expected_version'], $input['idempotency_key'], $input['feedback'] ?? '');

        return redirect()->route('ai.show', $run->id);
    }

    public function show(Request $request, string $id, Access $access, Onboarding $onboarding)
    {
        $run = AiRun::whereIn('enrolment_id', $access->enrolments($request->user())->select('enrolments.id'))->findOrFail($id);
        $enrolment = $access->enrolments($request->user())->findOrFail($run->enrolment_id);
        $onboarding->coordinator($request->user(), $enrolment);
        $blocks = AiBlock::where('ai_run_id', $run->id)->orderBy('block_index')->get();
        $result = $run->result_version_id ? ProgrammeVersion::findOrFail($run->result_version_id) : null;
        $attempts = AiAttempt::whereIn('ai_block_id', $blocks->pluck('id'))->orderBy('created_at')->get();
        if ($request->expectsJson()) {
            return response()->json(['status' => $run->status, 'message' => $run->message,
                'complete' => $blocks->where('status', 'complete')->count(), 'total' => $blocks->count(),
                'reserved' => $run->reserved, 'spent' => $run->spent,
                'attempts' => $attempts->map(fn ($attempt) => $attempt->only(['provider', 'model', 'status', 'input_tokens', 'output_tokens', 'external_id'])),
                'result_url' => $result ? route('plans.show', ['id' => $enrolment->id, 'version' => $result->version]) : null]);
        }

        return view('ai.run', compact('run', 'enrolment', 'blocks', 'attempts', 'result'));
    }

    public function settings(Request $request, Registry $registry)
    {
        $actor = $request->user();
        $registry->admin($actor);
        $policies = [];
        foreach (Registry::TASKS as $task) {
            $policies[$task] = $registry->policy($actor->organisation_id, $actor->environment, $task);
        }
        $providers = config('ai.providers');
        $ambiguous = AiRun::where('organisation_id', $actor->organisation_id)->where('environment', $actor->environment)->where('status', 'ambiguous')->get();

        return view('ai.settings', compact('policies', 'providers', 'ambiguous'));
    }

    public function policy(Request $request, Registry $registry)
    {
        $input = $request->validate(['task' => 'required|string', 'expected_version' => 'required|integer|min:0', 'primary' => 'required|string', 'fallback' => 'nullable|string']);
        $registry->save($request->user(), $input['task'], $input['expected_version'], $input['primary'], $input['fallback'] ?? null);

        return back()->with('status', 'New routing version saved. Existing runs retain their pinned configuration.');
    }

    public function reconcile(Request $request, string $id, Generation $generation, Registry $registry)
    {
        $registry->admin($request->user());
        $input = $request->validate(['total' => 'required|integer|min:0', 'reason' => 'required|string|min:8|max:2000']);
        $run = AiRun::where('organisation_id', $request->user()->organisation_id)->where('environment', $request->user()->environment)->findOrFail($id);
        $generation->reconcile($request->user(), $run, $input['total'], $input['reason']);

        return back()->with('status', 'Charge reconciliation recorded. Generation was not retried.');
    }
}
