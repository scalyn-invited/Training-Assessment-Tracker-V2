<?php

namespace App\Services;

use App\Models\User;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Support\Facades\Mail;

/** Synchronous delivery only: raw links must never enter a queue or application log. */
class LocalAccountDelivery
{
    public function available(): bool
    {
        return app(LocalAccounts::class)->enabled() && config('training.smtp_enabled')
            && config('training.account_mail_enabled') && config('mail.default') === 'smtp';
    }

    public function send(User $actor, array $result): string
    {
        $accounts = app(LocalAccounts::class);
        $link = $accounts->inspect($result['token'], $result['purpose']);
        abort_unless($link->issuer_id === $actor->id && $link->id === $result['id'], 403);
        $user = User::findOrFail($link->user_id);
        $allowed = array_map('strtolower', config('training.account_mail_recipients', []));
        if (! $this->available() || ! in_array(strtolower($user->email), $allowed, true)) {
            return 'Email was not sent. Delivery is disabled or this recipient is not approved. Share the private link manually.';
        }

        try {
            // Transport exceptions can contain addresses or message content. Never report them.
            $sent = Mail::mailer('smtp')->raw(
                'Complete your '.$link->purpose.' request: '.$result['url']."\nExpires at ".$link->expires_at.' UTC. If unexpected, do not use this link.',
                fn ($message) => $message->to($user->email)->subject('Your training account link')
            );
            $accepted = $sent !== null;
        } catch (\Throwable) {
            $accepted = false;
        }

        app(PlatformAdministration::class)->audit($actor, $accepted ? 'local.email.accepted' : 'local.email.failed',
            $link->id, $accepted ? 'Mail transport accepted the account link' : 'Mail transport did not confirm acceptance');

        return $accepted ? 'Email accepted by the mail server. Inbox delivery is not confirmed.'
            : 'Email delivery could not be confirmed. The link remains valid; share it privately or cancel and reissue it.';
    }
}
