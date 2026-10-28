@extends('layouts.app')
@section('title', 'People')
@section('content')
<p class="eyebrow">ADMINISTRATION</p><h1>People in this client</h1><p class="lead">Synthetic profiles are isolated from production. Admin access does not grant private assessment access.</p>
<div class="panel table-wrap" role="region" aria-label="People in the active client" tabindex="0"><table><caption class="sr-only">People with an active membership in the selected client</caption><thead><tr><th>Name</th><th>Client relationship</th><th>Profile</th><th>Access</th></tr></thead><tbody>@forelse($people as $person)<tr><td>{{ $person->name }}</td><td>Active client membership</td><td>{{ str_replace('_', ' ', $person->sync_policy) }}</td><td>{{ $person->active ? 'Active' : 'Suspended' }}</td></tr>@empty<tr><td colspan="4">No people are available in this client.</td></tr>@endforelse</tbody></table></div>{{ $people->links() }}
@endsection
