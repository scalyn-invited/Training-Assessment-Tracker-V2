<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\Group;
use App\Models\Promotion;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Services\Access;
use App\Services\Administration;
use App\Services\Ai\Registry;
use App\Services\Grading;
use App\Services\Promotions;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdministrationController
{
    public function show(Request $request, Registry $registry)
    {
        $actor = $request->user();
        $registry->admin($actor);
        $client = app(Access::class)->client($actor);
        $people = User::whereIn('id', app(ClientAccess::class)->members($actor, $client)->select('cm.user_id'))
            ->where('environment', $actor->environment)->orderBy('name')->get();
        $groups = Group::where('organisation_id', app(Access::class)->client($actor))->where('environment', $actor->environment)->where('kind', 'team')->orderBy('name')->get();
        $promotions = Promotion::where('organisation_id', $client)->where('environment', $actor->environment)->whereIn('user_id', $people->pluck('id'))->latest()->get();
        $appeals = Appeal::whereIn('submission_attempt_id', SubmissionAttempt::where('organisation_id', app(Access::class)->client($actor))->where('environment', $actor->environment)->select('id'))->where('status', 'open')->get();

        $coordinators = $people->filter(fn ($person) => app(ClientAccess::class)->grants($person, 'coordinator')->where('ra.organisation_id', $client)->exists());
        $members = $people->filter(fn ($person) => app(ClientAccess::class)->clientRole($person, $client, 'member'));
        $allocations = DB::table('coordinator_allocations as ca')
            ->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')->join('users as member', 'member.id', '=', 'cm.user_id')
            ->join('client_memberships as rm', 'rm.id', '=', 'ca.coordinator_membership_id')->join('users as recipient', 'recipient.id', '=', 'rm.user_id')
            ->where('ca.organisation_id', $client)->where('ca.environment', $actor->environment)->whereNull('ca.ends_at')
            ->where('recipient.id', '!=', $actor->id)->select(['ca.id', 'member.name as member_name', 'recipient.name as recipient_name'])->get();
        $sensitiveGrants = DB::table('client_sensitive_grants')->where('organisation_id', $client)->whereNull('ends_at')->get(['id', 'allocation_id']);

        return view('administration', compact('people', 'members', 'coordinators', 'groups', 'promotions', 'appeals', 'allocations', 'sensitiveGrants'));
    }

    public function act(Request $request, Administration $admin, Promotions $promotions, Grading $grading)
    {
        app(Registry::class)->admin($request->user());
        $input = $request->validate(['action' => 'required|in:person,group,assignment,enrol,deactivate,promote,reconcile,send,history,appeal,sensitive_grant,sensitive_revoke',
            'allocation' => 'nullable|uuid', 'grant' => 'nullable|uuid',
            'name' => 'nullable|string', 'email' => 'nullable|email', 'role' => 'nullable|in:member,coordinator', 'synthetic' => 'nullable|boolean', 'reason' => 'required|string|min:8|max:2000',
            'person' => 'nullable|uuid', 'coordinator' => 'nullable|uuid', 'group' => 'nullable|uuid', 'title' => 'nullable|string', 'expected' => 'nullable|integer',
            'assignment_type' => 'nullable|in:member,coordinator', 'active' => 'nullable|boolean', 'key' => 'nullable|uuid', 'promotion' => 'nullable|uuid', 'appeal' => 'nullable|uuid']);
        $actor = $request->user();
        $reason = $input['reason'];
        $person = isset($input['person']) ? User::findOrFail($input['person']) : null;
        if ($person) {
            $admin->scoped($actor, $person);
        }
        $group = isset($input['group']) ? Group::findOrFail($input['group']) : null;
        if ($group) {
            $admin->scoped($actor, $group);
        }
        switch ($input['action']) {
            case 'sensitive_grant': app(SensitiveAccess::class)->grant($actor, app(Access::class)->client($actor), $input['allocation'] ?? '', $reason);
                break;
            case 'sensitive_revoke': app(SensitiveAccess::class)->revoke($actor, app(Access::class)->client($actor), $input['grant'] ?? '', $reason);
                break;
            case 'person': $admin->person($actor, $input);
                break;
            case 'group': $admin->group($actor, $input['name'] ?? '', $reason);
                break;
            case 'assignment': abort_unless($person && $group, 422);
                $admin->assignment($actor, $person, $group, ($input['assignment_type'] ?? '') === 'coordinator', (bool) ($input['active'] ?? false), (int) ($input['expected'] ?? 0), $reason);
                break;
            case 'enrol': abort_unless($person, 422);
                $admin->enrol($actor, $person, User::findOrFail($input['coordinator'] ?? ''), $group, $input['title'] ?? '', $reason);
                break;
            case 'deactivate': abort_unless($person, 422);
                $admin->deactivate($actor, $person, (int) ($input['expected'] ?? 0), $reason);
                break;
            case 'promote': abort_unless($person, 422);
                $promotions->request($actor, $person, $input['key'] ?? '', $reason);
                break;
            case 'send': case 'reconcile': $promotions->queue($actor, Promotion::findOrFail($input['promotion'] ?? ''), $input['action'] === 'reconcile', $reason);
                break;
            case 'history': abort_unless($person, 422);
                $promotions->history($actor, $person, $reason);
                break;
            case 'appeal': $grading->assignAppeal($actor, Appeal::findOrFail($input['appeal'] ?? ''), User::findOrFail($input['coordinator'] ?? ''), $reason);
                break;
        }

        return back()->with('status', 'Administration action recorded. Live promotion remains prepared until a primary-platform adapter is configured.');
    }
}
