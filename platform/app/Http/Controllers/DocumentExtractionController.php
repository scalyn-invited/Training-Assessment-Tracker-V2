<?php

namespace App\Http\Controllers;

use App\Models\DocumentExtraction;
use App\Models\EvidenceFile;
use App\Services\Audit;
use App\Services\DocumentExtractionService;
use App\Services\Onboarding;
use Illuminate\Http\Request;

class DocumentExtractionController
{
    public function show(Request $request, string $id, DocumentExtractionService $service)
    {
        $file = EvidenceFile::findOrFail($id);
        $service->authorise($request->user(), $file);
        $runs = DocumentExtraction::where('evidence_file_id', $id)->orderByDesc('attempt')->get();
        $run = $runs->first();
        $draft = app(Onboarding::class)->latest($file->enrolment);
        $review = $run?->review_data ?? [];
        $editable = in_array($file->enrolment->status, ['onboarding', 'ready']);
        Audit::record($request->user(), 'extraction.viewed', $file->id);

        return response()->view('documents.review', compact('file', 'runs', 'run', 'draft', 'review', 'editable'))->header('Cache-Control', 'private, no-store');
    }

    public function request(Request $request, string $id, DocumentExtractionService $service)
    {
        $service->request($request->user(), EvidenceFile::findOrFail($id));

        return redirect()->route('documents.show', $id)->with('status', 'Extraction requested. Refresh this page for status; quarantined files wait for scanner approval.');
    }

    public function review(Request $request, string $id, DocumentExtractionService $service)
    {
        $input = $request->validate(['version' => 'required|integer|min:1', 'onboarding_version' => 'required|integer|min:0', 'data' => 'required|array', 'action' => 'required|in:save,confirm']);
        $run = DocumentExtraction::findOrFail($id);
        $service->review($request->user(), $run, $input['data'], (int) $input['version'], (int) $input['onboarding_version'], $input['action'] === 'confirm');

        $message = $input['action'] === 'confirm'
            ? 'Reviewed findings confirmed as a new onboarding version. Refresh and review any curriculum draft before approval.'
            : 'Review draft saved. Findings have not been confirmed or sent to AI.';
        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);

            return response()->json(['redirect' => route('documents.show', $run->evidence_file_id)]);
        }

        return redirect()->route('documents.show', $run->evidence_file_id)->with('status', $message);
    }
}
