@if(app(\App\Services\LocalAccounts::class)->enabled())
<section class="panel"><h2>Accounts</h2>
<p>Create an account or invite an existing account. Access starts after password setup or invitation acceptance. Initial access is Member only.</p>
@if(!isset($localClient) && $clients->isEmpty())
<p>Create your first client to start adding accounts.</p><a class="button primary" href="{{ route('platform.section', 'clients') }}">Create first client</a>
@else
<form method="post" action="{{ route('local.actions') }}">@csrf<input type="hidden" name="action" value="create">
@if(isset($localClient))<input type="hidden" name="client" value="{{ $localClient }}">@else<label>Client<select name="client" required>@foreach($clients as $entry)<option value="{{ $entry->id }}">{{ $entry->name }}</option>@endforeach</select></label>@endif
<label>Name<input name="name" maxlength="120" required></label><label>Email<input type="email" name="email" maxlength="200" required></label>
@if(config('training.environment') !== 'production')
<input type="hidden" name="is_synthetic" value="0"><label><input type="checkbox" name="is_synthetic" value="1" @checked(old('is_synthetic', '1') == '1') aria-describedby="account-type-help"> Test account</label>
<p id="account-type-help">Ordinary email addresses are supported. Uncheck for a non-test local account. This choice applies only to new accounts; existing accounts keep their classification. Local development only.</p>
@endif
@include('local-accounts.delivery-option')
<label>Your current password<input name="current_password" type="password" autocomplete="current-password" required maxlength="128"></label><label>Reason<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary">Create account or invitation</button></form>
@endif
<p><a href="{{ route('local.links') }}">Manage setup and recovery links</a> · <a href="{{ route('local.password.change') }}">Change your password</a></p></section>
@endif
