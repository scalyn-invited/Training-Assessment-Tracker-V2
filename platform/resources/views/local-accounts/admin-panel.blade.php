@if(app(\App\Services\LocalAccounts::class)->enabled())
<section class="panel"><h2>Accounts</h2>
<p>Create an account or invite an existing account. Access starts after password setup or invitation acceptance. Initial access is Member only.</p>
@php($accountClientChoices = $clientChoices ?? $clients ?? collect())
@php($retryCreate = old('_form') === 'local-account-create')
@if(!isset($localClient) && $accountClientChoices->isEmpty())
<p>Create your first client to start adding accounts.</p><a class="button primary" href="{{ route('platform.section', 'clients') }}">Create first client</a>
@else
<form method="post" action="{{ route('local.actions') }}">@csrf<input type="hidden" name="action" value="create"><input type="hidden" name="_form" value="local-account-create">
@if(isset($localClient))<input type="hidden" name="client" value="{{ $localClient }}">@else<label>Client<select name="client" required><option value="">Choose client…</option>@foreach($accountClientChoices as $entry)<option value="{{ $entry->id }}" @selected($retryCreate && old('client') === $entry->id)>{{ $entry->name }}</option>@endforeach</select></label>@endif
<label>Account operation<select name="account_mode" required><option value="new" @selected(old('account_mode')!=='existing')>New account</option><option value="existing" @selected(old('account_mode')==='existing')>Existing account invitation</option></select></label><label>Name<input name="name" value="{{ $retryCreate ? old('name') : '' }}" maxlength="120" required></label><label>Email<input type="email" name="email" value="{{ $retryCreate ? old('email') : '' }}" maxlength="200" required></label>
@if(config('training.environment') !== 'production')
<input type="hidden" name="is_synthetic" value="0"><label><input type="checkbox" name="is_synthetic" value="1" @checked(($retryCreate ? old('is_synthetic', '1') : '1') == '1') aria-describedby="account-type-help"> Test account</label>
<p id="account-type-help">Ordinary email addresses are supported. Uncheck for a non-test local account. This choice applies only to new accounts; existing accounts keep their classification. Local development only.</p>
@endif
@include('local-accounts.delivery-option')
<label>Your current password<input name="current_password" type="password" autocomplete="current-password" required maxlength="128"></label><label>Reason<input name="reason" value="{{ $retryCreate ? old('reason') : '' }}" required minlength="8" maxlength="2000"></label><button class="primary">Review and continue</button></form>
@endif
<p><a href="{{ route('local.links') }}">Manage setup and recovery links</a> · <a href="{{ route('local.password.change') }}">Change your password</a></p></section>
@endif
