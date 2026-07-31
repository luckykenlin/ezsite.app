<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The two readings of a raw design selection — `preset` plus every
 * {@see TokenKey}, arriving from a Filament form, an AI tool call or a cache
 * entry.
 *
 * Both readings existed in several copies before this class. {@see normalise()}
 * was written three times (both cache actions and the editor's canvas preview)
 * and {@see changes()} twice ({@see \App\Actions\SaveDesignSelection} and
 * {@see \App\Ai\Tools\SetSiteStyle}), each a `foreach (TokenKey::values())` over
 * the same `is_string()` test. That is the duplication `TokenKey` was introduced
 * to end, and the reason it matters is the same as it was there: a copy that
 * names its keys — or forgets one — silently DROPS a token, so the assistant
 * describes a change the operator never sees.
 *
 * Static and stateless like {@see TokenOptions}, and in the design module for
 * the reason that one is: the readers are an action, a tool and a Livewire
 * component, so owning this anywhere else would make one of them depend on
 * another.
 */
final class TokenSelection
{
    /**
     * A selection reduced to the keys the design surfaces understand, every
     * value either a string or null.
     *
     * Callers hand over data from outside the process — cached turn results,
     * cached undo snapshots, Livewire payloads — which is why the input is
     * `mixed` and every value is re-tested rather than trusted. A junk VALUE
     * cannot reach CSS (both {@see DesignTokens::fromArray()} and
     * {@see \App\Actions\UpdateDesignTokens} re-resolve every one against its
     * token enum), but an unexpected KEY would ride along into the editor's
     * state, so only known ones survive.
     *
     * Returns null only for input that is not an array at all, which every
     * caller reads as "no style was staged". An array in always means an array
     * out, so a normalised selection is never mistaken for an absent one.
     *
     * @return array<string, string|null>|null
     */
    public static function normalise(mixed $selection): ?array
    {
        if (! is_array($selection)) {
            return null;
        }

        $normalised = ['preset' => self::string($selection, 'preset')];

        foreach (TokenKey::values() as $key) {
            $normalised[$key] = self::string($selection, $key);
        }

        return $normalised;
    }

    /**
     * Only the token keys the caller actually supplied, as raw enum values.
     *
     * The absences are the point: an omitted key means "leave this token alone",
     * so a hidden form field or an unset tool argument must not be written as
     * null. `preset` is excluded — it is a marker, not a token, and the two
     * write paths handle it separately.
     *
     * @param  array<array-key, mixed>  $selection
     * @return array<string, string>
     */
    public static function changes(array $selection): array
    {
        $changes = [];

        foreach (TokenKey::values() as $key) {
            $value = self::string($selection, $key);

            if ($value !== null) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private static function string(array $selection, string $key): ?string
    {
        $value = $selection[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
