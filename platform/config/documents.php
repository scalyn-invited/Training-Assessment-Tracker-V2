<?php

return [
    // Absolute executable paths only; never accept these from an upload/request.
    'pdftotext' => env('DOCUMENT_PDFTOTEXT'),
    'pdfinfo' => env('DOCUMENT_PDFINFO'),
    'pdftoppm' => env('DOCUMENT_PDFTOPPM'),
    'tesseract' => env('DOCUMENT_TESSERACT'),
    'language' => env('DOCUMENT_OCR_LANGUAGE', 'eng'),
    // Production requires a qualified OS sandbox wrapper denying network access.
    'sandbox' => env('DOCUMENT_SANDBOX_WRAPPER'),
    'max_pages' => 20,
    'max_text_bytes' => 120000,
    'max_image_pixels' => 25000000,
];
