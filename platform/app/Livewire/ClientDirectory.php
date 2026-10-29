<?php

namespace App\Livewire;

use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'client_search', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $sort = 'asc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sort = $this->sort === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function render()
    {
        $service = app(PlatformAdministration::class);
        abort_unless(auth()->check(), 401);
        $service->authorised(auth()->user());
        $access = app(ClientAccess::class);
        $states = DB::table('platform_client_states')->where('environment', $service->environment())->get()->keyBy('organisation_id');
        $suspended = $states->where('suspended', true)->keys();
        $total = $service->clients()->count();
        $clients = $service->clients()->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.mb_substr($this->search, 0, 120).'%'))
            ->when($this->status === 'suspended', fn ($q) => $q->whereIn('id', $suspended))
            ->when($this->status === 'active', fn ($q) => $q->where('active', true)->whereNotIn('id', $suspended))
            ->when($this->status === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('name', $this->sort === 'desc' ? 'desc' : 'asc')->orderBy('id')->paginate(20);
        $memberships = $access->activeMemberships()->whereIn('cm.organisation_id', $clients->pluck('id'))->get(['cm.id', 'cm.user_id', 'cm.organisation_id']);
        $eligible = $service->accounts()->whereIn('id', $memberships->pluck('user_id'))->where(fn ($q) => $q->where('origin', '!=', 'primary_import')->orWhere('permissions_synced_at', '>=', now()->subSeconds(config('training.permission_freshness_seconds'))))->pluck('id');
        $memberships = $memberships->whereIn('user_id', $eligible);
        $memberCounts = $memberships->groupBy('organisation_id')->map(fn ($rows) => $rows->pluck('user_id')->unique()->count());
        $grants = $access->effective(DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id'), 'ra.')
            ->join('client_memberships as grant_member', 'grant_member.id', '=', 'ra.client_membership_id')
            ->whereColumn('grant_member.user_id', 'ra.user_id')->whereColumn('grant_member.organisation_id', 'ra.organisation_id')
            ->where('ra.environment', $service->environment())->where('ra.scope_type', 'client')->whereNull('ra.group_id')->where('r.code', 'admin')
            ->whereIn('ra.client_membership_id', $memberships->pluck('id'))->get(['ra.organisation_id', 'ra.user_id']);
        $adminCounts = $grants->groupBy('organisation_id')->map(fn ($rows) => $rows->pluck('user_id')->unique()->count());

        return view('livewire.client-directory', compact('clients', 'states', 'total', 'memberCounts', 'adminCounts'));
    }
}
