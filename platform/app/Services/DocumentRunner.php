<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class DocumentRunner
{
    public function run(string $path, string $extension, string $directory): array
    {
        $settings = config('documents');
        $sandbox = $settings['sandbox'];
        if (app()->environment('production') || config('training.environment') === 'production') {
            if (! $sandbox || ! is_file($sandbox)) {
                throw new RuntimeException('sandbox_required');
            }
        }
        $argv = [PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'allow_url_fopen=0', base_path('scripts/document-extract.php')];
        if ($sandbox) {
            // Wrapper contract: first argument is the private work directory, then the command.
            array_unshift($argv, $sandbox, $directory);
        }
        // Child tools receive no application/database/AI credentials from the worker environment.
        $environment = array_fill_keys(array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER))), false);
        foreach (['SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP', 'TMPDIR', 'TESSDATA_PREFIX'] as $key) {
            if (getenv($key) !== false) {
                $environment[$key] = getenv($key);
            }
        }
        $environment['LC_ALL'] = 'C';
        $process = new Process($argv, $directory, $environment);
        $process->setInput(json_encode(['path' => $path, 'extension' => $extension, 'directory' => $directory, 'settings' => $settings], JSON_THROW_ON_ERROR));
        $process->setTimeout(90);
        $output = '';
        $bytes = 0;
        try {
            $process->run(function ($type, $chunk) use (&$output, &$bytes, $process) {
                $bytes += strlen($chunk);
                if ($bytes > 1024 * 1024) {
                    $process->stop(0);
                    throw new RuntimeException('output_limit');
                }
                if ($type === Process::OUT) {
                    $output .= $chunk;
                }
                $process->clearOutput();
                $process->clearErrorOutput();
            });
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('timeout');
        }
        $result = json_decode($output, true);
        if (! $process->isSuccessful() || ! is_array($result) || isset($result['error'])) {
            throw new RuntimeException($result['error'] ?? 'parser_failed');
        }
        if (! isset($result['text'], $result['engine'], $result['pages'], $result['warnings']) || ! is_string($result['text'])
            || strlen($result['text']) > $settings['max_text_bytes'] || ! mb_check_encoding($result['text'], 'UTF-8')) {
            throw new RuntimeException('invalid_result');
        }

        return $result;
    }
}
