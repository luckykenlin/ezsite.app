<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Design\StylePreset;
use App\Filament\Fabricator\BlockRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Composes a full site draft from the enumerated block vocabulary: picks a
 * style preset, sequences blocks and writes copy — nothing else. Layout
 * variants are stamped server-side from the preset and bind targets resolve
 * at render time, so neither appears in the schema; header/footer are site
 * chrome and are excluded from the block type enum. The server-side gate is
 * {@see \App\Ai\SiteDraftValidator}.
 *
 * Provider/model follow `config('ai.default')` (the AI_PROVIDER env) and the
 * provider's default text model — e.g. DeepSeek → deepseek-v4-pro — so
 * swapping providers is a .env change, not a code change.
 *
 * Low temperature: providers whose structured output is prompt-enforced
 * (DeepSeek's json_object mode) drift off-schema at default temperature.
 * Generous HTTP timeout: a full-page draft takes 50–90s on some providers,
 * past the default 60s client timeout.
 */
#[Temperature(0.2)]
#[Timeout(150)]
final class SiteDraftAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private const string INSTRUCTIONS = 'You are a web designer composing a small-business website from a fixed '
        .'component vocabulary. You only select components, pick a style preset, '
        .'and write marketing copy. You never output HTML, CSS, Markdown or code. '
        .'Only use the block types and field names listed in the vocabulary. '
        .'Every block in your output is exactly {"type": "<listed type>", "data": '
        .'{<listed field names only>}} — never rename, invent or omit these keys. '
        .'Write concise, benefit-led copy grounded in the business profile — '
        .'never invent facts, addresses, prices or reviews that are not in the '
        .'profile. Write ALL user-visible copy in the requested language.';

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $blockTypes = array_values(array_diff(
            array_keys(BlockRegistry::vocabulary()),
            ['header', 'footer'],
        ));

        return [
            'preset' => $schema->string()
                ->enum(array_column(StylePreset::cases(), 'value'))
                ->description('The style preset whose vibe tags best match the business.')
                ->required(),
            'rationale' => $schema->string()
                ->max(300)
                ->description('One sentence on why this preset and composition fit the business.')
                ->required(),
            'pages' => $schema->array()
                ->min(1)
                ->max(1)
                ->items($schema->object([
                    'title' => $schema->string()->min(1)->max(120)->required(),
                    'slug' => $schema->string()->enum(['/'])->required(),
                    'blocks' => $schema->array()
                        ->min(3)
                        ->max(10)
                        ->items($schema->object([
                            'type' => $schema->string()->enum($blockTypes)->required(),
                            'data' => $schema->object()
                                ->description('Content fields for this block type, per the vocabulary.')
                                ->required(),
                        ]))
                        ->required(),
                ]))
                ->required(),
        ];
    }
}
