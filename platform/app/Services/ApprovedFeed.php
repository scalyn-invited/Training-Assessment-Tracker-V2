<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\SubmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovedFeed
{
    public function record(Enrolment $enrolment, string $entity, int $version, string $kind, array $facts): void
    {
        DB::table('approved_changes')->insertOrIgnore(['event_id' => (string) Str::uuid(), 'enrolment_id' => $enrolment->id, 'entity_id' => $entity,
            'entity_version' => $version, 'kind' => $kind, 'facts' => json_encode($facts, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function grade(SubmissionAttempt $submission): void
    {
        $this->record($submission->enrolment, $submission->id, $submission->version, 'grade.approved', [
            'grade_id' => $submission->approved_grade_id, 'submission_id' => $submission->id, 'total' => $submission->grades()->findOrFail($submission->approved_grade_id)->decision->total]);
    }

    public function read(User $actor, int $after = 0, int $limit = 50): array
    {
        $actor = $actor->fresh();
        $visible = app(Access::class)->externalEnrolments($actor);
        abort_unless(app(Access::class)->active($actor), 401);
        $rows = DB::table('approved_changes')->join('enrolments', 'enrolments.id', '=', 'approved_changes.enrolment_id')->join('users as members', 'members.id', '=', 'enrolments.member_id')
            ->whereIn('enrolments.id', $visible->select('enrolments.id'))->where('sequence', '>', $after)
            ->where(fn ($q) => $q->where('members.export_training_history', true)->orWhere(fn ($w) => $w->whereNotNull('members.linked_at')->whereColumn('approved_changes.created_at', '>=', 'members.linked_at')))
            ->orderBy('sequence')->limit(max(1, min(100, $limit)))->select('approved_changes.*')->get();

        return ['items' => $rows->map(fn ($row) => ['event_id' => $row->event_id, 'enrolment_id' => $row->enrolment_id, 'entity_id' => $row->entity_id,
            'version' => $row->entity_version, 'kind' => $row->kind, 'facts' => json_decode($row->facts, true), 'approved_at' => $row->created_at])->all(),
            'next_cursor' => $rows->last()?->sequence ?? $after, 'data_as_of' => now()->toISOString(), 'permission_version' => $actor->permission_version];
    }
}
