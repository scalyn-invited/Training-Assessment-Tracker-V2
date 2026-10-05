<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use ZipArchive;

/** Runs only in the bounded extraction subprocess, without application bootstrap or credentials. */
class DocumentParser
{
    private float $deadline;

    private array $warnings = [];

    public function extract(string $path, string $extension, array $settings, string $directory): array
    {
        $this->deadline = microtime(true) + 80;
        $this->warnings = [];
        $size = filesize($path);
        if (! $size || $size > 20 * 1024 * 1024) {
            throw new RuntimeException('file_limit');
        }
        $pages = 1;
        $engine = $extension;
        switch ($extension) {
            case 'txt':
                if ($size > $settings['max_text_bytes']) {
                    throw new RuntimeException('text_limit');
                }
                $text = file_get_contents($path);
                break;
            case 'docx':
                $text = $this->docx($path);
                $this->warnings[] = 'DOCX body text only; verify tables, headers, footnotes and any image-only content against the original.';
                break;
            case 'pdf':
                if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                    throw new RuntimeException('invalid_document');
                }
                $info = $this->command([$this->binary($settings, 'pdfinfo'), $path]);
                if (preg_match('/^Encrypted:\s+yes/im', $info)) {
                    throw new RuntimeException('encrypted_document');
                }
                if (! preg_match('/^Pages:\s+(\d+)/m', $info, $match)) {
                    throw new RuntimeException('invalid_document');
                }
                $pages = (int) $match[1];
                if ($pages < 1 || $pages > $settings['max_pages']) {
                    throw new RuntimeException('page_limit');
                }
                $text = '';
                $engine = 'poppler';
                for ($page = 1; $page <= $pages; $page++) {
                    $part = $this->command([$this->binary($settings, 'pdftotext'), '-f', (string) $page, '-l', (string) $page, '-layout', '-enc', 'UTF-8', $path, '-']);
                    if (mb_strlen(trim($part)) < 20) {
                        $prefix = $directory.'/page-'.$page;
                        $this->command([$this->binary($settings, 'pdftoppm'), '-f', (string) $page, '-l', (string) $page, '-singlefile', '-scale-to', '2200', '-png', $path, $prefix]);
                        try {
                            $part = $this->ocr($prefix.'.png', $settings);
                        } finally {
                            if (is_file($prefix.'.png')) {
                                unlink($prefix.'.png');
                            }
                        }
                        $engine = 'poppler+tesseract';
                        $this->warnings[] = 'Page '.$page.' used OCR; check every score, name and number against the original.';
                    }
                    if (trim($part) === '') {
                        throw new RuntimeException('no_text');
                    }
                    $text .= "[Page {$page}]\n".$part."\n";
                    $this->checkText($text, $settings);
                }
                $this->warnings[] = 'PDF text order and embedded diagrams may be incomplete. Compare every relevant section with the original.';
                break;
            case 'png':
            case 'jpg':
            case 'jpeg':
                $text = $this->ocr($path, $settings);
                $engine = 'tesseract';
                $this->warnings[] = 'OCR can misread numbers and omit text. Compare the findings with the original image.';
                break;
            default:
                throw new RuntimeException('unsupported_format');
        }
        $text = trim(str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n"], $text));
        $this->checkText($text, $settings);
        if ($text === '') {
            throw new RuntimeException('no_text');
        }

        return ['text' => $text, 'engine' => $engine, 'pages' => $pages, 'warnings' => array_values(array_unique($this->warnings))];
    }

    private function checkText(string $text, array $settings): void
    {
        if (strlen($text) > $settings['max_text_bytes']) {
            throw new RuntimeException('text_limit');
        }
        if (! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
            throw new RuntimeException('invalid_encoding');
        }
    }

    private function docx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('zip_unavailable');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('invalid_document');
        }
        try {
            if ($zip->numFiles > 500 || $zip->locateName('word/document.xml') === false) {
                throw new RuntimeException('invalid_document');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = strtolower($entry['name']);
                $total += $entry['size'];
                if ($total > 100 * 1024 * 1024 || $entry['size'] > 8 * 1024 * 1024 || ($entry['encryption_method'] ?? 0) !== 0
                    || str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, ':') || str_starts_with($name, '/')
                    || preg_match('/vbaproject|embeddings\/|activex\/|afchunk/', $name)) {
                    throw new RuntimeException('unsafe_document');
                }
                if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                    $xml = $zip->getFromIndex($i);
                    // Reject DTDs, entities, alternate imports and external relationships; never fetch a document resource.
                    if ($xml === false || str_contains($xml, "\0") || ! mb_check_encoding($xml, 'UTF-8') || preg_match('/<!DOCTYPE|<!ENTITY|<[^>]*\baltChunk\b|\bTargetMode\s*=\s*["\x27]External/i', $xml)) {
                        throw new RuntimeException('unsafe_document');
                    }
                }
            }
            $dom = new DOMDocument;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $previous = libxml_use_internal_errors(true);
            try {
                if (! $dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET)) {
                    throw new RuntimeException('invalid_document');
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = [];
            foreach ($xpath->query('//w:body//w:p') as $paragraph) {
                $line = '';
                foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $paragraph) as $node) {
                    $line .= match ($node->localName) {
                        'tab' => "\t", 'br' => "\n", default => $node->textContent
                    };
                }
                $paragraphs[] = $line;
            }

            return implode("\n", $paragraphs);
        } finally {
            $zip->close();
        }
    }

    private function ocr(string $path, array $settings): string
    {
        $image = @getimagesize($path);
        if (! $image || ! in_array($image['mime'], ['image/png', 'image/jpeg']) || $settings['max_image_pixels'] < $image[0] * $image[1]) {
            throw new RuntimeException('image_limit');
        }
        if (! preg_match('/^[a-zA-Z0-9_+]{1,40}$/D', $settings['language'])) {
            throw new RuntimeException('invalid_configuration');
        }
        $tsv = $this->command([$this->binary($settings, 'tesseract'), $path, 'stdout', '-l', $settings['language'], '--psm', '3', 'tsv']);
        $lines = [];
        $low = false;
        foreach (explode("\n", $tsv) as $row) {
            $cells = explode("\t", rtrim($row, "\r"), 12);
            if (count($cells) !== 12 || $cells[0] !== '5' || trim($cells[11]) === '') {
                continue;
            }
            $key = implode(':', array_slice($cells, 1, 4));
            $lines[$key][] = $cells[11];
            $low = $low || (float) $cells[10] < 70;
        }
        if ($low) {
            $this->warnings[] = 'Low-confidence OCR words detected; manually correct them before confirmation.';
        }

        return implode("\n", array_map(fn ($words) => implode(' ', $words), $lines));
    }

    private function binary(array $settings, string $name): string
    {
        $path = $settings[$name] ?? '';
        if (! $path || ! is_file($path) || ! preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $path)) {
            throw new RuntimeException('tool_unavailable');
        }

        return $path;
    }

    private function command(array $argv): string
    {
        $remaining = $this->deadline - microtime(true);
        if ($remaining <= 0) {
            throw new RuntimeException('timeout');
        }
        $process = new Process($argv);
        $process->setTimeout(min(25, $remaining));
        $output = '';
        $bytes = 0;
        try {
            $process->run(function ($type, $chunk) use (&$output, &$bytes, $process) {
                $bytes += strlen($chunk);
                if ($bytes > 2 * 1024 * 1024) {
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
        if (! $process->isSuccessful()) {
            throw new RuntimeException('parser_failed');
        }

        return $output;
    }
}
