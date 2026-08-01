<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\SectionAlign;
use App\Site\Blocks\SectionColumns;
use App\Site\Blocks\SectionImageShape;
use App\Site\Blocks\SectionItemStyle;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use App\Site\Blocks\SectionWidth;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Composes a full site draft from the enumerated block vocabulary: picks a
 * style preset, sequences blocks, chooses each section's layout variant and
 * presentation (tone + spacing), and writes copy. The three layout keys are
 * OPTIONAL flat siblings of `type`/`data` — a provider that omits or flubs
 * them costs nothing, because {@see \App\Actions\Pages\StampPresetDefaults::fill()}
 * back-fills the preset's defaults; bind targets still resolve at render time
 * and never appear in the schema. Header/footer are site chrome and are
 * excluded from the block type enum. The server-side gate is
 * {@see SiteDraftValidator}, which validates every layout choice against the
 * enums before it can reach `data`.
 *
 * A draft is the home page plus, when the profile gives real material, up to
 * three supporting pages from a FIXED slug menu — a closed set, so slugs are
 * collision-free and the header navigation can be stamped before anything
 * renders.
 *
 * Provider follows `config('ai.default')` (the AI_PROVIDER env); the model is
 * the provider's SMARTEST text tier (`ai.providers.*.models.text.smartest`) —
 * this runs once per site, unwatched, and its output quality is the whole
 * product moment, so it gets the deep tier while the interactive
 * {@see PageEditorAgent} gets the cheap one. Swapping providers remains a
 * .env change, not a code change.
 *
 * Low temperature: providers whose structured output is prompt-enforced
 * (DeepSeek's json_object mode) drift off-schema at default temperature.
 * Generous HTTP timeout: a full-page draft takes 50–90s on some providers,
 * past the default 60s client timeout.
 */
#[Temperature(0.2)]
#[Timeout(150)]
#[UseSmartestModel]
final readonly class SiteDraftAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private const string INSTRUCTIONS = 'You are a web designer composing a small-business website from a fixed '
        .'component vocabulary. You select components, pick a style preset, '
        .'choose each section\'s layout from the layouts its type offers, set a '
        .'section\'s background tone and vertical spacing where the page\'s '
        .'rhythm calls for it, and write marketing copy. You never output HTML, '
        .'CSS, Markdown or code. '
        .'Only use the block types and field names listed in the vocabulary. '
        .'Every block in your output is {"type": "<listed type>", "data": '
        .'{<listed field names only>}}, optionally with "variant", "tone" and '
        .'"spacing" — never rename, invent or omit the required keys. '
        .'Write concise, specific copy grounded in the business profile — '
        .'never invent facts, addresses, prices or reviews that are not in the '
        .'profile. Write ALL user-visible copy in the requested language.';

    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $blockTypes = $this->vocabulary->pageTypeNames();

        return [
            'preset' => $schema->string()
                ->enum(array_column(StylePreset::cases(), 'value'))
                ->description('The style preset whose vibe tags best match the business.')
                ->required(),
            'rationale' => $schema->string()
                ->max(300)
                ->description('One sentence on why this preset, this composition and this section rhythm fit the business.')
                ->required(),
            'pages' => $schema->array()
                ->min(1)
                ->max(1 + count(SiteDraftValidator::EXTRA_SLUGS))
                ->description('The home page (slug "/") first — required — then optional pages from the allowed slugs, only where the profile gives real material.')
                ->items($schema->object([
                    'title' => $schema->string()->min(1)->max(120)->required(),
                    'slug' => $schema->string()->enum(['/', ...SiteDraftValidator::EXTRA_SLUGS])->required(),
                    'meta_description' => $schema->string()
                        ->min(1)
                        ->max(160)
                        ->description('One plain sentence for the Google result: what the business does, for whom, where.')
                        ->required(),
                    'blocks' => $schema->array()
                        ->min(3)
                        ->max(10)
                        ->items($schema->object([
                            'type' => $schema->string()->enum($blockTypes)->required(),
                            'variant' => $schema->string()
                                ->enum($this->variants())
                                ->description('The layout for this section — ONLY one of the layouts the vocabulary lists for THIS block type. Omit to accept the style preset\'s default.'),
                            'tone' => $schema->string()
                                ->enum(SectionTone::values())
                                ->description('The background band this section sits on. Most sections should omit this; give at most ONE section on the page "accent" or "inverted" — see Design guidance.'),
                            'spacing' => $schema->string()
                                ->enum(SectionSpacing::values())
                                ->description('The vertical breathing room. Omit for the layout\'s own default.'),
                            'width' => $schema->string()
                                ->enum(SectionWidth::values())
                                ->description('The content column width: narrow reads, wide shows. Omit for the layout\'s own default.'),
                            'align' => $schema->string()
                                ->enum(SectionAlign::values())
                                ->description('The section header alignment. Omit for the layout\'s own default.'),
                            'columns' => $schema->string()
                                ->enum(SectionColumns::values())
                                ->description('How many columns the section\'s items flow into, where the type takes items. Omit for the layout\'s own default.'),
                            'item_style' => $schema->string()
                                ->enum(SectionItemStyle::values())
                                ->description('Whether items sit on cards or run plain. Omit for the layout\'s own default.'),
                            'image_shape' => $schema->string()
                                ->enum(SectionImageShape::values())
                                ->description('The crop item images render in. Omit for the layout\'s own default.'),
                            'data' => $schema->object()
                                ->description('Content fields for this block type, per the vocabulary.')
                                ->required(),
                        ]))
                        ->required(),
                ]))
                ->required(),
        ];
    }

    /**
     * Every layout any PAGE type offers, de-duplicated — the same union trick
     * as {@see \App\Ai\Tools\SetBlockVariant}, because a JSON Schema cannot
     * make one field's options depend on another's. It narrows the model's
     * guesses; {@see SiteDraftValidator} still checks each choice against its
     * own block's type. Chrome layouts (header/footer) never enter the enum.
     *
     * @return list<string>
     */
    private function variants(): array
    {
        $variants = [];

        foreach ($this->vocabulary->pageTypes() as $type) {
            $variants = [...$variants, ...$type->variants];
        }

        return array_values(array_unique($variants));
    }
}
