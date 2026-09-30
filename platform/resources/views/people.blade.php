@extends('layouts.app')
@section('title', 'People')
@section('content')
<p class="eyebrow">ADMINISTRATION</p><h1>People in this environment</h1><p class="lead">Synthetic profiles are isolated from production. Admin access does not grant private assessment access.</p>
<div class="panel table-wrap"><table><thead><tr><th>Name</th><th>Role</th><th>Profile</th><th>Access</th></tr></thead><tbody>@foreach($people as $person)<tr><td>{{ $person->name }}</td><td>{{ ucfirst($person->role) }}</td><td>{{ $person->is_synthetic ? 'Synthetic test' : 'Real member' }} · {{ str_replace('_', ' ', $person->sync_policy) }}</td><td>{{ $person->active ? 'Active' : 'Suspended' }}</td></tr>@endforeach</tbody></table></div>{{ $people->links() }}
@endsection
