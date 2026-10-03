<?php

namespace App\Services;

use App\Jobs\SendLearningNotice;
use App\Models\Enrolment;
use App\Models\LearningNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class LearningNotices
{
    public function record(Enrolment $enrolment, string $recipient, string $key, string $kind): LearningNotification
    {
        $notice = LearningNotification::firstOrCreate(['event_key' => $key], ['enrolment_id' => $enrolment->id, 'recipient_id' => $recipient, 'kind' => $kind]);
        SendLearningNotice::dispatch($notice->id)->afterCommit();

        return $notice;
    }

    public function deliver(string $id): void
    {
        $claim = DB::transaction(function () use ($id) {
            $notice = LearningNotification::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($notice->status !== 'pending' || $notice->available_at?->isFuture()) {
                return null;
            }
            $enrolment = Enrolment::findOrFail($notice->enrolment_id);
            $user = User::findOrFail($notice->recipient_id);
            if (! app(Access::class)->canView($user, $enrolment)) {
                $notice->update(['status' => 'cancelled', 'error_code' => 'access_revoked']);

                return null;
            }
            if (! $user->training_email) {
                $notice->update(['status' => 'in_app_only']);

                return null;
            }
            if ($enrolment->environment === 'test' || $user->is_synthetic || $enrolment->member->is_synthetic) {
                $notice->update(['status' => 'sink_delivered', 'attempts' => $notice->attempts + 1]);

                return null;
            }
            if (! config('training.smtp_enabled') || config('mail.default') !== 'smtp') {
                $notice->update(['status' => 'in_app_only', 'error_code' => 'smtp_not_configured']);

                return null;
            }
            $notice->update(['status' => 'sending', 'attempts' => $notice->attempts + 1, 'started_at' => now()]);

            return [$notice, $user, $enrolment];
        });
        if (! $claim) {
            return;
        }
        [$notice, $user, $enrolment] = $claim;
        try {
            // Only a secure workspace link; never scores, submissions or assessment content.
            Mail::raw('A training update is ready. Sign in to review: '.route('delivery.show', $enrolment->id), function ($message) use ($user) {
                $message->to($user->email)->subject('Training review update');
            });
            $notice->update(['status' => 'relay_accepted']);
        } catch (\Throwable $error) {
            $code = null;
            if ($error instanceof TransportExceptionInterface
                && preg_match('/got code "([45][0-9]{2})"/', $error->getMessage(), $match)) {
                $code = (int) $match[1];
            }
            if ($code !== null && $code < 500 && $notice->attempts < 4) {
                $notice->update(['status' => 'pending', 'error_code' => 'smtp_transient_rejection', 'available_at' => now()->addSeconds(10 * $notice->attempts + random_int(0, 5))]);
                SendLearningNotice::dispatch($notice->id)->delay($notice->available_at)->afterCommit();
            } else {
                $notice->update(['status' => $code !== null ? 'failed' : 'ambiguous', 'error_code' => $code !== null ? 'smtp_rejected' : 'smtp_outcome_unknown']);
            }
        }
    }

    public function recover(): void
    {
        LearningNotification::where('status', 'sending')->where('started_at', '<', now()->subSeconds(180))->update(['status' => 'ambiguous', 'error_code' => 'worker_interrupted']);
        foreach (LearningNotification::where('status', 'pending')->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->pluck('id') as $id) {
            SendLearningNotice::dispatch($id);
        }
    }
}
