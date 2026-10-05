<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\GradeDecision;
use App\Models\KpiObservation;
use App\Models\KpiSnapshot;
use App\Models\KpiVersion;
use App\Models\ProgrammeVersion;
use App\Models\SubmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Kpis
{
    public function seed(User $actor, Enrolment $enrolment, ProgrammeVersion $plan): void
    {
        foreach ($plan->onboarding->data['measures']['kpis'] as $index => $kpi) {
            KpiVersion::create(['enrolment_id' => $enrolment->id, 'number' => $index + 1, 'version' => 1,
                'definition' => $kpi + ['formula' => $kpi['unit'] === 'percent' ? 'approved_grade_mean' : 'manual_mean', 'direction' => 'higher', 'sample_minimum' => 3, 'evidence_type' => $kpi['unit'] === 'percent' ? 'demonstrated_competence' : 'workplace_outcome', 'owner' => $actor->id],
                'effective_at' => now(), 'actor_id' => $actor->id, 'reason' => 'Initial planning measure; only comparable approved grades count.']);
        }
    }

    public function current(Enrolment $enrolment, int $number): ?KpiVersion
    {
        return KpiVersion::where('enrolment_id', $enrolment->id)->where('number', $number)->where('effective_at', '<=', now())->orderByDesc('version')->first();
    }

    public function revise(User $actor, KpiVersion $previous, array $definition, string $effective, string $reason): KpiVersion
    {
        return app(Delivery::class)->locked($actor, $previous->enrolment, function ($actor, $enrolment) use ($previous, $definition, $effective, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            $latest = KpiVersion::where('enrolment_id', $enrolment->id)->where('number', $previous->number)->orderByDesc('version')->first();
            abort_unless($latest->id === $previous->id, 409, 'KPI definition changed. Reload the latest version.');
            Validator::make($definition + ['effective' => $effective], [
                'name' => 'required|string|max:200', 'rationale' => 'required|string|max:1000', 'unit' => 'required|string|max:100',
                'target' => 'required|numeric', 'baseline' => 'required|string|max:200', 'direction' => ['required', Rule::in(['higher', 'lower', 'band'])],
                'upper_target' => 'nullable|numeric|gte:target', 'formula' => ['required', Rule::in(['approved_grade_mean', 'manual_mean'])],
                'sample_minimum' => 'required|integer|between:1,100', 'evidence_type' => ['required', Rule::in(['demonstrated_competence', 'workplace_outcome'])],
                'effective' => 'required|date|after_or_equal:now',
            ])->validate();
            abort_if($definition['direction'] === 'band' && ! isset($definition['upper_target']), 422, 'A band needs an upper target.');
            abort_if($definition['formula'] === 'approved_grade_mean' && ($definition['evidence_type'] !== 'demonstrated_competence' || $definition['unit'] !== 'percent'), 422, 'Grade means measure demonstrated competence in percent, not workplace outcomes.');
            $allowed = array_intersect_key($definition, array_flip(['name', 'rationale', 'unit', 'target', 'baseline', 'direction', 'upper_target', 'formula', 'sample_minimum', 'evidence_type']));
            $version = KpiVersion::create(['enrolment_id' => $enrolment->id, 'number' => $previous->number, 'version' => $previous->version + 1,
                'definition' => $allowed + ['owner' => $actor->id], 'effective_at' => $effective, 'actor_id' => $actor->id, 'reason' => $reason]);
            Audit::record($actor, 'kpi.definition_revised', $version->id, ['reason' => $reason]);

            return $version;
        });
    }

    public function recordGrade(User $actor, SubmissionAttempt $submission, GradeDecision $decision): void
    {
        KpiObservation::where('submission_attempt_id', $submission->id)->update(['current' => false]);
        $number = $submission->block->content['lessons'][$submission->lesson_index]['kpi'];
        $metric = $this->current($submission->enrolment, $number);
        if (! $metric || $metric->definition['formula'] !== 'approved_grade_mean' || $decision->total === null) {
            return;
        }
        KpiObservation::firstOrCreate(['kpi_version_id' => $metric->id, 'grade_decision_id' => $decision->id], [
            'submission_attempt_id' => $submission->id, 'value' => $decision->total, 'evidence_type' => 'demonstrated_competence',
            'evidence' => ['decision_id' => $decision->id, 'submission_id' => $submission->id, 'rubric_hash' => hash('sha256', json_encode($submission->rubric))], 'actor_id' => $actor->id]);
        $this->snapshot($actor, $metric);
    }

    public function observe(User $actor, KpiVersion $metric, float $value, string $evidence, string $reason): void
    {
        app(Delivery::class)->locked($actor, $metric->enrolment, function ($actor, $enrolment) use ($metric, $value, $evidence, $reason) {
            app(Onboarding::class)->coordinator($actor, $enrolment);
            app(Delivery::class)->reason($reason);
            abort_unless(is_finite($value) && $metric->definition['formula'] === 'manual_mean' && $this->current($enrolment, $metric->number)?->id === $metric->id, 409);
            Validator::make(['evidence' => $evidence], ['evidence' => 'required|string|min:10|max:4000'])->validate();
            KpiObservation::create(['kpi_version_id' => $metric->id, 'value' => $value, 'evidence_type' => $metric->definition['evidence_type'],
                'evidence' => ['reference' => $evidence, 'reason' => $reason], 'actor_id' => $actor->id]);
            $this->snapshot($actor, $metric);
        });
    }

    public function snapshot(User $actor, KpiVersion $metric): KpiSnapshot
    {
        $observations = KpiObservation::where('kpi_version_id', $metric->id)->where('current', true)->where('evidence_type', $metric->definition['evidence_type'])->get();
        $enough = $observations->count() >= $metric->definition['sample_minimum'];
        $value = $enough ? $observations->avg('value') : null;
        $target = $metric->definition['target'];
        $status = 'insufficient_evidence';
        if ($enough) {
            $status = ! is_numeric($target) ? 'target_unknown' : (match ($metric->definition['direction']) {
                'higher' => $value >= $target, 'lower' => $value <= $target, 'band' => $value >= $target && $value <= $metric->definition['upper_target'],
            } ? 'on_target' : 'needs_support');
        }
        $snapshot = KpiSnapshot::create(['kpi_version_id' => $metric->id, 'source_ids' => $observations->pluck('id')->all(), 'value' => $value,
            'status' => $status, 'sample_count' => $observations->count(), 'actor_id' => $actor->id]);
        Audit::record($actor, 'kpi.snapshot_recorded', $snapshot->id);
        app(ApprovedFeed::class)->record($metric->enrolment, $snapshot->id, 1, 'kpi.snapshot', ['kpi_version_id' => $metric->id, 'value' => $snapshot->value, 'status' => $snapshot->status, 'sample_count' => $snapshot->sample_count]);

        return $snapshot;
    }
}
