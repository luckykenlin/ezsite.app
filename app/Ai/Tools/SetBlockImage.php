<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Models\Media;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Place an image from the tenant's media library into one block — the image on
 * a hero or CTA, a gallery/logos item, a team or testimonial avatar.
 *
 * The media id is the only thing the model authors, and it is validated
 * against the library (RLS scopes the lookup, so a cross-tenant id simply does
 * not exist). WHERE it lands is the block contract's decision, not the
 * model's: each type declares its media slots ({@see BlockType::$mediaField} /
 * `$itemMediaField`, derived from the form schema), so the model cannot
 * invent an `image_id` on a block that has no image.
 *
 * Deliberately does NOT switch layout variants: "make it the background" is
 * this tool for the image plus {@see SetBlockVariant} for the layout, and the
 * agent's instructions say so. One verb per tool, like the rest of the roster.
 */
final readonly class SetBlockImage implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private BlockVocabulary $vocabulary,
        private UpdatePageBlock $update,
    ) {
        //
    }

    public function description(): string
    {
        return 'Place an image from the media library into a block: the main image of a hero or cta, '
            .'or one item of a gallery, logos, features, offerings, team, or testimonials block '
            .'(pass item_index for those). Use only media ids announced in this conversation — '
            .'attached images are announced with their media id. Never invent a media id. '
            .'For a photographic background, set the image here and switch the layout with the '
            .'set block variant tool (hero: full-bleed-overlay, cta: full-photo).';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description("The block's key, as listed in the current page blocks.")
                ->required(),
            'media_id' => $schema->integer()
                ->description('The media library id of the image, exactly as announced in this conversation.')
                ->required(),
            'item_index' => $schema->integer()
                ->description('For blocks whose images live on repeater items (gallery, logos, features, offerings, team, testimonials): the zero-based index of the item to change.'),
            'alt' => $schema->string()
                ->description('Alt text for the image, when the slot supports it (gallery items). A short description of what the photo shows.'),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $block = $this->draft->locate($arguments['key'] ?? null);

        if ($block === null) {
            return $this->draft->reply('There is no block with that key on this page.');
        }

        $type = $this->vocabulary->get($block['type']);

        if (! $type instanceof BlockType || ! $type->acceptsMedia()) {
            return $this->draft->reply(sprintf("A '%s' block has no image slot, so nothing changed.", $block['type']));
        }

        $mediaId = $arguments['media_id'] ?? null;
        $media = is_int($mediaId) ? Media::query()->find($mediaId) : null;

        if (! $media instanceof Media) {
            return $this->draft->reply('There is no media with that id in the library — use a media id announced in this conversation.');
        }

        $itemIndex = $arguments['item_index'] ?? null;
        $alt = $arguments['alt'] ?? null;

        $data = $type->itemMediaField !== null && $type->itemsField !== null
            ? $this->placeOnItem($block['data'], $type, $mediaId, is_int($itemIndex) ? $itemIndex : null, is_string($alt) ? $alt : null)
            : $this->placeOnBlock($block['data'], $type, $mediaId);

        if (is_string($data)) {
            return $this->draft->reply($data);
        }

        $this->draft->replace($this->update->handle($this->draft->blocks(), $block['key'], $data));

        return $this->draft->reply(sprintf('Placed media %d on the %s block.', $mediaId, $block['type']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function placeOnBlock(array $data, BlockType $type, int $mediaId): array
    {
        $data[(string) $type->mediaField] = $mediaId;

        return $data;
    }

    /**
     * Set the media id on one repeater item, or explain why that could not be
     * done (the string return is the correction the model reads).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|string
     */
    private function placeOnItem(array $data, BlockType $type, int $mediaId, ?int $itemIndex, ?string $alt): array|string
    {
        $itemsField = (string) $type->itemsField;

        if ($itemIndex === null) {
            return sprintf("A '%s' block keeps its images on its '%s' items — pass item_index to say which one.", $type->type, $itemsField);
        }

        $items = is_array($data[$itemsField] ?? null) ? $data[$itemsField] : [];
        $keys = array_keys($items);

        // Filament repeaters key items by uuid, so position N is the N-th KEY,
        // not offset N.
        if (! array_key_exists($itemIndex, $keys)) {
            return sprintf("The '%s' block has %d items — item_index %d does not exist.", $type->type, count($items), $itemIndex);
        }

        $itemKey = $keys[$itemIndex];

        if (! is_array($items[$itemKey])) {
            $items[$itemKey] = [];
        }

        $items[$itemKey][(string) $type->itemMediaField] = $mediaId;

        if ($alt !== null && $alt !== '') {
            $items[$itemKey]['alt'] = $alt;
        }

        $data[$itemsField] = $items;

        return $data;
    }
}
