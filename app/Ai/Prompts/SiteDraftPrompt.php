<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

use App\Actions\FormatOpeningHours;
use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Support\Collection;
use Stringable;

/**
 * The dynamic context handed to {@see \App\Ai\Agents\SiteDraftAgent}: the
 * business profile, a locations digest, the block vocabulary (the agent's
 * entire allowed space), the preset menu with vibe tags, a composition
 * brief, and the output language.
 */
final readonly class SiteDraftPrompt implements Stringable
{
    /**
     * @param  Collection<int, Location>  $locations
     * @param  array<string, array{type: string, variants: list<string>, bind: string|null, fields: list<string>}>  $vocabulary
     */
    public function __construct(
        private Business $business,
        private Collection $locations,
        private array $vocabulary,
    ) {
        //
    }

    public function __toString(): string
    {
        return implode("\n\n", [
            $this->profileSection(),
            $this->locationsSection(),
            $this->vocabularySection(),
            $this->presetsSection(),
            $this->briefSection(),
            $this->languageSection(),
        ]);
    }

    private function profileSection(): string
    {
        $lines = array_filter([
            'Name: '.$this->business->name,
            $this->business->category !== null ? 'Category: '.$this->business->category : null,
            $this->business->tagline !== null ? 'Tagline: '.$this->business->tagline : null,
            $this->business->description !== null ? 'Description: '.$this->business->description : null,
            $this->business->contact_email !== null ? 'Email: '.$this->business->contact_email : null,
            $this->business->contact_phone !== null ? 'Phone: '.$this->business->contact_phone : null,
            $this->business->website_url !== null ? 'Current website: '.$this->business->website_url : null,
        ]);

        return "## Business profile\n".implode("\n", $lines);
    }

    private function locationsSection(): string
    {
        if ($this->locations->isEmpty()) {
            return "## Locations\nNone recorded yet.";
        }

        $formatter = new FormatOpeningHours;

        $digests = $this->locations->map(function (Location $location) use ($formatter): string {
            $address = implode(', ', array_filter([
                $location->address_line1,
                $location->city,
                $location->state,
                $location->postal_code,
            ]));

            $hours = collect($formatter->handle($location->opening_hours))
                ->filter()
                ->map(fn (string $ranges, string $day): string => ucfirst($day).' '.$ranges)
                ->implode('; ');

            return implode("\n", array_filter([
                '- '.$location->label.($location->is_primary ? ' (primary)' : ''),
                $address !== '' ? '  Address: '.$address : null,
                $location->phone !== null ? '  Phone: '.$location->phone : null,
                $hours !== '' ? '  Hours: '.$hours : null,
            ]));
        });

        return "## Locations\n".$digests->implode("\n");
    }

    private function vocabularySection(): string
    {
        return "## Block vocabulary\n"
            .'Compose the page ONLY from these block types. Every block object has EXACTLY two keys: '
            .'"type" (one of the listed types) and "data" (an object whose keys come ONLY from that '
            ."type's \"fields\" list — fields not in the list are discarded).\n"
            .json_encode($this->vocabulary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            ."\n\nField rules:\n"
            .'- Use the exact field names listed. Do NOT invent names like "items", "text", '
            .'"cta_text" or "button_text" — e.g. hero uses cta_label/cta_url, features uses a '
            ."\"features\" list, gallery uses an \"images\" list, testimonials uses a \"testimonials\" list.\n"
            .'- List fields (features, testimonials, images, nav_links) take an array of flat '
            ."objects whose keys are also exact field names (e.g. features: [{icon, title, description}]).\n"
            ."- heading.level must be one of \"h1\"–\"h6\" (e.g. \"h2\"), never a bare number.\n"
            .'- For internal links (cta_url, nav urls) use relative paths like "/contact" or '
            ."anchors like \"#contact\"; never bare \"#\".\n"
            ."- The site header and footer are rendered automatically — do NOT include them.\n"
            .'- Blocks that bind to business data (contact) show the live address, phone and '
            .'opening hours automatically — write only their narrative fields (heading, intro).';
    }

    private function presetsSection(): string
    {
        $lines = array_map(
            fn (StylePreset $preset): string => sprintf(
                '- %s — %s (vibes: %s)',
                $preset->value,
                $preset->description(),
                implode(', ', $preset->vibes()),
            ),
            StylePreset::cases(),
        );

        return "## Style presets\nPick the preset whose vibes best match the business.\n".implode("\n", $lines);
    }

    private function briefSection(): string
    {
        return "## Brief\n"
            .'Compose ONE home page for this business: a hero first, then 2–7 supporting '
            .'sections in a persuasive order (e.g. features, gallery, testimonials, contact, cta). '
            .'Include a contact section when the business has a location. '
            .'Use testimonials only if the profile provides real quotes — never fabricate them. '
            .'If the profile is sparse, STILL compose the full page: write neutral, '
            .'category-appropriate copy that makes no specific factual claims. '
            .'Never return an empty blocks list.';
    }

    private function languageSection(): string
    {
        return "## Language\nWrite all user-visible copy in: ".($this->business->locale ?? 'en');
    }
}
