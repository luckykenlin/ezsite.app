<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The site-wide chrome slots: the header/footer the main layout renders
 * around every page body.
 *
 * This enum owns the seam between two models. A slot is BOTH a Fabricator
 * block type (`header` / `footer`, stored in `site_settings` and rendered by
 * SiteChrome) AND — inside the page editor only — a pseudo block key
 * (`chrome:header`) that lets the same right pane edit chrome and page blocks
 * alike. That second convention used to live as bare strings in the editor,
 * its preview controller, three blade files and the canvas script, with no
 * single definition; both halves now live here.
 *
 * @see \App\Site\SiteChrome the live-site render side
 * @see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor the editor side
 */
enum ChromeSlot: string
{
    case Header = 'header';
    case Footer = 'footer';

    /**
     * The prefix marking a page-editor selection key as chrome rather than a
     * page block. Exposed for the canvas script, which only needs to tell the
     * two apart.
     */
    public const string EDITOR_KEY_PREFIX = 'chrome:';

    /**
     * The slot block types, for excluding chrome from a list of page-level types.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The slot an editor selection key refers to, or null for a regular page
     * block key (including null — "nothing selected").
     */
    public static function fromEditorKey(?string $key): ?self
    {
        if ($key === null || ! str_starts_with($key, self::EDITOR_KEY_PREFIX)) {
            return null;
        }

        return self::tryFrom(mb_substr($key, mb_strlen(self::EDITOR_KEY_PREFIX)));
    }

    /**
     * The editor's pseudo selection key for this slot.
     */
    public function editorKey(): string
    {
        return self::EDITOR_KEY_PREFIX.$this->value;
    }
}
