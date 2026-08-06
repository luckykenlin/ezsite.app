<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Location;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use App\Site\OpeningHoursForm;
use Illuminate\Support\Collection;
use Stringable;

/**
 * The dynamic context handed to {@see \App\Ai\Agents\SiteDraftAgent}: the
 * business profile, a locations digest, the block vocabulary (the agent's
 * entire allowed space), design guidance for the layout choices the schema
 * now offers, the preset menu with vibe tags, a composition brief, and the
 * output language.
 */
final readonly class SiteDraftPrompt implements Stringable
{
    /**
     * @param  Collection<int, Location>  $locations
     * @param  array<string, BlockType>  $vocabulary
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
            $this->designSection(),
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

        $formatter = new OpeningHoursForm;

        $digests = $this->locations->map(function (Location $location) use ($formatter): string {
            $address = implode(', ', array_filter([
                $location->address_line1,
                $location->city,
                $location->state,
                $location->postal_code,
            ]));

            $hours = collect($formatter->toFields($location->opening_hours))
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
            .'Compose the page ONLY from these block types. Every block object has the keys "type" '
            .'(one of the listed types) and "data" (an object whose keys come ONLY from that '
            ."type's \"fields\" list — fields not in the list are discarded), plus optional "
            .'"variant" (one of the layouts listed for that type), "tone" and "spacing" '
            ."(described under Design guidance).\n"
            .json_encode($this->vocabularyEntries(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            ."\n\nField rules:\n"
            .'- Use the exact field names listed. Do NOT invent names like "items", "text", '
            .'"cta_text" or "button_text" — e.g. hero uses cta_label/cta_url, features uses a '
            ."\"features\" list, gallery uses an \"images\" list, testimonials uses a \"testimonials\" list.\n"
            .'- List fields (features, testimonials, images, nav_links) take an array of flat '
            ."objects whose keys are also exact field names (e.g. features: [{icon, title, description}]).\n"
            .'- An "icon" is ONE emoji character (e.g. "✂️", "⭐"), never a word — a word renders '
            ."as literal text where a pictogram belongs.\n"
            .'- prose body copy goes in "paragraphs": a list of {text} objects. A prose block '
            ."with only a heading renders as an empty section — always include paragraphs.\n"
            ."- heading.level must be one of \"h1\"–\"h6\" (e.g. \"h2\"), never a bare number.\n"
            .'- For internal links (cta_url, nav urls) use relative paths like "/contact" or '
            ."anchors like \"#contact\"; never bare \"#\".\n"
            ."- The site header and footer are rendered automatically — do NOT include them.\n"
            .'- Blocks that bind to business data (contact) show the live address, phone and '
            .'opening hours automatically — write only their narrative fields (heading, intro).'
            .$this->imageQueryRule();
    }

    /**
     * The stock-photo instruction, only when the pipeline can act on it — an
     * `image_query` proposed while the pipeline is disabled would be silently
     * discarded, and asking for one anyway wastes tokens and trust.
     */
    private function imageQueryRule(): string
    {
        if (! config()->boolean('stock-photos.enabled')) {
            return '';
        }

        return "\n".'- Blocks that show photographs (hero, gallery, offerings, features) may include '
            .'"image_query" in their data: 2–4 concrete ENGLISH nouns describing the ideal photo '
            .'(e.g. "barber shop interior"), whatever the copy language. Real photographs are '
            .'attached automatically from it — never write image URLs yourself.';
    }

    /**
     * How to USE the layout keys the schema offers. The tone/spacing lines are
     * generated from the enums' own guidance (the same `describe()` pattern as
     * {@see \App\Ai\Tools\SetBlockAppearance}), never hand-copied — so a new
     * case or a reworded description reaches this prompt automatically. The
     * rhythm language is adapted from {@see \App\Ai\Agents\PageEditorAgent},
     * where it has already proven to keep a model from painting every section.
     */
    private function designSection(): string
    {
        return "## Design guidance\n"
            .'You choose each section\'s layout ("variant") and how it presents ("tone" and '
            .'"spacing"). Pick layouts with purpose and vary them — a page where every section '
            ."is the same shape reads as a template.\n"
            .'Backgrounds ("tone"): '.$this->describe(SectionTone::cases()).".\n"
            .'Vertical space ("spacing"): '.$this->describe(SectionSpacing::cases()).".\n"
            .'Give the page a rhythm, not a paint job: most sections sit on the page background; '
            .'place a muted band between plain sections to mark a change of subject; make exactly '
            .'ONE section stand out with accent or inverted — usually the call to action or the '
            .'strongest trust section — and leave the others alone. Never put two dark bands next '
            .'to each other. A page where every section claims its own colour has no rhythm at '
            .'all. When unsure, omit variant, tone and spacing: the style preset supplies a '
            .'considered default.';
    }

    /**
     * `value is when-to-use-it` lines for the guidance above.
     *
     * @param  list<SectionTone|SectionSpacing>  $cases
     */
    private function describe(array $cases): string
    {
        return implode('; ', array_map(
            static fn (SectionTone|SectionSpacing $case): string => $case->value.' is '.$case->description(),
            $cases,
        ));
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
            .'Compose the HOME page (slug "/") for this business: a hero first, then supporting '
            .'sections in a persuasive order. Choose sections by their intent — introduce the '
            .'business, showcase what it offers, build trust with real material, then convert '
            .'with one clear next step. Let the PROFILE decide the length: a rich profile earns '
            .'5–7 supporting sections; a sparse one earns 3–4 strong ones, and a short, specific '
            .'page is a better draft than a padded, generic one. Never add a section whose '
            .'content you would have to invent, and never return an empty blocks list. '
            .'Include a contact section when the business has a location. '
            .'Use testimonials only if the profile provides real quotes — never fabricate them. '
            ."\n\n"
            .'Then, ONLY where the profile gives real material for one, add supporting pages from '
            .'this fixed menu: "/about" (the story and the people), "/services" (what is offered, '
            .'in depth), "/contact" (how to reach and find the business). Each needs at least 3 '
            .'sections of substance — a page you would have to pad does not belong in the draft; '
            .'a sparse profile means the home page alone, and that is a good draft too. Do not '
            .'repeat the home page: a supporting page goes DEEPER on its one topic. '
            ."\n\n"
            .'For every page also write meta_description: one plain sentence (max 160 characters, no '
            .'marketing punctuation runs) naming the business, what that page covers and its city '
            .'— this is the summary Google shows under the search result.';
    }

    private function languageSection(): string
    {
        return "## Language\nWrite all user-visible copy in: ".($this->business->locale ?? 'en');
    }

    /**
     * The vocabulary as the model should see it: for each page-level type, its
     * purpose, its library intent (the Brief composes by these), its authorable
     * field names, and the layouts it offers with their human labels — the
     * label IS the "when to use" line, so the model can pick a layout by
     * purpose rather than by key name.
     *
     * Spelled out rather than json_encode()ing the BlockType objects directly —
     * their `sample` and `icon` are render/editor concerns that would only add
     * tokens and invite the model to copy placeholder copy verbatim. Chrome is
     * filtered here rather than trusted to the caller: the header and footer
     * are rendered automatically, and listing their fields would contradict
     * the rule that says not to compose them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function vocabularyEntries(): array
    {
        $entries = [];

        foreach ($this->vocabulary as $name => $type) {
            if ($type->isChrome()) {
                continue;
            }

            $entries[$name] = array_filter([
                'purpose' => $type->description,
                'intent' => $type->intent?->value,
                'fields' => $type->fields,
                'layouts' => $type->variantLabels === [] ? null : $type->variantLabels,
            ]);
        }

        return $entries;
    }
}
