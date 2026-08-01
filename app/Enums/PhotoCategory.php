<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The subject matter of a shared-library photo
 * ({@see \App\Models\LibraryPhoto}).
 *
 * A CLOSED set on purpose. The category is guessed from free text (a search
 * query plus the provider's alt text), and if that guess produced free-form
 * strings the AI's filter argument would be unusable: it would have to know
 * that this library happens to spell it "food & drink" and not "restaurant".
 * A dozen cases the model can enumerate is worth more than exact taxonomy.
 */
enum PhotoCategory: string
{
    // DECLARATION ORDER IS GUESS PRIORITY — see guess(). The specific subjects
    // come first, then the settings a subject might be photographed in, then the
    // catch-all abstractions. Reordering these changes how photos are
    // classified, so it is not a cosmetic edit.
    case FoodDrink = 'food-drink';
    case People = 'people';
    case Animal = 'animal';
    case Product = 'product';
    case Workspace = 'workspace';
    case Interior = 'interior';
    case Exterior = 'exterior';
    case Cityscape = 'cityscape';
    case Nature = 'nature';
    case Event = 'event';
    case Texture = 'texture';
    case Abstract = 'abstract';

    /**
     * The first category whose keywords appear in the given text, or null when
     * nothing matches — an uncategorised photo is still perfectly searchable
     * through its keywords, so guessing wrong is worse than not guessing.
     *
     * Order matters: the cases are tried in declaration order, so the more
     * specific subjects (a dish, a person) win over the settings they happen
     * to be photographed in.
     */
    public static function guess(string $text): ?self
    {
        $haystack = mb_strtolower($text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $haystack) ?: [];

        foreach (self::cases() as $category) {
            foreach ($category->keywords() as $keyword) {
                if (self::matches($keyword, $haystack, $words)) {
                    return $category;
                }
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::FoodDrink => 'Food & drink',
            self::People => 'People',
            self::Animal => 'Animal',
            self::Product => 'Product',
            self::Workspace => 'Workspace',
            self::Interior => 'Interior',
            self::Exterior => 'Exterior',
            self::Cityscape => 'Cityscape',
            self::Nature => 'Nature',
            self::Event => 'Event',
            self::Texture => 'Texture',
            self::Abstract => 'Abstract',
        };
    }

    /**
     * The words that place a photo in this category, matched whole (see
     * {@see matches()}).
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return match ($this) {
            self::FoodDrink => ['food', 'meal', 'dish', 'drink', 'coffee', 'cafe', 'café', 'restaurant', 'bakery', 'bakeries', 'bar', 'wine', 'cocktail', 'kitchen', 'menu', 'dessert', 'breakfast', 'lunch', 'dinner', 'pizza', 'tea'],
            self::People => ['people', 'person', 'portrait', 'woman', 'man', 'child', 'team', 'staff', 'customer', 'client', 'smiling', 'hands', 'face'],
            self::Animal => ['animal', 'dog', 'cat', 'pet', 'bird', 'horse', 'puppy', 'kitten'],
            self::Product => ['product', 'packaging', 'bottle', 'jar', 'box', 'clothing', 'shoe', 'jewelry', 'jewellery', 'cosmetic', 'flatlay', 'flat lay'],
            self::Workspace => ['office', 'desk', 'workspace', 'laptop', 'computer', 'meeting', 'coworking', 'studio', 'workshop', 'tools'],
            self::Interior => ['interior', 'indoor', 'inside', 'room', 'lobby', 'salon', 'living room', 'bedroom', 'furniture', 'shop interior'],
            self::Exterior => ['exterior', 'facade', 'storefront', 'shopfront', 'entrance', 'building', 'outdoor', 'terrace', 'garden'],
            self::Cityscape => ['city', 'cities', 'street', 'skyline', 'urban', 'downtown', 'bridge', 'traffic'],
            self::Nature => ['nature', 'landscape', 'forest', 'mountain', 'beach', 'ocean', 'sea', 'sky', 'flower', 'plant', 'tree', 'sunset', 'field'],
            self::Event => ['event', 'wedding', 'party', 'concert', 'conference', 'celebration', 'festival'],
            self::Texture => ['texture', 'pattern', 'background', 'surface', 'marble', 'wood grain', 'fabric', 'concrete'],
            self::Abstract => ['abstract', 'gradient', 'blur', 'geometric', 'minimal'],
        };
    }

    /**
     * WHOLE WORDS, not substrings, and this is load-bearing rather than
     * pedantic: on substring matching "bar" classifies every *barber* shop as
     * food & drink and "tea" classifies a *team* photo the same way. Plurals are
     * tolerated so "restaurants" still hits "restaurant". Multi-word keywords
     * ("flat lay") match as a phrase, since they have no single word to compare.
     *
     * @param  list<string>  $words
     */
    private static function matches(string $keyword, string $haystack, array $words): bool
    {
        if (str_contains($keyword, ' ')) {
            return str_contains($haystack, $keyword);
        }

        return array_any(
            $words,
            fn (string $word): bool => in_array($word, [$keyword, $keyword.'s', $keyword.'es'], true),
        );
    }
}
