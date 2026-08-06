<?php

declare(strict_types=1);

namespace App\Ai;

use InvalidArgumentException;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;

/**
 * One file the operator attached to a chat message, in the one shape every
 * layer shares: the Livewire trait builds it from the upload, the job payload
 * and the `page_chat_messages.attachments` column carry its array form, the
 * agent rehydrates the SDK file from it for conversation history, and the
 * prompt and transcript chips render from its metadata.
 *
 * `$file` is the SDK's own `File::toArray()` shape (type + disk + path for
 * stored files) rather than fields of our own, so rehydration is a single
 * {@see File::fromArray()} call and the stored form stays valid against
 * whatever the SDK accepts.
 */
final readonly class ChatAttachment
{
    public const string KIND_IMAGE = 'image';

    public const string KIND_DOCUMENT = 'document';

    /**
     * @param  'image'|'document'  $kind
     * @param  string  $name  the operator's original filename — what the chips
     *                        and the prompt call the file
     * @param  array<array-key, mixed>  $file  {@see File::toArray()} shape
     * @param  int|null  $mediaId  the curator Media row an image was imported
     *                             into — what SetBlockImage places; null for
     *                             documents
     */
    public function __construct(
        public string $kind,
        public string $name,
        public array $file,
        public ?int $mediaId = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
        //
    }

    /**
     * Null on anything malformed rather than throwing: these rows come back
     * from JSON columns and queued payloads, and one corrupt attachment must
     * not take the whole transcript (or turn) down with it.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }

        $kind = $data['kind'] ?? null;
        $name = $data['name'] ?? null;
        $file = $data['file'] ?? null;

        if (! in_array($kind, [self::KIND_IMAGE, self::KIND_DOCUMENT], true) || ! is_string($name) || ! is_array($file)) {
            return null;
        }

        $mediaId = $data['media_id'] ?? null;
        $width = $data['width'] ?? null;
        $height = $data['height'] ?? null;

        return new self(
            $kind,
            $name,
            $file,
            is_int($mediaId) ? $mediaId : null,
            is_int($width) ? $width : null,
            is_int($height) ? $height : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'name' => $this->name,
            'file' => $this->file,
            'media_id' => $this->mediaId,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * The SDK attachment this row rehydrates to — a {@see StoredImage} or
     * {@see StoredDocument} named after the operator's file. Null when the
     * stored shape no longer reconstructs, for the same reason
     * {@see fromArray()} is defensive.
     */
    public function toFile(): ?File
    {
        try {
            $file = File::fromArray($this->file);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $file?->as($this->name);
    }

    /**
     * The one line the prompt's attachments section prints for this file —
     * for an image, everything the model needs to place it without a
     * media-library tool.
     */
    public function promptLine(): string
    {
        if ($this->kind === self::KIND_IMAGE) {
            $dimensions = $this->width !== null && $this->height !== null
                ? sprintf(' (%dx%d)', $this->width, $this->height)
                : '';

            return sprintf(
                "image '%s'%s — already imported into the media library as media id %s; place it with the set block image tool",
                $this->name,
                $dimensions,
                $this->mediaId ?? 'unknown',
            );
        }

        return sprintf("document '%s' — attached to this message; read it directly", $this->name);
    }
}
