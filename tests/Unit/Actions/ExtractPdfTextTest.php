<?php

declare(strict_types=1);

use App\Actions\ExtractPdfText;

it('reads the text layer of a PDF', function (): void {
    expect(resolve(ExtractPdfText::class)->handle(pdfWithText(['Margherita   12', 'Espresso (double) 4'])))
        ->toBe("Margherita 12\nEspresso (double) 4");
});

/*
 * Empty is the composer's cue to ask for a photo instead — so a scan, and a
 * file that is not a PDF at all, must both land there rather than throw.
 */
it('reads nothing from a PDF with no text layer or from a file that is not a PDF', function (string $contents): void {
    expect(resolve(ExtractPdfText::class)->handle($contents))->toBeEmpty();
})->with([
    'scan' => [pdfWithText([])],
    'not a PDF' => ['just some bytes'],
]);

/*
 * The text rides in the prompt, and in every later turn's history while the
 * message stays in the window — a long brochure must not cost a whole book.
 */
it('caps the text at the configured length', function (): void {
    config()->set('chat.attachments.max_document_characters', 10);

    expect(resolve(ExtractPdfText::class)->handle(pdfWithText(['Margherita 12'])))->toBe('Margherita');
});
