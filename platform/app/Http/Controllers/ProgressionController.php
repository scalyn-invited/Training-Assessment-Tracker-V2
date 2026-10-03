<?php

namespace App\Http\Controllers;

use App\Models\AdaptationProposal;
use App\Models\KpiSnapshot;
use App\Models\KpiSuggestion;
use App\Models\KpiVersion;
use App\Services\Access;
use App\Services\Delivery;
use App\Services\Kpis;
use App\Services\Onboarding;
use App\Services\Progression;
use Illuminate\Http\Request;

class ProgressionController
{
    public function show(Request $request, string $id, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->with('member')->findOrFail($id);
        app(Onboarding::class)->authorise($request->user(), $enrolment);
        $coordinator = $request->user()->id === $enrolment->coordinator_id;
        $metrics = KpiVersion::where('enrolment_id', $id)->where('effective_at', '<=', now())->get()->groupBy('number')->map(fn ($versions) => $versions->sortByDesc('version')->first());
        $snapshots = $metrics->mapWithKeys(fn ($metric) => [$metric->id => KpiSnapshot::where('kpi_version_id', $metric->id)->latest()->first()]);
        $proposals = AdaptationProposal::where('enrolment_id', $id)->when(! $coordinator, fn ($query) => $query->whereIn('status', ['approved', 'source_review_required']))->latest()->get();
        $suggestions = $coordinator ? KpiSuggestion::where('enrolment_id', $id)->latest()->limit(10)->get() : collect();

        return view('delivery.progress', compact('enrolment', 'coordinator', 'metrics', 'snapshots', 'proposals', 'suggestions'));
    }

    public function act(Request $request, string $id, Access $access, Progression $progression, Kpis $kpis)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        app(Onboarding::class)->coordinator($request->user(), $enrolment);
        $input = $request->validate(['action' => 'required|in:propose,approve,suggest,observe,revise,snapshot', 'reason' => 'nullable|string',
            'evidence_block' => 'nullable|integer|min:1|max:12', 'proposal' => 'nullable|uuid', 'base' => 'nullable|uuid', 'metric' => 'nullable|uuid',
            'value' => 'nullable|numeric', 'evidence' => 'nullable|string', 'definition' => 'nullable|array', 'effective' => 'nullable|date']);
        $actor = $request->user();
        $reason = $input['reason'] ?? '';
        if ($input['action'] === 'propose') {
            $progression->propose($actor, $enrolment, $input['evidence_block'] ?? 0, $reason);
        } elseif ($input['action'] === 'approve') {
            $progression->approve($actor, AdaptationProposal::where('enrolment_id', $id)->findOrFail($input['proposal'] ?? ''), $input['base'] ?? '', $reason);
        } elseif ($input['action'] === 'suggest') {
            $progression->suggest($actor, $enrolment);
        } else {
            $metric = KpiVersion::where('enrolment_id', $id)->findOrFail($input['metric'] ?? '');
            if ($input['action'] === 'observe') {
                abort_unless(isset($input['value']), 422);
                $kpis->observe($actor, $metric, (float) $input['value'], $input['evidence'] ?? '', $reason);
            } elseif ($input['action'] === 'revise') {
                $kpis->revise($actor, $metric, $input['definition'] ?? [], $input['effective'] ?? '', $reason);
            } else {
                app(Delivery::class)->locked($actor, $enrolment, fn () => $kpis->snapshot($actor, $metric));
            }
        }

        return back()->with('status', 'Progress action recorded. Queued AI results require a refresh and human review.');
    }
}
