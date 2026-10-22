<?php

declare(strict_types=1);

namespace App\Site\Blocks;

use App\Themes\ThemeDefinition;
use Closure;

/**
 * The memo behind {@see BlockVocabulary}: one built vocabulary per theme
 * definition, for as long as the container keeps this scoped instance.
 *
 * The vocabulary used to be the scoped instance itself, which was right while
 * it was the same for every site. It is now theme-dependent, and nothing
 * flushes scoped instances when `RunInTenant` switches tenants inside one
 * process — so a queue worker that built tenant A's vocabulary would hand it to
 * tenant B on a different theme. Keying the memo by the definition the
 * vocabulary was built FOR makes the switch harmless: a different theme is a
 * different key, and the same theme keeps the walk over every block class it
 * exists to avoid repeating.
 *
 * Keyed by object identity (`spl_object_id`) rather than by the {@see
 * \App\Themes\Theme} case, because that is what is actually current: a test
 * binds a hand-built definition with no case behind it, and `Theme::definition()`
 * returns one stable object per case, so identity is exactly "the same theme".
 * Holding the definition beside its vocabulary keeps the id from being reused
 * by a later object while this memo lives.
 *
 * The builder is injected rather than called by name because the block classes
 * that know how to build one live under `App\Filament`, which this layer must
 * not import; the provider passes `BlockRegistry::contracts(...)`. It builds for
 * whatever theme is current when it runs, which is the definition {@see for()}
 * was just asked about — the only caller resolves both from `CurrentTheme` in
 * the same breath.
 */
final class BlockVocabularies
{
    /**
     * @var array<int, array{ThemeDefinition, BlockVocabulary}>
     */
    private array $built = [];

    /**
     * @param  Closure(): array<string, BlockType>  $builder
     */
    public function __construct(private readonly Closure $builder)
    {
        //
    }

    public function for(ThemeDefinition $definition): BlockVocabulary
    {
        $id = spl_object_id($definition);

        if (! isset($this->built[$id])) {
            $this->built[$id] = [$definition, new BlockVocabulary(($this->builder)())];
        }

        return $this->built[$id][1];
    }
}
