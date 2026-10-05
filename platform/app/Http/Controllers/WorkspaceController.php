<?php

namespace App\Http\Controllers;

use App\Jobs\ScanEvidence;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FileScanning;
use App\Services\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceController
{
    public function index(Request $request, Access $access)
    {
        $enrolments = $access->enrolments($request->user())->with(['member', 'group'])->orderBy('title')->paginate(12);

        return view('dashboard', compact('enrolments'));
    }

    public function show(Request $request, string $id, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->with(['member', 'group', 'coordinator'])->findOrFail($id);
        $files = $enrolment->files->filter(fn ($file) => $access->canFile($request->user(), $file));

        return view('enrolment', compact('enrolment', 'files'));
    }

    public function people(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $people = User::where('organisation_id', $request->user()->organisation_id)->where('environment', $request->user()->environment)
            ->when($request->user()->environment === 'production', fn ($q) => $q->where('is_synthetic', false))->orderBy('name')->paginate(20);

        return view('people', compact('people'));
    }

    public function check(Request $request, string $id, Access $access, Outbox $outbox)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        $data = $request->validate(['expected_version' => 'required|integer|min:1', 'idempotency_key' => 'required|string|min:8|max:128']);
        $outbox->requestCheck($request->user(), $enrolment, (int) $data['expected_version'], $data['idempotency_key']);

        return back()->with('status', 'Check queued. Its receipt will appear below when the worker completes it.');
    }

    public function job(Request $request, string $id, Access $access)
    {
        $job = OutboxEvent::where('actor_id', $request->user()->id)->where('organisation_id', $request->user()->organisation_id)
            ->where('environment', $request->user()->environment)->findOrFail($id);
        abort_unless($access->enrolments($request->user())->whereKey($job->enrolment_id)->exists(), 404);

        return response()->json(['job_id' => $job->id, 'state' => $job->status, 'error_code' => $job->error_code]);
    }

    public function download(Request $request, string $id, Access $access)
    {
        $file = EvidenceFile::findOrFail($id);
        abort_unless($access->canFile($request->user(), $file), 404);
        abort_unless($file->scan_status === 'clean', 409, 'File is quarantined pending scanner approval.');
        abort_unless(Storage::disk('local')->exists($file->storage_key), 404);
        Audit::record($request->user(), 'evidence.downloaded', $file->id);

        return Storage::disk('local')->download($file->storage_key, $file->original_name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function upload(Request $request, string $id, Access $access)
    {
        $enrolment = $access->enrolments($request->user())->findOrFail($id);
        abort_unless($request->user()->id === $enrolment->member_id || ($request->user()->role === 'coordinator' && $request->user()->sensitive_access), 403);
        $request->validate(['evidence' => 'required|file|max:20480|mimes:pdf,png,jpg,jpeg,txt,docx|extensions:pdf,png,jpg,jpeg,txt,docx', 'purpose' => 'nullable|in:assessment,evidence']);
        $upload = $request->file('evidence');
        app(FileScanning::class)->validateDocument($upload->getRealPath(), strtolower($upload->getClientOriginalExtension()));
        $key = 'quarantine/'.Str::uuid();
        Storage::disk('local')->put($key, file_get_contents($upload->getRealPath()));
        try {
            DB::transaction(function () use ($request, $enrolment, $upload, $key, $access) {
                $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $current = Enrolment::whereKey($enrolment->id)->lockForUpdate()->firstOrFail();
                abort_unless($access->canView($actor, $current), 404);
                $file = EvidenceFile::create(['organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                    'enrolment_id' => $enrolment->id, 'storage_key' => $key, 'original_name' => Str::limit(basename($upload->getClientOriginalName()), 180, ''),
                    'mime' => $upload->getMimeType(), 'bytes' => $upload->getSize(), 'sha256' => hash_file('sha256', $upload->getRealPath()),
                    'scan_status' => 'quarantined', 'sensitive' => true, 'uploaded_by' => $actor->id, 'purpose' => $request->input('purpose', 'assessment')]);
                Audit::record($actor, 'evidence.quarantined', $file->id);
                ScanEvidence::dispatch($file->id)->afterCommit();
            });
        } catch (\Throwable $error) {
            if (! EvidenceFile::where('storage_key', $key)->exists()) {
                Storage::disk('local')->delete($key);
            }
            throw $error;
        }

        return back()->with('status', 'Upload stored privately in quarantine. Downloads remain blocked until a real scanner is connected.');
    }
}
