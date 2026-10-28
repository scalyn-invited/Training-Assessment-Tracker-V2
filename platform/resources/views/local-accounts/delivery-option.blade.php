@if(app(\App\Services\LocalAccountDelivery::class)->available())
<label><input type="checkbox" name="send_email" value="1"> Email this link</label>
<p>Email can be sent only to approved recipients.</p>
@else
<p>Email is not configured. A private link will be shown for manual sharing.</p>
@endif
