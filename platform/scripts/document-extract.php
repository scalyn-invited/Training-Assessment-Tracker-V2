<?php

use App\Services\DocumentParser;

// Deliberately do not bootstrap Laravel or read .env. stdin is trusted worker configuration.
require dirname(__DIR__).'/vendor/autoload.php';

try {
    $input = json_decode(stream_get_contents(STDIN, 16384), true, 32, JSON_THROW_ON_ERROR);
    $result = (new DocumentParser)->extract($input['path'], $input['extension'], $input['settings'], $input['directory']);
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $allowed = ['file_limit', 'text_limit', 'invalid_document', 'encrypted_document', 'page_limit', 'no_text', 'unsupported_format', 'invalid_encoding', 'zip_unavailable', 'unsafe_document', 'image_limit', 'invalid_configuration', 'tool_unavailable', 'timeout', 'output_limit', 'parser_failed'];
    echo json_encode(['error' => in_array($error->getMessage(), $allowed, true) ? $error->getMessage() : 'parser_failed']);
    exit(1);
}
