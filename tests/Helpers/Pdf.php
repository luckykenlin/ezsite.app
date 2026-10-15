<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

/*
 * Real PDFs for the chat-attachment tests.
 *
 * `UploadedFile::fake()->create('menu.pdf')` is zero bytes with a PDF name —
 * fine for size and type checks, but ExtractPdfText parses the file, so any
 * test that needs a PDF to be READ needs actual PDF syntax. Built in code
 * rather than kept as a binary fixture so the text a test asserts on sits in
 * the test itself.
 */

/**
 * A one-page PDF whose text layer holds each line of `$lines`; no lines gives
 * a PDF with no text at all — the shape of a scan.
 *
 * @param  list<string>  $lines
 */
function pdfWithText(array $lines): string
{
    $stream = '';

    foreach ($lines as $i => $line) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $stream .= sprintf("BT /F1 12 Tf 72 %d Td (%s) Tj ET\n", 720 - ($i * 16), $escaped);
    }

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.mb_strlen($stream, '8bit')." >>\nstream\n{$stream}endstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $i => $object) {
        $offsets[] = mb_strlen($pdf, '8bit');
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }

    $xref = mb_strlen($pdf, '8bit');
    $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer
<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

/**
 * {@see pdfWithText()} as a composer upload.
 *
 * @param  list<string>  $lines
 */
function pdfUpload(string $name, array $lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, pdfWithText($lines));
}
