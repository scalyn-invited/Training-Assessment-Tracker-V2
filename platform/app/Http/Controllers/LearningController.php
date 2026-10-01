<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeVersion;
use App\Services\Access;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Illuminate\Http\Request;

class LearningController
{
    public function onboarding(Request $request, string $id, Onboarding $service, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $service->authorise($request->user(), $enrolment);
        $draft = $service->latest($enrolment);
        $calendar = $service->calendar($enrolment);
        $step = $request->query('step', $draft?->last_step ?? 'profile');
        abort_unless(isset(Onboarding::STEPS[$step]) || $step === 'review', 404);
        $data = $draft?->data ?? [];
        $coordinator = $request->user()->role === 'coordinator' && $request->user()->id !== $enrolment->member_id;
        $editable = $coordinator || in_array($step, ['profile', 'assessment', 'schedule']);
        $plan = app(LearningPlans::class)->latest($enrolment);

        return view('learning.onboarding', compact('enrolment', 'draft', 'calendar', 'step', 'data', 'coordinator', 'editable', 'plan'));
    }

    public function save(Request $request, string $id, string $step, Onboarding $service, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $request->validate(['expected_version' => 'required|integer|min:0']);
        $input = $request->input('data', []);
        if ($step === 'schedule') {
            foreach (['holidays', 'absences'] as $key) {
                $input[$key] = preg_split('/[\\s,]+/', trim($input[$key] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            }
        }
        $draft = $service->save($request->user(), $enrolment, $step, $input, (int) $request->expected_version, $request->boolean('confirm_assessment'));
        if ($request->expectsJson()) {
            return response()->json(['version' => $draft->version, 'message' => 'Draft saved. This is not an approval.', 'confirmed' => ! empty($draft->data['assessment']['confirmed_by'])]);
        }

        return back()->with('status', 'Draft saved. This is not an approval.');
    }

    public function calendar(Request $request, string $id, Onboarding $service, Access $access)
    {
        $request->validate(['expected_version' => 'required|integer|min:0', 'onboarding_version' => 'required|integer|min:1', 'reason' => 'required|string']);
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $service->confirmCalendar($request->user(), $enrolment, (int) $request->expected_version, (int) $request->onboarding_version, $request->reason);

        return $this->saved($request, route('onboarding.show', ['id' => $id, 'step' => 'review']), 'Shared capacity calendar confirmed.');
    }

    public function create(Request $request, string $id, LearningPlans $service, Access $access)
    {
        $request->validate(['expected_version' => 'required|integer|min:0', 'onboarding_version' => 'required|integer|min:1', 'reason' => 'required|string']);
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $service->create($request->user(), $enrolment, (int) $request->expected_version, (int) $request->onboarding_version, $request->reason);

        return $this->saved($request, route('plans.show', $id), 'Draft created from the reviewed inputs. Complete every lesson before requesting approval.');
    }

    public function show(Request $request, string $id, LearningPlans $service, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $versions = ProgrammeVersion::where('enrolment_id', $id)->orderByDesc('version')->get();
        $plan = $request->filled('version') ? $versions->firstWhere('version', (int) $request->version) : $versions->first();
        abort_unless($plan, 404);
        $blockIndex = max(0, (int) $request->query('block', 0));
        abort_unless(isset($plan->content['blocks'][$blockIndex]), 404);
        $block = $plan->content['blocks'][$blockIndex];
        $editable = $plan->id === $versions->first()->id && $request->user()->role === 'coordinator'
            && $request->user()->sensitive_access && $request->user()->id === $enrolment->coordinator_id && $request->user()->id !== $enrolment->member_id;
        $previous = $versions->first(fn ($version) => $version->version < $plan->version);

        return view('learning.plan', compact('enrolment', 'plan', 'versions', 'blockIndex', 'block', 'editable', 'previous'));
    }

    public function edit(Request $request, string $id, LearningPlans $service, Access $access)
    {
        $request->validate(['expected_version' => 'required|integer|min:1', 'block' => 'required|integer|min:0', 'reason' => 'required|string', 'data' => 'required|array']);
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $service->edit($request->user(), $enrolment, (int) $request->expected_version, (int) $request->block, $request->input('data'), $request->reason);

        return $this->saved($request, route('plans.show', ['id' => $id, 'block' => $request->block]), 'New draft version saved; review must be requested again.');
    }

    public function review(Request $request, string $id, LearningPlans $service, Access $access)
    {
        $request->validate(['expected_version' => 'required|integer|min:1', 'action' => 'required|in:submit,approve', 'reason' => 'nullable|string']);
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $service->review($request->user(), $enrolment, (int) $request->expected_version, $request->action === 'approve', $request->reason ?? '');

        return $this->saved($request, route('plans.show', $id), $request->action === 'approve' ? 'Exact plan version approved. Enrolment is ready; training has not started.' : 'All blocks validated. This exact version is awaiting coordinator approval.');
    }

    private function saved(Request $request, string $url, string $message)
    {
        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);

            return response()->json(['redirect' => $url]);
        }

        return redirect($url)->with('status', $message);
    }
}
