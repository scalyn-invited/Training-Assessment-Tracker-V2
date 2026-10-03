<?php

namespace App\Services;

use App\Jobs\ScanEvidence;
use App\Models\EvidenceFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FileScanning
{
    public function inspect(string $id): void
    {
        $file = EvidenceFile::findOrFail($id);
        if ($file->scan_status !== 'quarantined') {
            return;
        }
        $binary = config('training.clamscan_binary');
        if (! $binary || ! is_file($binary)) {
            return;
        }
        if (! EvidenceFile::whereKey($id)->where('scan_status', 'quarantined')->update(['scan_status' => 'scanning'])) {
            return;
        }
        $path = Storage::disk('local')->path($file->storage_key);
        if (! is_file($path) || ! hash_equals($file->sha256, hash_file('sha256', $path))) {
            EvidenceFile::whereKey($id)->where('scan_status', 'scanning')->update(['scan_status' => 'rejected']);

            return;
        }
        $result = $this->scan($binary, $path);
        // Exit 0 is clean; 1 is infected; scanner failures stay quarantined.
        if ($result === 0 && is_file($path) && hash_equals($file->sha256, hash_file('sha256', $path))) {
            EvidenceFile::whereKey($id)->where('scan_status', 'scanning')->update(['scan_status' => 'clean']);
        } elseif ($result === 1) {
            EvidenceFile::whereKey($id)->where('scan_status', 'scanning')->update(['scan_status' => 'rejected']);
        } else {
            EvidenceFile::whereKey($id)->where('scan_status', 'scanning')->update(['scan_status' => 'quarantined']);
        }
    }

    public function recover(): void
    {
        if (! config('training.clamscan_binary')) {
            return;
        }
        EvidenceFile::where('scan_status', 'scanning')->where('updated_at', '<', now()->subSeconds(180))->update(['scan_status' => 'quarantined']);
        foreach (EvidenceFile::where('scan_status', 'quarantined')->pluck('id') as $id) {
            ScanEvidence::dispatch($id);
        }
    }

    protected function scan(string $binary, string $path): int
    {
        $process = new Process([$binary, '--no-summary', '--alert-exceeds-max', '--alert-encrypted', '--max-filesize=20M', '--max-scansize=100M', '--max-recursion=10', '--', $path]);
        $process->setTimeout(90);
        try {
            $process->run();

            return $process->getExitCode() ?? 2;
        } catch (\Throwable) {
            return 2;
        }
    }

    public function validateDocument(string $path, string $extension): void
    {
        if ($extension !== 'docx') {
            return;
        }
        abort_unless(class_exists(\ZipArchive::class), 422, 'DOCX inspection requires the PHP zip extension. Use a text or PDF assessment until the host is qualified.');
        $zip = new \ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'Invalid DOCX container.');
        try {
            abort_unless($zip->numFiles <= 500 && $zip->locateName('word/document.xml') !== false, 422, 'Unsupported document container.');
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = strtolower($entry['name']);
                $total += $entry['size'];
                abort_if($total > 100 * 1024 * 1024 || str_contains($name, '..') || str_contains($name, 'vbaproject') || str_contains($name, 'embeddings/') || str_starts_with($name, '/'), 422, 'Unsafe or oversized document content.');
            }
        } finally {
            $zip->close();
        }
    }
}
