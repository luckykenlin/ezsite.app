<?php

declare(strict_types=1);

use App\Enums\ChromeSlot;

it('builds the editor pseudo key from the slot', function (): void {
    expect(ChromeSlot::Header->editorKey())->toBe('chrome:header')
        ->and(ChromeSlot::Footer->editorKey())->toBe('chrome:footer');
});

it('round-trips an editor key back to its slot', function (ChromeSlot $slot): void {
    expect(ChromeSlot::fromEditorKey($slot->editorKey()))->toBe($slot);
})->with(ChromeSlot::cases());

it('reads a regular selection as no slot', function (?string $key): void {
    expect(ChromeSlot::fromEditorKey($key))->toBeNull();
})->with([
    'nothing selected' => null,
    'a page block uuid' => '0198f0a1-2b3c-7d4e-8f90-123456789abc',
    // The bare slot name is NOT an editor key — only the prefixed form is.
    'a bare slot name' => 'header',
    'an unknown prefixed slot' => 'chrome:sidebar',
]);
