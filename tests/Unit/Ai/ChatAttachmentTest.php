<?php

declare(strict_types=1);

use App\Ai\ChatAttachment;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;

function imageAttachment(): ChatAttachment
{
    return new ChatAttachment(
        kind: ChatAttachment::KIND_IMAGE,
        name: 'kitchen.jpg',
        file: ['type' => 'stored-image', 'name' => 'kitchen.jpg', 'path' => 'chat/chat-a.jpg', 'disk' => 'public'],
        mediaId: 42,
        width: 1600,
        height: 900,
    );
}

it('round-trips through its array shape', function (): void {
    $attachment = ChatAttachment::fromArray(imageAttachment()->toArray());

    expect($attachment)->not->toBeNull()
        ->and($attachment->kind)->toBe('image')
        ->and($attachment->name)->toBe('kitchen.jpg')
        ->and($attachment->mediaId)->toBe(42)
        ->and($attachment->width)->toBe(1600)
        ->and($attachment->height)->toBe(900)
        ->and($attachment->toArray())->toBe(imageAttachment()->toArray());
});

/*
 * These shapes come back from a JSON column and a queued payload, so a corrupt
 * row must degrade to null rather than take the transcript or the turn down.
 */
it('refuses malformed stored shapes', function (mixed $stored): void {
    expect(ChatAttachment::fromArray($stored))->toBeNull();
})->with([
    'not an array' => ['a string'],
    'unknown kind' => [['kind' => 'video', 'name' => 'a.mp4', 'file' => []]],
    'missing name' => [['kind' => 'image', 'file' => []]],
    'file not an array' => [['kind' => 'document', 'name' => 'menu.pdf', 'file' => 'menu.pdf']],
]);

it('discards non-integer media ids and dimensions instead of failing', function (): void {
    $attachment = ChatAttachment::fromArray([
        'kind' => 'image',
        'name' => 'kitchen.jpg',
        'file' => ['type' => 'stored-image', 'path' => 'chat/chat-a.jpg', 'disk' => 'public'],
        'media_id' => '42',
        'width' => '1600',
        'height' => null,
    ]);

    expect($attachment->mediaId)->toBeNull()
        ->and($attachment->width)->toBeNull()
        ->and($attachment->height)->toBeNull();
});

it('rehydrates the SDK file named after the operator upload', function (): void {
    $image = imageAttachment()->toFile();

    $document = new ChatAttachment(
        kind: ChatAttachment::KIND_DOCUMENT,
        name: 'menu.pdf',
        file: ['type' => 'stored-document', 'name' => 'menu.pdf', 'path' => 'chat/chat-b.pdf', 'disk' => 'local'],
    )->toFile();

    expect($image)->toBeInstanceOf(StoredImage::class)
        ->and($image->path)->toBe('chat/chat-a.jpg')
        ->and($image->disk)->toBe('public')
        ->and($image->name())->toBe('kitchen.jpg')
        ->and($document)->toBeInstanceOf(StoredDocument::class)
        ->and($document->disk)->toBe('local');
});

it('rehydrates nothing from a file shape the SDK cannot reconstruct', function (mixed $file): void {
    $attachment = new ChatAttachment(ChatAttachment::KIND_IMAGE, 'kitchen.jpg', $file);

    expect($attachment->toFile())->toBeNull();
})->with([
    'unknown type' => [['type' => 'carrier-pigeon']],
    // File::fromArray THROWS on a known type missing its payload key — the
    // catch in toFile() is what keeps that a null instead of a dead turn.
    'missing path' => [['type' => 'stored-image', 'disk' => 'public']],
]);

it('announces an image with the media id the placement tool takes', function (): void {
    expect(imageAttachment()->promptLine())
        ->toContain("image 'kitchen.jpg'")
        ->toContain('(1600x900)')
        ->toContain('media id 42')
        ->toContain('set block image');
});

it('announces a document as directly readable', function (): void {
    $line = new ChatAttachment(ChatAttachment::KIND_DOCUMENT, 'menu.pdf', [])->promptLine();

    expect($line)->toContain("document 'menu.pdf'")
        ->toContain('read it directly');
});

it('announces an image with no dimensions without inventing them', function (): void {
    $line = new ChatAttachment(ChatAttachment::KIND_IMAGE, 'logo.png', [], mediaId: 7)->promptLine();

    expect($line)->toContain('media id 7')
        ->not->toContain('(');
});
