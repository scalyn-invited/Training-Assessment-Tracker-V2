<?php

namespace App\Services;

use App\Jobs\ExtractDocument;
use App\Models\DocumentExtraction;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentExtractionService
{
    public const ERRORS = [
        'tool_unavailable' => 'A required PDF/OCR tool is unavailable. Ask the operator to configure it, or enter findings manually.',
        'zip_unavailable' => 'DOCX extraction requires the PHP zip extension in the extraction process.',
        'sandbox_required' => 'The production extraction sandbox has not been configured.',
        'no_text' => 'No readable text was found. Use a clearer scan or enter findings manually.',
        'encrypted_document' => 'Password-protected documents cannot be extracted. Supply an authorised unencrypted copy.',
        'page_limit' => 'The document exceeds the 20-page extraction limit. Supply a smaller relevant excerpt.',
        'text_limit' => 'The extracted text exceeds the limit. Supply a smaller relevant excerpt.',
        'image_limit' => 'The image is invalid or exceeds the 25-megapixel limit.',
        'unsafe_document' => 'The document contains unsupported active, external, encrypted or oversized content.',
        'invalid_encoding' => 'The text is not valid UTF-8. Save a UTF-8 copy or use manual entry.',
        'source_changed' => 'The source file changed or is unavailable. Upload a new copy for scanning.',
        'scan_rejected' => 'The file was rejected or deleted; extraction is blocked.',
        'access_revoked' => 'Access or enrolment status changed. The result was not published.',
        'interrupted' => 'Extraction was interrupted. Request a new attempt or enter findings manually.',
        'timeout' => 'Extraction exceeded its time limit. Use a smaller excerpt or manual entry.',
        'parser_failed' => 'Extraction could not read this document. Use a supported copy or manual entry.',
    ];

    public function authorise(User $actor, EvidenceFile $file): void
    {
        abort_unless(app(Access::class)->canFile($actor, $file), 404);
        app(Onboarding::class)->coordinator($actor, $file->enrolment);
        abort_unless($file->purpose === 'assessment', 422, 'Choose an assessment document, not submitted work.');
    }

    public function request(User $actor, EvidenceFile $file): DocumentExtraction
    {
        return app(Delivery::class)->locked($actor, $file->enrolment, function ($actor, $enrolment) use ($file) {
            abort_unless(in_array($enrolment->status, ['onboarding', 'ready']), 409, 'Active onboarding cannot be replaced.');
            $file = EvidenceFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
            $this->authorise($actor, $file);
            abort_unless(in_array($file->scan_status, ['clean', 'quarantined', 'scanning']), 409, 'The source was rejected or deleted.');
            $latest = DocumentExtraction::where('evidence_file_id', $file->id)->orderByDesc('attempt')->first();
            if ($latest && $latest->status !== 'failed') {
                return $latest;
            }
            $run = DocumentExtraction::create(['evidence_file_id' => $file->id, 'actor_id' => $actor->id, 'attempt' => ($latest?->attempt ?? 0) + 1,
                'version' => 1, 'status' => $file->scan_status === 'clean' ? 'queued' : 'waiting_scan', 'source_hash' => $file->sha256]);
            Audit::record($actor, 'extraction.requested', $run->id, ['file_id' => $file->id, 'source_hash' => $file->sha256]);
            DB::afterCommit(function () use ($run) {
                try {
                    ExtractDocument::dispatch($run->id);
                } catch (\Throwable) {
                    // Durable row remains recoverable by training:extract-recover.
                }
            });

            return $run;
        });
    }

    public function process(string $id): void
    {
        $run = DB::transaction(function () use ($id) {
            $run = $this->lockRun($id);
            if (! in_array($run->status, ['waiting_scan', 'queued'])) {
                return null;
            }
            $file = $run->file;
            try {
                $this->authorise(User::findOrFail($run->actor_id), $file);
                abort_unless(in_array($file->enrolment->status, ['onboarding', 'ready']), 409);
            } catch (\Throwable) {
                $this->fail($run, 'access_revoked');

                return null;
            }
            if (in_array($file->scan_status, ['rejected', 'deleted'])) {
                $this->fail($run, 'scan_rejected');

                return null;
            }
            if ($file->scan_status !== 'clean') {
                return null;
            }
            $run->update(['status' => 'running', 'lease' => (string) Str::uuid(), 'started_at' => now()]);

            return $run;
        });
        if (! $run) {
            return;
        }
        $directory = Storage::disk('local')->path('extraction-work/'.$run->lease);
        $errorCode = null;
        $result = null;
        try {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                throw new \RuntimeException('parser_failed');
            }
            $source = Storage::disk('local')->path($run->file->storage_key);
            if (! is_file($source) || ! hash_equals($run->source_hash, hash_file('sha256', $source))) {
                throw new \RuntimeException('source_changed');
            }
            $input = $directory.'/input';
            if (! copy($source, $input) || ! hash_equals($run->source_hash, hash_file('sha256', $input))) {
                throw new \RuntimeException('source_changed');
            }
            $result = app(DocumentRunner::class)->run($input, strtolower(pathinfo($run->file->original_name, PATHINFO_EXTENSION)), $directory);
        } catch (\Throwable $error) {
            // Never log subprocess output or document text, even on failure.
            $errorCode = array_key_exists($error->getMessage(), self::ERRORS) ? $error->getMessage() : 'parser_failed';
        } finally {
            $this->cleanup($run->lease);
        }
        DB::transaction(function () use ($run, $result, $errorCode) {
            $current = $this->lockRun($run->id);
            if ($current->status !== 'running' || $current->lease !== $run->lease) {
                return;
            }
            $file = EvidenceFile::whereKey($current->evidence_file_id)->lockForUpdate()->firstOrFail();
            try {
                $actor = User::findOrFail($current->actor_id);
                $this->authorise($actor, $file);
                abort_unless(in_array($file->enrolment->status, ['onboarding', 'ready']), 409);
            } catch (\Throwable) {
                $this->fail($current, 'access_revoked');

                return;
            }
            if (! $this->sourceIntact($current, $file)) {
                $errorCode = 'source_changed';
            }
            if ($errorCode) {
                $this->fail($current, $errorCode);

                return;
            }
            $current->update(['status' => 'ready', 'extracted_text' => $result['text'], 'engine' => $result['engine'],
                'page_count' => $result['pages'], 'warnings' => $result['warnings'], 'version' => $current->version + 1]);
            Audit::record($actor, 'extraction.ready', $current->id, ['engine' => $result['engine'], 'pages' => $result['pages']]);
        });
    }

    public function review(User $actor, DocumentExtraction $run, array $input, int $version, int $onboardingVersion, bool $confirm): void
    {
        app(Delivery::class)->locked($actor, $run->file->enrolment, function ($actor, $enrolment) use ($run, $input, $version, $onboardingVersion, $confirm) {
            abort_unless(in_array($enrolment->status, ['onboarding', 'ready']), 409, 'Active onboarding cannot be replaced.');
            $file = EvidenceFile::whereKey($run->evidence_file_id)->lockForUpdate()->firstOrFail();
            $this->authorise($actor, $file);
            $current = DocumentExtraction::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'ready' && $current->version === $version, 409, 'This extraction changed or was already confirmed. Reload after copying your edits.');
            abort_unless($this->sourceIntact($current, $file), 409, 'Source file changed or is no longer clean.');
            $input['source'] = 'Document '.$file->original_name.'; extraction '.$current->id.'; SHA-256 '.$current->source_hash;
            $data = Validator::make($input, app(Onboarding::class)->rules('assessment', $confirm))->validate();
            $current->review_data = $data;
            $current->version++;
            if ($confirm) {
                $draft = app(Onboarding::class)->save($actor, $enrolment, 'assessment', $data, $onboardingVersion, true);
                $assessment = $draft->data;
                $assessment['assessment']['extraction_id'] = $current->id;
                $assessment['assessment']['source_sha256'] = $current->source_hash;
                $assessment['assessment']['extraction_version'] = $current->version;
                $draft->update(['data' => $assessment]);
                $current->status = 'confirmed';
                $current->confirmed_onboarding_id = $draft->id;
            }
            $current->save();
            Audit::record($actor, $confirm ? 'extraction.confirmed' : 'extraction.review_saved', $current->id,
                ['version' => $current->version, 'onboarding_id' => $current->confirmed_onboarding_id]);
        });
    }

    public function recover(): void
    {
        foreach (DocumentExtraction::where('status', 'running')->where('started_at', '<', now()->subSeconds(180))->get() as $run) {
            // Pure local parsing can be retried explicitly, but an expired worker may never publish late.
            if (DocumentExtraction::whereKey($run->id)->where('status', 'running')->where('lease', $run->lease)
                ->update(['status' => 'failed', 'error_code' => 'interrupted', 'version' => $run->version + 1])) {
                $this->cleanup($run->lease);
            }
        }
        DocumentExtraction::whereIn('status', ['waiting_scan', 'queued'])->orderBy('id')->chunkById(100, function ($runs) {
            foreach ($runs as $run) {
                ExtractDocument::dispatch($run->id);
            }
        });
    }

    private function sourceIntact(DocumentExtraction $run, EvidenceFile $file): bool
    {
        $path = Storage::disk('local')->path($file->storage_key);

        return $file->scan_status === 'clean' && hash_equals($run->source_hash, $file->sha256)
            && is_file($path) && hash_equals($run->source_hash, hash_file('sha256', $path));
    }

    private function lockRun(string $id): DocumentExtraction
    {
        $run = DocumentExtraction::findOrFail($id);
        $enrolment = $run->file->enrolment;
        Organisation::whereKey($enrolment->organisation_id)->lockForUpdate()->firstOrFail();
        User::whereKey($enrolment->member_id)->lockForUpdate()->firstOrFail();
        Enrolment::whereKey($enrolment->id)->lockForUpdate()->firstOrFail();
        User::whereKey($run->actor_id)->lockForUpdate()->firstOrFail();
        EvidenceFile::whereKey($run->evidence_file_id)->lockForUpdate()->firstOrFail();

        return DocumentExtraction::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function fail(DocumentExtraction $run, string $code): void
    {
        $run->update(['status' => 'failed', 'error_code' => $code, 'version' => $run->version + 1, 'extracted_text' => null, 'review_data' => null]);
    }

    private function cleanup(?string $lease): void
    {
        if (! $lease || ! Str::isUuid($lease)) {
            return;
        }
        // Fixed prefix + validated UUID, never an uploaded filename or user-supplied path.
        Storage::disk('local')->deleteDirectory('extraction-work/'.$lease);
    }
}
