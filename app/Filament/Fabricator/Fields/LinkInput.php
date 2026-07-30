<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use App\Models\Page;
use App\Site\UrlScheme;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * The shared URL field for block schemas: a plain TextInput whose datalist
 * suggests the tenant's own page paths (parent chains included), so internal
 * links are picked instead of typed. Storage stays a plain string — external
 * URLs, tel:/mailto:, and anchors keep working, and nothing changes for the
 * XSS posture, the block contract, or the views. Known limit: a renamed slug
 * does not rewrite stored links (a `page:{id}` reference scheme is a
 * deliberately deferred phase-2 idea).
 *
 * No ->url() rule for the same reason the blocks never had one: internal
 * links are relative paths ("/contact") or anchors ("#contact"), which the
 * url rule rejects. What replaces it is a narrow deny-list
 * ({@see UrlScheme::isExecutable()}) so the field still refuses the schemes that
 * execute — the render guard in
 * {@see \App\Filament\Fabricator\BlockRegistry::denyExecutableUrls()} would drop
 * them anyway, but silently, and an operator who pastes one deserves to be told
 * rather than watch their link vanish.
 */
final class LinkInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->maxLength(2048)
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && UrlScheme::isExecutable($value)) {
                    $fail(__('This link uses a scheme that is not allowed.'));
                }
            })
            // Lazy: building the schema (e.g. for contract()) never queries.
            ->datalist(fn (): array => Page::query()
                ->orderBy('title')
                ->get()
                ->map(static fn (Page $page): string => $page->getUrl())
                ->all());
    }
}
