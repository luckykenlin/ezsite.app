<?php

declare(strict_types=1);

namespace App\Actions;

use Exception;
use Smalot\PdfParser\Parser;

/**
 * Reduce a PDF the operator attached to chat to the text an AI turn can read.
 *
 * The chat runs on DeepSeek only, and DeepSeek's API takes text and images
 * but no documents — its SDK gateway throws on a PDF attachment. So the PDF
 * is read HERE, once, at send time, and the model only ever sees its text.
 * Rendering pages to images for the vision model was the alternative; it
 * needs Ghostscript on every host, and the PDFs this is for (menus, price
 * lists, brochures) are born-digital, so their text is already in the file.
 *
 * A scanned PDF has no text layer and comes back empty — the composer turns
 * that into "send a photo instead" rather than an attachment the model
 * cannot read. Encrypted or corrupt files come back empty for the same
 * reason.
 */
final readonly class ExtractPdfText
{
    public function handle(string $contents): string
    {
        try {
            $text = new Parser()->parseContent($contents)->getText();
        } catch (Exception) {
            return '';
        }

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;

        return mb_substr(mb_trim($text), 0, config()->integer('chat.attachments.max_document_characters'));
    }
}
