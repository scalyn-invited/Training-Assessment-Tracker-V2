<?php

namespace Tests\Feature;

use App\Models\EvidenceFile;
use App\Services\DocumentExtractionService;
use App\Services\FileScanning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class DocumentScannerTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    public function test_real_clamav_gates_extraction_using_a_harmless_custom_signature(): void
    {
        $binary = '/usr/bin/clamscan';
        if (! is_file($binary)) {
            if (getenv('DOCUMENT_REQUIRE_TOOLS')) {
                $this->fail('ClamAV required in the utility verification job.');
            }
            $this->markTestSkipped('ClamAV not installed; never substitute a fake clean result for local uploads.');
        }
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        $this->seed();
        // Engine integration proof only; this tiny custom database is not production signature coverage.
        $marker = 'Harmless synthetic scanner rejection fixture.';
        Storage::disk('local')->put('scan-test.hdb', md5($marker).':'.strlen($marker).":Synthetic.Test.Marker\n");
        config(['training.clamscan_binary' => $binary, 'training.clamav_database' => Storage::disk('local')->path('scan-test.hdb')]);
        $file = $this->enrolment()->files()->firstOrFail();
        $file->update(['scan_status' => 'quarantined']);
        $run = app(DocumentExtractionService::class)->request($this->person(), $file);
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('waiting_scan', $run->fresh()->status);
        app(FileScanning::class)->inspect($file->id);
        $this->assertSame('clean', $file->fresh()->scan_status);
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('ready', $run->fresh()->status);
        $bad = EvidenceFile::create(array_merge($file->only(['organisation_id', 'environment', 'enrolment_id', 'mime', 'purpose', 'sensitive']), [
            'storage_key' => 'marker-fixture', 'original_name' => 'marker.txt', 'scan_status' => 'quarantined', 'bytes' => strlen($marker), 'sha256' => hash('sha256', $marker),
        ]));
        Storage::disk('local')->put($bad->storage_key, $marker);
        $blocked = app(DocumentExtractionService::class)->request($this->person(), $bad);
        app(FileScanning::class)->inspect($bad->id);
        $this->assertSame('rejected', $bad->fresh()->scan_status);
        app(DocumentExtractionService::class)->process($blocked->id);
        $this->assertSame('scan_rejected', $blocked->fresh()->error_code);
        $this->assertNull($blocked->fresh()->extracted_text);
    }
}
